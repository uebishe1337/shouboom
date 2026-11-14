<?php
require 'db.php';

$specializations = get_specializations($pdo);
$ageGroups = get_age_groups($pdo);
$programs = get_programs($pdo);

$f = [
    'specialization' => trim((string)($_GET['specialization'] ?? '')),
    'age_group_id'   => (int)($_GET['age_group_id'] ?? 0),
    'program_id'     => (int)($_GET['program_id'] ?? 0),
    'date'           => trim((string)($_GET['date'] ?? '')),
    'time'           => trim((string)($_GET['time'] ?? '')),
    'hours'          => (float)($_GET['hours'] ?? 1),
    'max_price'      => trim((string)($_GET['max_price'] ?? '')),
    'q'              => trim((string)($_GET['q'] ?? '')),
];

if ($f['date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date'])) {
    $f['date'] = '';
}
if ($f['time'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $f['time'])) {
    $f['time'] = '';
}
if ($f['hours'] <= 0) {
    $f['hours'] = 1;
}

$animators = available_animators($pdo, $f);
$hasFilters = $f['specialization'] !== '' || $f['age_group_id'] || $f['program_id'] || $f['date'] !== '' || $f['time'] !== '' || $f['max_price'] !== '' || $f['q'] !== '';

$pageTitle = 'Аниматоры';
$active = 'animators';
require 'includes/header.php';
?>
<div class="page-wrap">
  <div class="page-inner">
    <div class="page-heading">
      <div>
        <h1 class="section-title-center" style="text-align:left;margin-bottom:4px">Каталог аниматоров</h1>
        <p class="page-sub">Найдено: <b><?= count($animators) ?></b>. Фильтры работают по специализации, возрасту, программе и свободному времени.</p>
      </div>
      <a href="animators.php" class="btn-ghost btn-small">Сбросить</a>
    </div>

    <div class="animators-layout">
      <aside class="filters-panel">
        <div class="filters-title">Фильтры</div>
        <form method="GET" action="animators.php">
          <div class="filter-group">
            <div class="filter-label">Поиск по имени</div>
            <input class="form-input" type="text" name="q" value="<?= e($f['q']) ?>" placeholder="Например, Алексей">
          </div>

          <div class="filter-group">
            <div class="filter-label">Специализация</div>
            <select class="form-input" name="specialization">
              <option value="">Любая</option>
              <?php foreach ($specializations as $spec): ?>
                <option value="<?= e($spec) ?>" <?= $f['specialization'] === $spec ? 'selected' : '' ?>><?= e($spec) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-group">
            <div class="filter-label">Возрастная группа</div>
            <select class="form-input" name="age_group_id">
              <option value="">Любая</option>
              <?php foreach ($ageGroups as $ag): ?>
                <option value="<?= (int)$ag['age_group_id'] ?>" <?= $f['age_group_id'] === (int)$ag['age_group_id'] ? 'selected' : '' ?>><?= e($ag['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-group">
            <div class="filter-label">Тематическая программа</div>
            <select class="form-input" name="program_id">
              <option value="">Любая</option>
              <?php foreach ($programs as $p): ?>
                <option value="<?= (int)$p['program_id'] ?>" <?= $f['program_id'] === (int)$p['program_id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-group">
            <div class="filter-label">Свободен на дату</div>
            <input class="form-input" type="date" name="date" value="<?= e($f['date']) ?>" min="<?= date('Y-m-d') ?>">
          </div>

          <div class="form-row">
            <div class="filter-group">
              <div class="filter-label">Время</div>
              <input class="form-input" type="time" name="time" value="<?= e($f['time']) ?>">
            </div>
            <div class="filter-group">
              <div class="filter-label">Часов</div>
              <select class="form-input" name="hours">
                <?php foreach ([1, 1.5, 2, 3] as $h): ?>
                  <option value="<?= $h ?>" <?= abs($f['hours'] - $h) < 0.01 ? 'selected' : '' ?>><?= $h ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="filter-group">
            <div class="filter-label">Цена до, ₽/час</div>
            <input class="form-input" type="number" name="max_price" min="0" step="100" value="<?= e($f['max_price']) ?>" placeholder="Например, 3000">
          </div>

          <button type="submit" class="btn-orange" style="width:100%;font-size:14px;padding:10px 0">Применить фильтры</button>
        </form>
      </aside>

      <div>
        <?php if ($f['date'] !== '' && $f['time'] !== ''): ?>
          <div class="alert alert-info">Показаны аниматоры, свободные <?= e(format_date($f['date'])) ?> в <?= e($f['time']) ?> (<?= e($f['hours']) ?> ч).</div>
        <?php elseif ($f['date'] !== ''): ?>
          <div class="alert alert-info">Выбрана дата <?= e(format_date($f['date'])) ?>. Укажите время, чтобы проверить занятость в конкретный час.</div>
        <?php endif; ?>

        <?php if (!$animators): ?>
          <div class="empty-state">
            <div class="empty-emoji">🔍</div>
            <h3>Никого не нашли</h3>
            <p>Попробуйте изменить фильтры или выбрать другую дату.</p>
            <a href="animators.php" class="btn-teal">Сбросить фильтры</a>
          </div>
        <?php else: ?>
          <div class="animators-list">
            <?php foreach ($animators as $a): ?>
              <article class="animator-card-list">
                <?php if (!empty($a['photo'])): ?>
                  <img class="avatar-placeholder avatar-photo" src="<?= e($a['photo']) ?>" alt="<?= e($a['name']) ?>">
                <?php else: ?>
                  <div class="avatar-placeholder avatar-initials" style="background: <?= e(['#1a9ea0','#e87c2a','#6a3fd1','#d1437b'][(int)$a['animator_id'] % 4]) ?>"><?= e(initials($a['name'])) ?></div>
                <?php endif; ?>
                <div class="card-name"><?= e($a['name']) ?></div>
                <div class="card-exp"><?= e($a['specialization']) ?></div>
                <div class="card-exp">Опыт: <?= (int)$a['experience_years'] ?> лет · <?= e($a['city']) ?></div>
                <div class="card-price"><?= format_price($a['hourly_rate']) ?> / час</div>
                <div class="card-stars"><?= stars((int)round((float)$a['avg_rating'])) ?> <span class="star-num"><?= number_format((float)$a['avg_rating'], 1, '.', '') ?></span></div>
                <?php if (!empty($a['age_groups'])): ?><div class="card-tags"><?= e($a['age_groups']) ?></div><?php endif; ?>
                <?php if (!empty($a['program_titles'])): ?><div class="card-programs"><?= e(mb_strimwidth($a['program_titles'], 0, 70, '…')) ?></div><?php endif; ?>
                <?php if ($f['date'] !== '' && $f['time'] !== ''): ?>
                  <div class="free-badge">Свободен</div>
                <?php endif; ?>
                <a href="animator-profile.php?id=<?= (int)$a['animator_id'] ?>" class="btn-teal btn-block" style="margin-top:8px;width:100%">Подробнее</a>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require 'includes/footer.php'; ?>
