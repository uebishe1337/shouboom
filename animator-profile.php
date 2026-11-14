<?php
require 'db.php';

$id = (int)($_GET['id'] ?? 0);
$animator = $id ? get_animator($pdo, $id) : null;
if (!$animator) {
    http_response_code(404);
    $pageTitle = 'Аниматор не найден';
    $active = 'animators';
    require 'includes/header.php';
    echo '<div class="page-wrap"><div class="page-inner"><div class="empty-state"><div class="empty-emoji">🙈</div><h3>Аниматор не найден</h3><p>Возможно, он больше не работает на ШоуБум.</p><a class="btn-teal" href="animators.php">Вернуться в каталог</a></div></div></div>';
    require 'includes/footer.php';
    exit;
}

$avg = (float)$animator['avg_rating'];
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$busy = month_bookings($pdo, $id, $month);
$firstDay = strtotime($month . '-01');
$daysInMonth = (int)date('t', $firstDay);
$startWeekday = (int)date('N', $firstDay); // 1 — понедельник

$pageTitle = $animator['name'];
$active = 'animators';
require 'includes/header.php';
?>
<div class="page-wrap">
  <div class="page-inner">
    <div class="breadcrumbs"><a href="index.php">Главная</a> / <a href="animators.php">Аниматоры</a> / <?= e($animator['name']) ?></div>

    <div class="profile-layout">
      <aside class="profile-aside">
        <?php if (!empty($animator['photo'])): ?>
          <img class="profile-avatar avatar-photo" src="<?= e($animator['photo']) ?>" alt="<?= e($animator['name']) ?>">
        <?php else: ?>
          <div class="profile-avatar avatar-initials" style="background:#1a9ea0"><?= e(initials($animator['name'])) ?></div>
        <?php endif; ?>
        <div class="profile-rating">★ <?= number_format($avg, 1, '.', '') ?> <span>· <?= (int)$animator['reviews_count'] ?> отзывов</span></div>
        <a href="order.php?animator_id=<?= (int)$animator['animator_id'] ?>" class="btn-orange" style="width:100%;text-align:center;display:block;margin-top:10px">Заказать</a>
        <a href="animators.php" class="btn-ghost" style="width:100%;text-align:center;display:block;margin-top:8px">В каталог</a>
      </aside>

      <div>
        <h1 class="profile-name"><?= e($animator['name']) ?></h1>
        <div class="profile-role"><?= e($animator['specialization']) ?> · <?= e($animator['city']) ?> · опыт <?= (int)$animator['experience_years'] ?> лет</div>
        <div class="profile-price">Ставка: <b><?= format_price($animator['hourly_rate']) ?></b> / час</div>

        <?php if ($animator['bio']): ?>
          <div class="profile-section-title">О себе</div>
          <p class="profile-bio"><?= nl2br(e($animator['bio'])) ?></p>
        <?php endif; ?>

        <?php if ($animator['age_groups']): ?>
          <div class="profile-section-title">Возрастные группы</div>
          <div class="tag-row">
            <?php foreach ($animator['age_groups'] as $ag): ?>
              <span class="tag active"><?= e($ag['title']) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="profile-section-title">Тематические программы</div>
        <?php if ($animator['programs']): ?>
          <div class="profile-programs">
            <?php foreach ($animator['programs'] as $p): ?>
              <div class="profile-program">
                <span class="pp-emoji"><?= e(program_icon($p['title'], $p['category'])) ?></span>
                <div>
                  <b><?= e($p['title']) ?></b>
                  <span><?= e($p['category']) ?> · <?= (int)$p['duration_min'] ?> мин</span>
                </div>
                <div class="pp-price"><?= format_price($p['custom_price'] ?: $p['price']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted">Программы не указаны.</p>
        <?php endif; ?>

        <div class="profile-section-title">Портфолио</div>
        <?php if ($animator['portfolio']): ?>
          <div class="portfolio-grid">
            <?php foreach ($animator['portfolio'] as $photo): ?>
              <a class="portfolio-thumb" href="<?= e($photo['file_path']) ?>" target="_blank" title="<?= e($photo['title']) ?>">
                <img src="<?= e($photo['file_path']) ?>" alt="<?= e($photo['title']) ?>">
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted">Портфолио пока пустое.</p>
        <?php endif; ?>

        <div class="profile-section-title">Календарь занятости</div>
        <div class="calendar-box">
          <div class="cal-head">
            <div class="cal-month"><?= e(month_label($month)) ?></div>
            <div class="cal-nav">
              <a href="?id=<?= (int)$id ?>&month=<?= e(date('Y-m', strtotime($month . '-01 -1 month'))) ?>">←</a>
              <a href="?id=<?= (int)$id ?>&month=<?= e(date('Y-m', strtotime($month . '-01 +1 month'))) ?>">→</a>
            </div>
          </div>
          <div class="cal-grid">
            <?php foreach (['Пн','Вт','Ср','Чт','Пт','Сб','Вс'] as $d): ?><div class="cal-day-name"><?= $d ?></div><?php endforeach; ?>
            <?php for ($i = 1; $i < $startWeekday; $i++): ?><div class="cal-empty"></div><?php endfor; ?>
            <?php for ($day = 1; $day <= $daysInMonth; $day++):
              $date = sprintf('%s-%02d', $month, $day);
              $isBusy = isset($busy[$date]);
              $isPast = strtotime($date) < strtotime(date('Y-m-d'));
            ?>
              <div class="cal-cell <?= $isBusy ? 'busy' : ($isPast ? 'past' : 'free') ?>" title="<?= e(format_date($date)) ?><?= $isBusy ? ': есть бронирование' : ': свободен' ?>">
                <?= $day ?>
              </div>
            <?php endfor; ?>
          </div>
          <div class="cal-legend">
            <span><i class="dot-free"></i> свободен</span>
            <span><i class="dot-busy"></i> занят</span>
          </div>
        </div>

        <div class="profile-section-title">Отзывы</div>
        <?php if ($animator['reviews']): ?>
          <div class="reviews-list">
            <?php foreach ($animator['reviews'] as $r): ?>
              <article class="review-card">
                <div class="review-avatar"><?= e(initials($r['customer_name'])) ?></div>
                <div class="review-content">
                  <div class="review-name"><?= e($r['customer_name']) ?></div>
                  <div class="review-stars"><?= stars((int)$r['rating']) ?></div>
                  <div class="review-event"><?= e($r['program_title'] ?: 'Праздник') ?> · <?= e(format_date($r['event_date'])) ?></div>
                  <div class="review-text"><?= nl2br(e($r['text'])) ?></div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted">Пока нет отзывов.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require 'includes/footer.php'; ?>
