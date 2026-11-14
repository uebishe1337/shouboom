<?php
/**
 * Подключение к базе данных и запуск сессии.
 * Файл подключается на всех страницах (require 'db.php').
 */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    $installed = is_file(__DIR__ . '/database.sql');
    ?>
    <!DOCTYPE html>
    <html lang="ru"><head><meta charset="utf-8"><title>База данных не найдена</title>
    <style>body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;padding:40px;color:#333}
    .box{max-width:640px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;border:1px solid #e0e0e0}
    a{color:#1a9ea0;font-weight:700}code{background:#f3f3f3;padding:2px 6px;border-radius:4px}
    h1{color:#1a9ea0;font-size:24px;margin-bottom:14px}</style></head><body><div class="box">
    <h1>База данных ещё не создана</h1>
    <p>Не удалось подключиться к базе <b><?= htmlspecialchars(DB_NAME) ?></b>.</p>
    <p>Запустите установщик: <a href="install.php">install.php</a> — он создаст таблицы, представления и демонстрационные данные.</p>
    <?php if ($installed): ?><p>Либо импортируйте файл <code>database.sql</code> через phpMyAdmin.</p><?php endif; ?>
    <p style="margin-top:18px;font-size:13px;color:#888">Техническая информация: <?= htmlspecialchars($e->getMessage()) ?></p>
    </div></body></html>
    <?php
    exit;
}

require_once __DIR__ . '/functions.php';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
