<?php
/**
 * Установщик: создаёт базу, таблицы, представления и демо-данные.
 * Запускать один раз: http://localhost/install.php
 */
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(16));
}

$messages = [];
$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['install_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $error = 'Сессия истекла. Обновите страницу.';
    } else {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $sql = file_get_contents(__DIR__ . '/database.sql');
            if ($sql === false) {
                throw new RuntimeException('Не удалось прочитать database.sql');
            }

            // Убираем построчные комментарии и разбиваем на отдельные запросы
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)));

            $pdo->exec('SET NAMES utf8mb4');
            foreach ($statements as $i => $statement) {
                if ($statement === '') {
                    continue;
                }
                $pdo->exec($statement);
                $messages[] = 'Выполнено: ' . mb_substr(preg_replace('/\s+/', ' ', $statement), 0, 90) . '…';
            }

            $done = true;
        } catch (Throwable $e) {
            $error = 'Ошибка установки: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Установка ШоуБум</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="navbar"><div class="navbar-inner"><a href="index.php" class="logo"><img class="logo-img" src="logo.png" alt=""><span class="logo-text">ШоуБум</span></a></div></header>
<div class="ticker"></div>
<main class="page-wrap"><div class="page-inner" style="max-width:760px">
  <div class="auth-wrap" style="max-width:760px">
    <h1 class="auth-title">Установка базы данных</h1>
    <p class="auth-sub">Создание базы «<?= htmlspecialchars(DB_NAME) ?>», таблиц, 5 представлений и демонстрационных данных</p>

    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($done): ?>
      <div class="alert alert-success">База данных успешно установлена. Можно переходить на сайт.</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0">
        <a href="index.php" class="btn-orange">Открыть сайт</a>
        <a href="admin/index.php" class="btn-teal">Админ-панель</a>
        <a href="login.php" class="btn-teal">Войти</a>
      </div>
      <div class="install-log"><?php foreach ($messages as $m): ?><div><?= htmlspecialchars($m) ?></div><?php endforeach; ?></div>
    <?php else: ?>
      <div class="install-steps">
        <p><b>Что будет сделано:</b></p>
        <ol>
          <li>Создана база <code><?= htmlspecialchars(DB_NAME) ?></code>.</li>
          <li>Созданы таблицы: users, animators, age_groups, animator_age_groups, programs, animator_programs, bookings, reviews, photos, contact_requests.</li>
          <li>Созданы 5 представлений по запросам из технического задания.</li>
          <li>Добавлены демонстрационные данные (8 пользователей, 4 аниматора, 8 программ, 9 бронирований, 4 отзыва).</li>
        </ol>
        <p>Пароль демо-пользователей: <code>123456</code>, администратора — <code>admin123</code>.</p>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['install_csrf']) ?>">
        <button class="form-btn" type="submit">Установить базу данных</button>
      </form>
    <?php endif; ?>
  </div>
</div></main>
<div class="ticker"></div>
<footer class="footer"><p>© <?= date('Y') ?> ШоуБум</p></footer>
</body>
</html>
