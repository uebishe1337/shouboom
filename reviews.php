<?php
require 'db.php';

$rating = (int)($_GET['rating'] ?? 0);
$animatorId = (int)($_GET['animator_id'] ?? 0);
$sort = $_GET['sort'] ?? 'date';
$sort = in_array($sort, ['date', 'rating'], true) ? $sort : 'date';

$where = [];
$params = [];
if ($rating >= 1 && $rating <= 5) {
    $where[] = 'r.rating = ?';
    $params[] = $rating;
}
if ($animatorId > 0) {
    $where[] = 'r.animator_id = ?';
    $params[] = $animatorId;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$orderSql = $sort === 'rating' ? ' ORDER BY r.rating DESC, r.created_at DESC' : ' ORDER BY r.created_at DESC';

$stmt = $pdo->prepare(
    'SELECT r.*, u.name AS customer_name, a.name AS animator_name,
            b.event_date, p.title AS program_title
     FROM reviews r
     JOIN users u ON u.user_id = r.customer_id
     JOIN animators an ON an.animator_id = r.animator_id
     JOIN users a ON a.user_id = an.user_id
     JOIN bookings b ON b.booking_id = r.booking_id
     LEFT JOIN programs p ON p.program_id = b.program_id'
     . $whereSql . $orderSql
);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$avg = (float)$pdo->query('SELECT COALESCE(ROUND(AVG(rating),1), 5.0) FROM reviews')->fetchColumn();
$count = (int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
$animatorOptions = $pdo->query('SELECT a.animator_id, u.name FROM animators a JOIN users u ON u.user_id=a.user_id ORDER BY u.name')->fetchAll();

$pageTitle = 'Отзывы';
$active = 'reviews';
require 'includes/header.php';
?>
<div class="page-wrap">
  <div class="page-inner">
    <h1 class="section-title-center">Отзывы наших клиентов</h1>
    <p class="section-sub-center">Только подтверждённые отзывы после завершённых заказов</p>

    <div class="reviews-summary">
      <div class="reviews-score">
        <b><?= number_format($avg, 1, '.', '') ?></b>
        <div class="review-stars"><?= stars((int)round($avg)) ?></div>
        <span><?= $count ?> отзывов</span>
      </div>
      <div class="reviews-filters">
        <form method="GET" action="reviews.php" class="reviews-filter-form">
          <select class="form-input" name="rating">
            <option value="">Все оценки</option>
            <?php for ($i = 5; $i >= 1; $i--): ?>
              <option value="<?= $i ?>" <?= $rating === $i ? 'selected' : '' ?>><?= stars($i) ?> (<?= $i ?>)</option>
            <?php endfor; ?>
          </select>
          <select class="form-input" name="animator_id">
            <option value="">Все аниматоры</option>
            <?php foreach ($animatorOptions as $a): ?>
              <option value="<?= (int)$a['animator_id'] ?>" <?= $animatorId === (int)$a['animator_id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="form-input" name="sort">
            <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>Сначала новые</option>
            <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>По оценке</option>
          </select>
          <button class="btn-teal" type="submit">Показать</button>
        </form>
      </div>
    </div>

    <?php if (!$reviews): ?>
      <div class="empty-state"><div class="empty-emoji">💬</div><h3>Отзывов пока нет</h3><p>Станьте первым, кто поделится впечатлениями о празднике.</p><a href="animators.php" class="btn-teal">Выбрать аниматора</a></div>
    <?php else: ?>
      <div class="reviews-list">
        <?php foreach ($reviews as $r): ?>
          <article class="review-card">
            <div class="review-avatar"><?= e(initials($r['customer_name'])) ?></div>
            <div class="review-content">
              <div class="review-head">
                <div>
                  <div class="review-name"><?= e($r['customer_name']) ?></div>
                  <div class="review-event"><?= e($r['program_title'] ?: 'Праздник') ?> · аниматор <?= e($r['animator_name']) ?> · <?= e(format_date($r['event_date'])) ?></div>
                </div>
                <div class="review-stars" title="<?= (int)$r['rating'] ?> из 5"><?= stars((int)$r['rating']) ?></div>
              </div>
              <div class="review-text"><?= nl2br(e($r['text'])) ?></div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="review-cta">
      <div>
        <b>Были на празднике с ШоуБум?</b>
        <p>Оставить отзыв можно в личном кабинете после завершения заказа.</p>
      </div>
      <a href="cabinet.php" class="btn-orange">В личный кабинет</a>
    </div>
  </div>
</div>
<?php require 'includes/footer.php'; ?>
