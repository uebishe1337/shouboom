<?php
/** Общая шапка сайта. Ожидает переменные $pageTitle и $active (необязательно). */
if (!isset($pdo)) {
    require_once __DIR__ . '/../db.php';
}
$currentUser = current_user();
$pageTitle = $pageTitle ?? SITE_NAME;
$active = $active ?? '';
$navItems = [
    'index.php'     => ['Главная', 'index'],
    'animators.php' => ['Аниматоры', 'animators'],
    'prices.php'    => ['Цены', 'prices'],
    'order.php'     => ['Заказ', 'order'],
    'reviews.php'   => ['Отзывы', 'reviews'],
    'contacts.php'  => ['Контакты', 'contacts'],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="style.css">
<link rel="icon" href="logo.png" type="image/png">
</head>
<body>

<header class="navbar">
  <div class="navbar-inner">
    <a href="index.php" class="logo">
      <img class="logo-img" src="logo.png" alt="<?= e(SITE_NAME) ?>">
      <span class="logo-text"><?= e(SITE_NAME) ?></span>
    </a>
    <nav class="nav-links">
      <?php foreach ($navItems as $href => $item): ?>
        <a href="<?= e($href) ?>"<?= $active === $item[1] ? ' class="active"' : '' ?>><?= e($item[0]) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-auth">
      <?php if ($currentUser): ?>
        <?php if (is_admin()): ?>
          <a href="admin/index.php" class="btn-admin">Админ-панель</a>
        <?php endif; ?>
        <a href="cabinet.php" class="btn-username"><?= e($currentUser['name']) ?></a>
        <a href="logout.php" class="btn-login">Выйти</a>
      <?php else: ?>
        <a href="login.php" class="btn-login">Вход</a>
        <a href="register.php" class="btn-register">Регистрация</a>
      <?php endif; ?>
    </div>
    <button class="burger" id="burgerBtn" type="button" aria-label="Меню">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<nav class="mobile-nav" id="mobileNav">
  <?php foreach ($navItems as $href => $item): ?>
    <a href="<?= e($href) ?>"<?= $active === $item[1] ? ' class="active"' : '' ?>><?= e($item[0]) ?></a>
  <?php endforeach; ?>
  <div class="mobile-nav-auth">
    <?php if ($currentUser): ?>
      <?php if (is_admin()): ?><a href="admin/index.php" class="btn-admin" style="font-size:13px;">Админ-панель</a><?php endif; ?>
      <a href="cabinet.php" class="btn-username" style="font-size:13px;"><?= e($currentUser['name']) ?></a>
      <a href="logout.php" class="btn-login" style="font-size:13px;">Выйти</a>
    <?php else: ?>
      <a href="login.php" class="btn-login" style="font-size:13px;">Вход</a>
      <a href="register.php" class="btn-register" style="font-size:13px;">Регистрация</a>
    <?php endif; ?>
  </div>
</nav>

<div class="ticker" aria-hidden="true"></div>

<?php $flashes = take_flashes(); ?>
<?php if ($flashes): ?>
  <div class="flash-stack">
    <?php foreach ($flashes as $item): ?>
      <div class="alert alert-<?= e($item['type']) ?>"><?= e($item['message']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
