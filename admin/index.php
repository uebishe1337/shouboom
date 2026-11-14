<?php
/**
 * Административная панель ШоуБум.
 * Вывод всех таблиц, CRUD и просмотр 5 представлений.
 */
require_once __DIR__ . '/../db.php';
require_admin();
verify_csrf();

/* ============================ Метаданные таблиц ============================ */

$TABLES = [
    'users' => [
        'label' => 'Пользователи',
        'pk' => 'user_id',
        'fields' => [
            'name' => ['label' => 'Имя', 'type' => 'text', 'required' => true],
            'email' => ['label' => 'Email', 'type' => 'email', 'required' => true],
            'phone' => ['label' => 'Телефон', 'type' => 'text', 'required' => true],
            'password' => ['label' => 'Пароль', 'type' => 'password', 'required' => true, 'hash' => true, 'help' => 'При редактировании оставьте пустым, чтобы не менять'],
            'role' => ['label' => 'Роль', 'type' => 'select', 'required' => true, 'options' => ['customer' => 'Заказчик', 'animator' => 'Аниматор', 'admin' => 'Администратор']],
            'avatar' => ['label' => 'Аватар (путь)', 'type' => 'text'],
        ],
    ],
    'animators' => [
        'label' => 'Аниматоры',
        'pk' => 'animator_id',
        'fields' => [
            'user_id' => ['label' => 'Пользователь', 'type' => 'fk', 'required' => true,
                'options_sql' => "SELECT user_id AS id, CONCAT(name, ' (', email, ')') AS label FROM users ORDER BY name"],
            'specialization' => ['label' => 'Специализация', 'type' => 'text', 'required' => true],
            'experience_years' => ['label' => 'Опыт, лет', 'type' => 'number', 'required' => true],
            'hourly_rate' => ['label' => 'Ставка, ₽/час', 'type' => 'number', 'step' => '0.01', 'required' => true],
            'rating' => ['label' => 'Рейтинг', 'type' => 'number', 'step' => '0.1'],
            'bio' => ['label' => 'Описание', 'type' => 'textarea'],
            'city' => ['label' => 'Город', 'type' => 'text'],
            'photo' => ['label' => 'Фото (путь)', 'type' => 'text'],
            'is_active' => ['label' => 'Активен', 'type' => 'checkbox'],
        ],
    ],
    'age_groups' => [
        'label' => 'Возрастные группы',
        'pk' => 'age_group_id',
        'fields' => [
            'title' => ['label' => 'Название', 'type' => 'text', 'required' => true],
            'min_age' => ['label' => 'Мин. возраст', 'type' => 'number', 'required' => true],
            'max_age' => ['label' => 'Макс. возраст', 'type' => 'number', 'required' => true],
        ],
    ],
    'animator_age_groups' => [
        'label' => 'Аниматор ↔ возраст',
        'pk' => 'id',
        'fields' => [
            'animator_id' => ['label' => 'Аниматор', 'type' => 'fk', 'required' => true,
                'options_sql' => "SELECT a.animator_id AS id, CONCAT(u.name, ' — ', a.specialization) AS label FROM animators a JOIN users u ON u.user_id=a.user_id ORDER BY u.name"],
            'age_group_id' => ['label' => 'Возрастная группа', 'type' => 'fk', 'required' => true,
                'options_sql' => 'SELECT age_group_id AS id, title AS label FROM age_groups ORDER BY min_age'],
        ],
    ],
    'programs' => [
        'label' => 'Программы',
        'pk' => 'program_id',
        'fields' => [
            'title' => ['label' => 'Название', 'type' => 'text', 'required' => true],
            'category' => ['label' => 'Категория', 'type' => 'text', 'required' => true],
            'description' => ['label' => 'Описание', 'type' => 'textarea'],
            'price' => ['label' => 'Цена, ₽', 'type' => 'number', 'step' => '0.01', 'required' => true],
            'duration_min' => ['label' => 'Длительность, мин', 'type' => 'number', 'required' => true],
            'image' => ['label' => 'Изображение (путь)', 'type' => 'text'],
            'is_active' => ['label' => 'Активна', 'type' => 'checkbox'],
        ],
    ],
    'animator_programs' => [
        'label' => 'Аниматор ↔ программа',
        'pk' => 'id',
        'fields' => [
            'animator_id' => ['label' => 'Аниматор', 'type' => 'fk', 'required' => true,
                'options_sql' => "SELECT a.animator_id AS id, CONCAT(u.name, ' — ', a.specialization) AS label FROM animators a JOIN users u ON u.user_id=a.user_id ORDER BY u.name"],
            'program_id' => ['label' => 'Программа', 'type' => 'fk', 'required' => true,
                'options_sql' => 'SELECT program_id AS id, title AS label FROM programs ORDER BY title'],
            'price' => ['label' => 'Своя цена, ₽', 'type' => 'number', 'step' => '0.01'],
        ],
    ],
    'bookings' => [
        'label' => 'Бронирования',
        'pk' => 'booking_id',
        'fields' => [
            'customer_id' => ['label' => 'Заказчик', 'type' => 'fk',
                'options_sql' => "SELECT user_id AS id, CONCAT(name, ' (', email, ')') AS label FROM users WHERE role='customer' ORDER BY name"],
            'animator_id' => ['label' => 'Аниматор', 'type' => 'fk', 'required' => true,
                'options_sql' => "SELECT a.animator_id AS id, CONCAT(u.name, ' — ', a.specialization) AS label FROM animators a JOIN users u ON u.user_id=a.user_id ORDER BY u.name"],
            'program_id' => ['label' => 'Программа', 'type' => 'fk',
                'options_sql' => 'SELECT program_id AS id, title AS label FROM programs ORDER BY title'],
            'contact_name' => ['label' => 'Имя клиента', 'type' => 'text', 'required' => true],
            'contact_phone' => ['label' => 'Телефон', 'type' => 'text', 'required' => true],
            'contact_email' => ['label' => 'Email', 'type' => 'email'],
            'event_date' => ['label' => 'Дата', 'type' => 'date', 'required' => true],
            'event_time' => ['label' => 'Время', 'type' => 'time', 'required' => true],
            'duration_hours' => ['label' => 'Часов', 'type' => 'number', 'step' => '0.5', 'required' => true],
            'address' => ['label' => 'Адрес', 'type' => 'text', 'required' => true],
            'children_age' => ['label' => 'Возраст детей', 'type' => 'text'],
            'comment' => ['label' => 'Комментарий', 'type' => 'textarea'],
            'status' => ['label' => 'Статус', 'type' => 'select', 'required' => true,
                'options' => ['pending' => 'Ожидает', 'confirmed' => 'Подтверждён', 'completed' => 'Завершён', 'cancelled' => 'Отменён']],
            'total_price' => ['label' => 'Сумма, ₽', 'type' => 'number', 'step' => '0.01'],
        ],
    ],
    'reviews' => [
        'label' => 'Отзывы',
        'pk' => 'review_id',
        'fields' => [
            'booking_id' => ['label' => 'Бронирование', 'type' => 'fk', 'required' => true,
                'options_sql' => "SELECT booking_id AS id, CONCAT('#', booking_id, ' · ', event_date, ' · ', contact_name) AS label FROM bookings ORDER BY booking_id DESC"],
            'customer_id' => ['label' => 'Заказчик', 'type' => 'fk', 'required' => true,
                'options_sql' => 'SELECT user_id AS id, name AS label FROM users ORDER BY name'],
            'animator_id' => ['label' => 'Аниматор', 'type' => 'fk', 'required' => true,
                'options_sql' => 'SELECT a.animator_id AS id, u.name AS label FROM animators a JOIN users u ON u.user_id=a.user_id ORDER BY u.name'],
            'rating' => ['label' => 'Оценка 1–5', 'type' => 'number', 'required' => true],
            'text' => ['label' => 'Текст', 'type' => 'textarea', 'required' => true],
        ],
    ],
    'photos' => [
        'label' => 'Фотографии',
        'pk' => 'photo_id',
        'fields' => [
            'user_id' => ['label' => 'Пользователь', 'type' => 'fk', 'required' => true,
                'options_sql' => 'SELECT user_id AS id, name AS label FROM users ORDER BY name'],
            'booking_id' => ['label' => 'Бронирование', 'type' => 'fk',
                'options_sql' => "SELECT booking_id AS id, CONCAT('#', booking_id, ' · ', event_date, ' · ', contact_name) AS label FROM bookings ORDER BY booking_id DESC"],
            'title' => ['label' => 'Подпись', 'type' => 'text'],
            'file_path' => ['label' => 'Путь к файлу', 'type' => 'text', 'required' => true],
        ],
    ],
    'contact_requests' => [
        'label' => 'Обращения',
        'pk' => 'request_id',
        'fields' => [
            'name' => ['label' => 'Имя', 'type' => 'text', 'required' => true],
            'email' => ['label' => 'Email', 'type' => 'email', 'required' => true],
            'phone' => ['label' => 'Телефон', 'type' => 'text'],
            'message' => ['label' => 'Сообщение', 'type' => 'textarea', 'required' => true],
            'status' => ['label' => 'Статус', 'type' => 'select', 'required' => true,
                'options' => ['new' => 'Новая', 'processed' => 'Обработана']],
        ],
    ],
];

$VIEWS = [
    'view_animators_by_specialization' => [
        'label' => '1. Аниматоры по специализации',
        'desc' => 'Запрос №1: найти аниматоров по специализации (детские праздники, шоу-программы, квесты).',
    ],
    'view_animators_by_age_group' => [
        'label' => '2. Аниматоры по возрастной группе',
        'desc' => 'Запрос №2: найти аниматоров для возрастной группы (3–6, 7–11, 12+).',
    ],
    'view_available_programs' => [
        'label' => '3. Доступные тематические программы',
        'desc' => 'Запрос №3: показать доступные тематические программы и число аниматоров по каждой.',
    ],
    'view_available_animators' => [
        'label' => '4. Доступные аниматоры',
        'desc' => 'Запрос №4: показать активных аниматоров и их загрузку на 7 дней вперёд.',
    ],
    'view_weekly_bookings' => [
        'label' => '5. Бронирования на текущую неделю',
        'desc' => 'Запрос №5: показать бронирования аниматоров на текущую неделю.',
    ],
];

/* ============================ Вспомогательные функции ============================ */

function fk_options(PDO $pdo, array $field): array
{
    static $cache = [];
    $sql = $field['options_sql'] ?? '';
    if ($sql === '') {
        return [];
    }
    if (!array_key_exists($sql, $cache)) {
        $map = [];
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $map[(string)$row['id']] = $row['label'];
        }
        $cache[$sql] = $map;
    }
    return $cache[$sql];
}

function admin_value(PDO $pdo, string $fieldName, array $field, $value): string
{
    $type = $field['type'] ?? 'text';
    if ($value === null || $value === '') {
        return '<span class="muted">—</span>';
    }
    if ($type === 'password') {
        return '<span class="muted">••••••</span>';
    }
    if ($type === 'checkbox') {
        return $value ? '<span class="badge-yes">Да</span>' : '<span class="badge-no">Нет</span>';
    }
    if ($type === 'fk') {
        $options = fk_options($pdo, $field);
        return e($options[(string)$value] ?? ('#' . $value));
    }
    if ($type === 'select' && isset($field['options'][$value])) {
        return e($field['options'][$value]);
    }
    return e($value);
}

/* ============================ Обработка POST ============================ */

$page = $_GET['page'] ?? 'dashboard';
$table = $_GET['table'] ?? '';
$action = $_GET['action'] ?? '';

if (is_post()) {
    $postAction = $_POST['post_action'] ?? '';
    $postTable = $_POST['table'] ?? '';
    $meta = $TABLES[$postTable] ?? null;

    if ($postAction === 'save' && $meta) {
        $id = (int)($_POST['id'] ?? 0);
        $values = [];
        $errors = [];
        foreach ($meta['fields'] as $name => $field) {
            $type = $field['type'] ?? 'text';

            if ($type === 'checkbox') {
                $values[$name] = isset($_POST[$name]) ? 1 : 0;
                continue;
            }

            $value = trim((string)($_POST[$name] ?? ''));

            if ($name === 'password') {
                if ($value === '') {
                    if ($id === 0 && !empty($field['required'])) {
                        $errors[] = 'Пароль обязателен.';
                    }
                    continue;
                }
                $values[$name] = password_hash($value, PASSWORD_BCRYPT);
                continue;
            }

            if ($value === '' && !empty($field['required'])) {
                $errors[] = 'Поле «' . $field['label'] . '» обязательно.';
            }

            if ($value === '' && in_array($type, ['fk', 'date', 'time'], true)) {
                $value = null;
            }
            $values[$name] = $value;
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            try {
                if ($id > 0) {
                    $sets = [];
                    $params = [];
                    foreach ($values as $name => $value) {
                        $sets[] = '`' . $name . '` = ?';
                        $params[] = $value;
                    }
                    $params[] = $id;
                    $stmt = $pdo->prepare('UPDATE `' . $postTable . '` SET ' . implode(', ', $sets) . ' WHERE `' . $meta['pk'] . '` = ?');
                    $stmt->execute($params);
                    flash('success', 'Запись обновлена.');
                } else {
                    $columns = array_keys($values);
                    $stmt = $pdo->prepare(
                        'INSERT INTO `' . $postTable . '` (`' . implode('`, `', $columns) . '`) VALUES (' .
                        implode(', ', array_fill(0, count($columns), '?')) . ')'
                    );
                    $stmt->execute(array_values($values));
                    flash('success', 'Запись добавлена (ID ' . $pdo->lastInsertId() . ').');
                }
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (strpos($msg, 'Duplicate entry') !== false) {
                    $msg = 'Такая запись уже существует (проверьте уникальные поля).';
                } elseif (strpos($msg, 'foreign key') !== false) {
                    $msg = 'Нарушена связь с другой таблицей. Проверьте выбранные значения.';
                }
                flash('error', 'Ошибка сохранения: ' . $msg);
            }
        }
        redirect('index.php?page=table&table=' . urlencode($postTable));
    }

    if ($postAction === 'delete' && $meta) {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM `' . $postTable . '` WHERE `' . $meta['pk'] . '` = ?');
            $stmt->execute([$id]);
            flash('success', 'Запись удалена.');
        } catch (PDOException $e) {
            flash('error', 'Нельзя удалить запись: на неё ссылаются другие таблицы. Сначала удалите связанные записи.');
        }
        redirect('index.php?page=table&table=' . urlencode($postTable));
    }
}

$adminUser = current_user();
$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Админ-панель — <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="../style.css">
<link rel="icon" href="../logo.png" type="image/png">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a href="../index.php" class="admin-logo"><img src="../logo.png" alt=""><span><?= e(SITE_NAME) ?></span></a>
    <div class="admin-caption">Админ-панель</div>
    <nav class="admin-nav">
      <a href="index.php?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">📊 Сводка</a>
      <div class="admin-nav-title">Таблицы</div>
      <?php foreach ($TABLES as $t => $m): ?>
        <a href="index.php?page=table&table=<?= e($t) ?>" class="<?= $page === 'table' && $table === $t ? 'active' : '' ?>"><?= e($m['label']) ?></a>
      <?php endforeach; ?>
      <div class="admin-nav-title">Представления</div>
      <a href="index.php?page=views" class="<?= $page === 'views' ? 'active' : '' ?>">5 запросов (VIEW)</a>
    </nav>
    <div class="admin-sidebar-footer">
      <a href="../index.php">← На сайт</a>
      <a href="../logout.php">Выйти</a>
    </div>
  </aside>

  <main class="admin-main">
    <header class="admin-topbar">
      <div>
        <b><?= e($adminUser['name']) ?></b>
        <span class="muted">· администратор</span>
      </div>
      <a href="../index.php" class="btn-ghost btn-small">Открыть сайт</a>
    </header>

    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <?php if ($page === 'dashboard'): ?>
      <?php
      $stats = [
          'Пользователи' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
          'Аниматоры' => (int)$pdo->query('SELECT COUNT(*) FROM animators')->fetchColumn(),
          'Бронирования' => (int)$pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
          'Отзывы' => (int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
          'Фотографии' => (int)$pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn(),
          'Обращения' => (int)$pdo->query('SELECT COUNT(*) FROM contact_requests')->fetchColumn(),
      ];
      $latest = $pdo->query('SELECT b.*, u.name AS animator_name FROM bookings b JOIN animators a ON a.animator_id=b.animator_id JOIN users u ON u.user_id=a.user_id ORDER BY b.created_at DESC LIMIT 8')->fetchAll();
      ?>
      <h1 class="admin-title">Сводка</h1>
      <div class="stats-grid">
        <?php foreach ($stats as $label => $value): ?>
          <div class="stat-card"><b><?= $value ?></b><span><?= e($label) ?></span></div>
        <?php endforeach; ?>
      </div>

      <h2 class="admin-subtitle">Последние бронирования</h2>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>№</th><th>Дата</th><th>Аниматор</th><th>Клиент</th><th>Статус</th><th>Сумма</th></tr></thead>
          <tbody>
            <?php foreach ($latest as $b): ?>
              <tr>
                <td><?= (int)$b['booking_id'] ?></td>
                <td><?= e(format_date($b['event_date'])) ?> <?= e(format_time($b['event_time'])) ?></td>
                <td><?= e($b['animator_name']) ?></td>
                <td><?= e($b['contact_name']) ?></td>
                <td><span class="status-badge <?= e(status_class($b['status'])) ?>"><?= e(status_label($b['status'])) ?></span></td>
                <td><?= format_price($b['total_price']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($page === 'table'): ?>
      <?php
      $meta = $TABLES[$table] ?? null;
      if (!$meta) {
          echo '<div class="alert alert-error">Неизвестная таблица.</div>';
          $meta = null;
      }
      ?>
      <?php if ($meta): ?>
        <?php
        $isForm = in_array($action, ['new', 'edit'], true);
        $row = [];
        if ($action === 'edit') {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `' . $meta['pk'] . '` = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch() ?: [];
        }
        ?>
        <?php if ($isForm): ?>
          <div class="admin-page-head">
            <div>
              <h1 class="admin-title"><?= $action === 'edit' ? 'Редактирование' : 'Добавление' ?>: <?= e($meta['label']) ?></h1>
              <div class="muted">Таблица <code><?= e($table) ?></code></div>
            </div>
            <a href="index.php?page=table&table=<?= e($table) ?>" class="btn-ghost btn-small">← К списку</a>
          </div>

          <form method="POST" action="index.php?page=table&table=<?= e($table) ?>" class="admin-form">
            <?php csrf_field(); ?>
            <input type="hidden" name="post_action" value="save">
            <input type="hidden" name="table" value="<?= e($table) ?>">
            <input type="hidden" name="id" value="<?= (int)($row[$meta['pk']] ?? 0) ?>">

            <?php foreach ($meta['fields'] as $name => $field): ?>
              <?php
              $type = $field['type'] ?? 'text';
              $value = $row[$name] ?? '';
              $required = !empty($field['required']) && !($name === 'password' && $action === 'edit');
              ?>
              <div class="form-group">
                <label><?= e($field['label']) ?><?= $required ? ' <span class="req">*</span>' : '' ?></label>
                <?php if ($type === 'textarea'): ?>
                  <textarea class="form-input" name="<?= e($name) ?>" rows="4" <?= $required ? 'required' : '' ?>><?= e($value) ?></textarea>
                <?php elseif ($type === 'select'): ?>
                  <select class="form-input" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
                    <?php if (empty($field['required'])): ?><option value="">—</option><?php endif; ?>
                    <?php foreach ($field['options'] as $optValue => $optLabel): ?>
                      <option value="<?= e($optValue) ?>" <?= (string)$value === (string)$optValue ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($type === 'fk'): ?>
                  <select class="form-input" name="<?= e($name) ?>" <?= $required ? 'required' : '' ?>>
                    <option value="">— не выбрано —</option>
                    <?php foreach (fk_options($pdo, $field) as $optValue => $optLabel): ?>
                      <option value="<?= e($optValue) ?>" <?= (string)$value === (string)$optValue ? 'selected' : '' ?>><?= e($optLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($type === 'checkbox'): ?>
                  <label class="check-row" style="margin-top:4px">
                    <input type="checkbox" name="<?= e($name) ?>" value="1" <?= !empty($value) ? 'checked' : '' ?>>
                    <span>Включено</span>
                  </label>
                <?php else: ?>
                  <input class="form-input" type="<?= e($type) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"
                    <?= $type === 'number' && !empty($field['step']) ? 'step="' . e($field['step']) . '"' : '' ?>
                    <?= $required ? 'required' : '' ?>>
                <?php endif; ?>
                <?php if (!empty($field['help'])): ?><span class="field-help"><?= e($field['help']) ?></span><?php endif; ?>
              </div>
            <?php endforeach; ?>

            <div style="display:flex;gap:10px;flex-wrap:wrap">
              <button type="submit" class="form-btn" style="max-width:280px">Сохранить</button>
              <a href="index.php?page=table&table=<?= e($table) ?>" class="btn-ghost" style="align-self:center">Отмена</a>
            </div>
          </form>

        <?php else: ?>
          <div class="admin-page-head">
            <div>
              <h1 class="admin-title"><?= e($meta['label']) ?></h1>
              <div class="muted">Таблица <code><?= e($table) ?></code> · записей: <?= (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() ?></div>
            </div>
            <a href="index.php?page=table&table=<?= e($table) ?>&action=new" class="btn-orange btn-small">+ Добавить</a>
          </div>

          <?php
          $rows = $pdo->query('SELECT * FROM `' . $table . '` ORDER BY `' . $meta['pk'] . '` DESC LIMIT 200')->fetchAll();
          ?>
          <div class="table-wrap">
            <table class="data-table admin-table">
              <thead>
                <tr>
                  <th><?= e($meta['pk']) ?></th>
                  <?php foreach ($meta['fields'] as $name => $field): ?><th><?= e($field['label']) ?></th><?php endforeach; ?>
                  <th>Действия</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td><b><?= e($r[$meta['pk']]) ?></b></td>
                    <?php foreach ($meta['fields'] as $name => $field): ?>
                      <td><?= admin_value($pdo, $name, $field, $r[$name] ?? null) ?></td>
                    <?php endforeach; ?>
                    <td class="admin-actions">
                      <a class="btn-teal btn-small" href="index.php?page=table&table=<?= e($table) ?>&action=edit&id=<?= (int)$r[$meta['pk']] ?>">Изменить</a>
                      <form method="POST" action="index.php?page=table&table=<?= e($table) ?>" style="display:inline">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="post_action" value="delete">
                        <input type="hidden" name="table" value="<?= e($table) ?>">
                        <input type="hidden" name="id" value="<?= (int)$r[$meta['pk']] ?>">
                        <button class="btn-danger btn-small" type="submit" data-confirm="Удалить запись #<?= (int)$r[$meta['pk']] ?>?">Удалить</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="<?= count($meta['fields']) + 2 ?>" class="muted">Записей нет.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    <?php elseif ($page === 'views'): ?>
      <h1 class="admin-title">Представления (5 запросов)</h1>
      <p class="muted">Только просмотр. Представления созданы в базе данных и соответствуют запросам из технического задания.</p>

      <?php foreach ($VIEWS as $viewName => $view): ?>
        <h2 class="admin-subtitle"><?= e($view['label']) ?></h2>
        <p class="muted"><?= e($view['desc']) ?></p>
        <?php
        try {
            $rows = $pdo->query('SELECT * FROM `' . $viewName . '` LIMIT 100')->fetchAll();
        } catch (Throwable $e) {
            echo '<div class="alert alert-error">' . e($e->getMessage()) . '</div>';
            continue;
        }
        ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <?php if ($rows): foreach (array_keys($rows[0]) as $col): ?><th><?= e($col) ?></th><?php endforeach; endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr><?php foreach ($r as $val): ?><td><?= e((string)$val) ?></td><?php endforeach; ?></tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?><tr><td class="muted">Нет данных.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
</div>
<script src="../assets/app.js"></script>
</body>
</html>
