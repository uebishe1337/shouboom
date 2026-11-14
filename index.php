<?php
require 'db.php';

$programs = get_programs($pdo);
$animators = array_slice(available_animators($pdo), 0, 3);
$ageGroups = get_age_groups($pdo);
$specializations = get_specializations($pdo);

$bookingsWeek = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE YEARWEEK(event_date,1)=YEARWEEK(CURDATE(),1) AND status IN ('pending','confirmed')")->fetchColumn();
$reviewsAvg = (float)$pdo->query('SELECT COALESCE(ROUND(AVG(rating),1), 5.0) FROM reviews')->fetchColumn();
$animatorsCount = (int)$pdo->query('SELECT COUNT(*) FROM animators WHERE is_active=1')->fetchColumn();

$pageTitle = 'Главная';
$active = 'index';
require 'includes/header.php';
?>

<main class="page-main">
  <section class="hero home-hero">
    <div class="hero-copy">
      <div class="hero-kicker">Праздник без хлопот</div>
      <h1 class="hero-title">Праздник начинается<br><span class="hero-accent">с первого клика</span></h1>
      <p class="hero-sub"><?= e(SITE_TAGLINE) ?></p>
      <div class="hero-actions">
        <a href="animators.php" class="btn-orange">Подобрать аниматора</a>
        <a href="prices.php" class="btn-ghost">Смотреть цены</a>
      </div>
      <div class="hero-stats">
        <div><b><?= $animatorsCount ?></b><span>аниматора в каталоге</span></div>
        <div><b>★ <?= number_format($reviewsAvg, 1, '.', '') ?></b><span>средняя оценка</span></div>
        <div><b><?= $bookingsWeek ?></b><span>бронирований на неделе</span></div>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true">
      <div class="hero-balloon b1">🎈</div>
      <div class="hero-balloon b2">🎉</div>
      <div class="hero-balloon b3">🎁</div>
      <div class="hero-card-float">
        <div class="float-emoji">🧸</div>
        <div>
          <b>Аниматоры свободны</b>
          <span>Выберите дату и время</span>
        </div>
      </div>
    </div>
  </section>

  <section class="quick-search">
    <form action="animators.php" method="GET" class="quick-form">
      <div>
        <label>Специализация</label>
        <select name="specialization" class="form-input">
          <option value="">Любая</option>
          <?php foreach ($specializations as $spec): ?>
            <option value="<?= e($spec) ?>"><?= e($spec) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Возраст ребёнка</label>
        <select name="age_group_id" class="form-input">
          <option value="">Любой</option>
          <?php foreach ($ageGroups as $ag): ?>
            <option value="<?= (int)$ag['age_group_id'] ?>"><?= e($ag['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Дата</label>
        <input type="date" name="date" class="form-input" data-min-today>
      </div>
      <div class="quick-submit">
        <button type="submit" class="btn-teal">Найти</button>
      </div>
    </form>
  </section>

  <section class="section-popular">
    <div class="section-head">
      <h2 class="section-title-teal">Тематические программы</h2>
      <a href="prices.php" class="section-link">Все программы →</a>
    </div>
    <div class="program-grid">
      <?php foreach ($programs as $i => $p): ?>
        <article class="program-card" style="--accent: <?= e(['#1a9ea0','#6a3fd1','#1678c9','#8a5a2b','#d1437b','#0d6b52','#c94b1e','#5a2ca0'][$i % 8]) ?>">
          <div class="program-emoji"><?= e(program_icon($p['title'], $p['category'])) ?></div>
          <div class="program-category"><?= e($p['category']) ?></div>
          <h3><?= e($p['title']) ?></h3>
          <p><?= e(mb_strimwidth((string)$p['description'], 0, 92, '…')) ?></p>
          <div class="program-meta">
            <span class="program-price">от <?= format_price($p['price']) ?></span>
            <span class="program-duration"><?= (int)$p['duration_min'] ?> мин</span>
          </div>
          <a href="order.php?program_id=<?= (int)$p['program_id'] ?>" class="program-link">Заказать →</a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section-popular">
    <div class="section-head">
      <h2 class="section-title-teal">Популярные аниматоры</h2>
      <a href="animators.php" class="section-link">Все аниматоры →</a>
    </div>
    <div class="cards-grid">
      <?php foreach ($animators as $a): ?>
        <article class="animator-card">
          <?php if (!empty($a['photo'])): ?>
            <img class="avatar-placeholder avatar-photo" src="<?= e($a['photo']) ?>" alt="<?= e($a['name']) ?>">
          <?php else: ?>
            <div class="avatar-placeholder avatar-initials" style="background: <?= e(['#1a9ea0','#e87c2a','#6a3fd1','#d1437b'][(int)$a['animator_id'] % 4]) ?>"><?= e(initials($a['name'])) ?></div>
          <?php endif; ?>
          <div class="card-name"><?= e($a['name']) ?></div>
          <div class="card-exp"><?= e($a['specialization']) ?> · опыт <?= (int)$a['experience_years'] ?> лет</div>
          <div class="card-price">от <?= format_price($a['hourly_rate']) ?> / час</div>
          <div class="card-stars"><?= stars((int)round((float)$a['avg_rating'])) ?> <span class="star-num"><?= number_format((float)$a['avg_rating'], 1, '.', '') ?></span></div>
          <a href="animator-profile.php?id=<?= (int)$a['animator_id'] ?>" class="btn-teal btn-block">Выбрать</a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="how-it-works">
    <h2 class="section-title-teal">Как это работает</h2>
    <div class="steps-grid">
      <div class="step"><div class="step-num">1</div><h3>Выберите программу</h3><p>Фильтруйте аниматоров по специализации, возрасту ребёнка и дате.</p></div>
      <div class="step"><div class="step-num">2</div><h3>Забронируйте</h3><p>Укажите дату, время и адрес — система проверит занятость аниматора.</p></div>
      <div class="step"><div class="step-num">3</div><h3>Отдыхайте</h3><p>Аниматор подтвердит заявку, а вы сможете следить за заказом в кабинете.</p></div>
    </div>
  </section>
</main>

<?php require 'includes/footer.php'; ?>
