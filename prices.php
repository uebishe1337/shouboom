<?php
require 'db.php';

$programs = get_programs($pdo);
$minPrice = $pdo->query('SELECT MIN(hourly_rate) FROM animators WHERE is_active=1')->fetchColumn() ?: 2500;
$maxPrice = $pdo->query('SELECT MAX(price) FROM programs WHERE is_active=1')->fetchColumn() ?: 5500;
$grouped = [];
foreach ($programs as $p) {
    $grouped[$p['category']][] = $p;
}

$pageTitle = 'Цены';
$active = 'prices';
require 'includes/header.php';
?>
<div class="page-wrap">
  <div class="page-inner">
    <h1 class="section-title-center">Наши цены</h1>
    <p class="section-sub-center">Прозрачные тарифы без скрытых доплат. Итоговая стоимость рассчитывается при оформлении заказа.</p>

    <div class="prices-grid">
      <div class="price-card">
        <div class="price-title">Стандарт</div>
        <div class="price-amount">от <?= format_price($minPrice) ?></div>
        <div class="price-per">за час работы аниматора</div>
        <div class="price-divider"></div>
        <ul class="price-features">
          <li>1 аниматор</li>
          <li>Игры, конкурсы, эстафеты</li>
          <li>Музыкальное сопровождение</li>
          <li>Работа по адресу заказчика</li>
          <li class="muted">Шоу-программа и квест — отдельно</li>
        </ul>
        <a href="animators.php" class="btn-orange" style="width:100%;text-align:center;display:block">Выбрать аниматора</a>
      </div>

      <div class="price-card vip">
        <div class="price-hit-badge">Хит</div>
        <div class="price-title">ВИП</div>
        <div class="price-amount">до <?= format_price($maxPrice) ?></div>
        <div class="price-per">за тематическую программу</div>
        <div class="price-divider"></div>
        <ul class="price-features">
          <li>Аниматор + шоу-программа</li>
          <li>Химическое шоу / мыльные пузыри</li>
          <li>Квест или ростовые куклы</li>
          <li>Фото на память</li>
          <li>Индивидуальный сценарий</li>
        </ul>
        <a href="order.php" class="btn-orange" style="width:100%;text-align:center;display:block">Оформить заказ</a>
      </div>
    </div>

    <div class="section-head" style="margin-top:34px">
      <h2 class="section-title-teal">Стоимость программ</h2>
      <span class="muted">Цены указаны за программу, длительность — в карточке</span>
    </div>

    <?php foreach ($grouped as $category => $items): ?>
      <h3 class="price-category"><?= e($category) ?></h3>
      <div class="table-wrap">
        <table class="data-table price-table">
          <thead><tr><th>Программа</th><th>Описание</th><th>Длительность</th><th>Цена</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($items as $p): ?>
              <tr>
                <td><b><?= e(program_icon($p['title'], $p['category'])) ?> <?= e($p['title']) ?></b></td>
                <td class="muted"><?= e(mb_strimwidth((string)$p['description'], 0, 90, '…')) ?></td>
                <td><?= (int)$p['duration_min'] ?> мин</td>
                <td class="price-cell"><?= format_price($p['price']) ?></td>
                <td><a class="btn-teal btn-small" href="order.php?program_id=<?= (int)$p['program_id'] ?>">Заказать</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>

    <div class="price-note">
      <b>Что входит в стоимость:</b> работа аниматора, реквизит, музыкальное сопровождение и подготовка программы.
      Транспортные расходы за пределы МКАД и дополнительные часы оплачиваются отдельно.
    </div>
  </div>
</div>
<?php require 'includes/footer.php'; ?>
