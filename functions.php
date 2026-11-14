<?php
/**
 * Общие вспомогательные функции приложения.
 */

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'user_id' => (int)$_SESSION['user_id'],
        'name'    => $_SESSION['user_name'] ?? '',
        'role'    => $_SESSION['user_role'] ?? 'customer',
        'email'   => $_SESSION['user_email'] ?? '',
        'phone'   => $_SESSION['user_phone'] ?? '',
    ];
}

function login_user(array $user): void
{
    $_SESSION['user_id']    = (int)$user['user_id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_phone'] = $user['phone'] ?? '';
    session_regenerate_id(true);
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function is_animator(): bool
{
    return (current_user()['role'] ?? '') === 'animator';
}

function require_login(string $next = ''): void
{
    if (!current_user()) {
        $next = $next !== '' ? $next : ($_SERVER['REQUEST_URI'] ?? 'cabinet.php');
        redirect('login.php?next=' . urlencode($next));
    }
}

function require_admin(): void
{
    if (!current_user()) {
        redirect('../login.php?next=' . urlencode('admin/index.php'));
    }
    if (!is_admin()) {
        http_response_code(403);
        die('Доступ разрешён только администратору. <a href="../index.php">На главную</a>');
    }
}

function require_animator(): void
{
    require_login('cabinet.php');
    if (!is_animator()) {
        redirect('cabinet.php');
    }
}

function safe_next(string $next, string $fallback = 'cabinet.php'): string
{
    if ($next === '' || preg_match('~^(https?:)?//~i', $next)) {
        return $fallback;
    }
    return $next;
}

/* ---------- Сообщения ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    return $_SESSION['csrf'] ?? '';
}

function csrf_field(): void
{
    echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!is_post()) {
        return;
    }
    $token = $_POST['csrf'] ?? '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(419);
        die('Сессия истекла. Обновите страницу и повторите действие. <a href="index.php">На главную</a>');
    }
}

/* ---------- Форматирование ---------- */

function format_price($value): string
{
    return number_format((float)$value, 0, '.', ' ') . ' ₽';
}

function format_date($date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime((string)$date);
    return $ts ? date('d.m.Y', $ts) : (string)$date;
}

function format_time($time): string
{
    return $time ? substr((string)$time, 0, 5) : '—';
}

function format_datetime($date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime((string)$date);
    return $ts ? date('d.m.Y H:i', $ts) : (string)$date;
}

function status_label(string $status): string
{
    return [
        'pending'   => 'Ожидает подтверждения',
        'confirmed' => 'Подтверждён',
        'completed' => 'Завершён',
        'cancelled' => 'Отменён',
        'new'       => 'Новая',
        'processed' => 'Обработана',
    ][$status] ?? $status;
}

function status_class(string $status): string
{
    return [
        'pending'   => 'status-pending',
        'confirmed' => 'status-confirmed',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled',
        'new'       => 'status-pending',
        'processed' => 'status-confirmed',
    ][$status] ?? 'status-pending';
}

function role_label(string $role): string
{
    return [
        'customer' => 'Заказчик',
        'animator' => 'Аниматор',
        'admin'    => 'Администратор',
    ][$role] ?? $role;
}

function stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name));
    $first = mb_substr($parts[0] ?? '?', 0, 1);
    $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';
    return mb_strtoupper($first . $second);
}

function program_icon(string $title, string $category = ''): string
{
    $text = mb_strtolower($title . ' ' . $category);
    if (mb_strpos($text, 'хим') !== false) return '🧪';
    if (mb_strpos($text, 'пузыр') !== false) return '🫧';
    if (mb_strpos($text, 'квест') !== false) return '🗺️';
    if (mb_strpos($text, 'кукл') !== false) return '🧸';
    if (mb_strpos($text, 'ёлк') !== false || mb_strpos($text, 'елк') !== false) return '🎄';
    if (mb_strpos($text, 'аквагрим') !== false) return '🎨';
    if (mb_strpos($text, 'дискотек') !== false) return '💃';
    if (mb_strpos($text, 'животн') !== false) return '🐾';
    return '🎉';
}

function price_basis(array $animator, ?array $program): float
{
    return $program ? (float)$program['price'] : (float)$animator['hourly_rate'];
}

/* ---------- Загрузка изображений ---------- */

function upload_image(array $file, string $subdir = 'photos'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [false, 'Файл не выбран.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [false, 'Не удалось загрузить файл (код ' . (int)$file['error'] . ').'];
    }
    if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
        return [false, 'Файл больше 4 МБ.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        return [false, 'Допустимы только изображения JPG, PNG, WEBP или GIF.'];
    }

    $dir = UPLOAD_DIR . DIRECTORY_SEPARATOR . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return [false, 'Не удалось создать папку для загрузок.'];
    }

    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return [false, 'Не удалось сохранить файл.'];
    }

    return [true, UPLOAD_URL . '/' . $subdir . '/' . $name];
}

function delete_upload(string $path): void
{
    $path = str_replace('\\', '/', $path);
    if (strpos($path, UPLOAD_URL . '/') !== 0) {
        return;
    }
    $full = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $real = realpath($full);
    $base = realpath(UPLOAD_DIR);
    if ($real && $base && strpos($real, $base) === 0 && is_file($real)) {
        @unlink($real);
    }
}

/* ---------- Данные ---------- */

function get_animator(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT a.*, u.name, u.email, u.phone, u.created_at AS user_created_at,
                (SELECT COUNT(*) FROM reviews r WHERE r.animator_id = a.animator_id) AS reviews_count,
                (SELECT COALESCE(AVG(r.rating), a.rating) FROM reviews r WHERE r.animator_id = a.animator_id) AS avg_rating
         FROM animators a
         JOIN users u ON u.user_id = a.user_id
         WHERE a.animator_id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $animator = $stmt->fetch();
    if (!$animator) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ag.* FROM age_groups ag
         JOIN animator_age_groups aag ON aag.age_group_id = ag.age_group_id
         WHERE aag.animator_id = ? ORDER BY ag.min_age'
    );
    $stmt->execute([$id]);
    $animator['age_groups'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT p.*, ap.price AS custom_price
         FROM programs p
         JOIN animator_programs ap ON ap.program_id = p.program_id
         WHERE ap.animator_id = ? AND p.is_active = 1
         ORDER BY p.category, p.title'
    );
    $stmt->execute([$id]);
    $animator['programs'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT * FROM photos WHERE user_id = ? AND booking_id IS NULL ORDER BY created_at DESC'
    );
    $stmt->execute([$animator['user_id']]);
    $animator['portfolio'] = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT r.*, u.name AS customer_name, b.event_date, p.title AS program_title
         FROM reviews r
         JOIN users u ON u.user_id = r.customer_id
         JOIN bookings b ON b.booking_id = r.booking_id
         LEFT JOIN programs p ON p.program_id = b.program_id
         WHERE r.animator_id = ?
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([$id]);
    $animator['reviews'] = $stmt->fetchAll();

    return $animator;
}

function get_animator_by_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT animator_id FROM animators WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id ? get_animator($pdo, (int)$id) : null;
}

function get_age_groups(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM age_groups ORDER BY min_age')->fetchAll();
}

function get_programs(PDO $pdo, bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM programs';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY category, price';
    return $pdo->query($sql)->fetchAll();
}

function get_specializations(PDO $pdo): array
{
    return $pdo->query('SELECT DISTINCT specialization FROM animators ORDER BY specialization')->fetchAll(PDO::FETCH_COLUMN);
}

function time_to_seconds(string $time): int
{
    $parts = array_map('intval', explode(':', substr($time, 0, 8)));
    return ($parts[0] ?? 0) * 3600 + ($parts[1] ?? 0) * 60 + ($parts[2] ?? 0);
}

function booking_overlap_exists(PDO $pdo, int $animatorId, string $date, string $time, float $hours, int $excludeId = 0): bool
{
    $sql = "SELECT booking_id, event_time, duration_hours FROM bookings
            WHERE animator_id = ? AND event_date = ? AND status IN ('pending','confirmed')";
    $params = [$animatorId, $date];
    if ($excludeId > 0) {
        $sql .= ' AND booking_id <> ?';
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $start = time_to_seconds($time);
    $end   = $start + (int)round($hours * 3600);
    foreach ($stmt->fetchAll() as $row) {
        $busyStart = time_to_seconds($row['event_time']);
        $busyEnd   = $busyStart + (int)round((float)$row['duration_hours'] * 3600);
        if ($start < $busyEnd && $end > $busyStart) {
            return true;
        }
    }
    return false;
}

/**
 * Поиск аниматоров по специализации, возрастной группе, программе,
 * цене и свободному времени (запросы №1–№4).
 */
function available_animators(PDO $pdo, array $f = []): array
{
    $sql = "SELECT a.*, u.name, u.email, u.phone,
                   GROUP_CONCAT(DISTINCT ag.title ORDER BY ag.min_age SEPARATOR ', ') AS age_groups,
                   GROUP_CONCAT(DISTINCT p.title ORDER BY p.title SEPARATOR ' · ') AS program_titles,
                   (SELECT COUNT(*) FROM reviews r WHERE r.animator_id = a.animator_id) AS reviews_count,
                   (SELECT COALESCE(AVG(r.rating), a.rating) FROM reviews r WHERE r.animator_id = a.animator_id) AS avg_rating
            FROM animators a
            JOIN users u ON u.user_id = a.user_id
            LEFT JOIN animator_age_groups aag ON aag.animator_id = a.animator_id
            LEFT JOIN age_groups ag ON ag.age_group_id = aag.age_group_id
            LEFT JOIN animator_programs ap ON ap.animator_id = a.animator_id
            LEFT JOIN programs p ON p.program_id = ap.program_id AND p.is_active = 1
            WHERE a.is_active = 1";
    $params = [];

    if (!empty($f['specialization'])) {
        $sql .= ' AND a.specialization = ?';
        $params[] = $f['specialization'];
    }
    if (!empty($f['age_group_id'])) {
        $sql .= ' AND EXISTS (SELECT 1 FROM animator_age_groups x WHERE x.animator_id = a.animator_id AND x.age_group_id = ?)';
        $params[] = (int)$f['age_group_id'];
    }
    if (!empty($f['program_id'])) {
        $sql .= ' AND EXISTS (SELECT 1 FROM animator_programs x WHERE x.animator_id = a.animator_id AND x.program_id = ?)';
        $params[] = (int)$f['program_id'];
    }
    if (!empty($f['max_price'])) {
        $sql .= ' AND a.hourly_rate <= ?';
        $params[] = (float)$f['max_price'];
    }
    if (!empty($f['q'])) {
        $sql .= ' AND (u.name LIKE ? OR a.specialization LIKE ? OR a.city LIKE ?)';
        $like = '%' . $f['q'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if (!empty($f['date']) && !empty($f['time'])) {
        $start = time_to_seconds($f['time']);
        $hours = max(0.5, (float)($f['hours'] ?? 1));
        $end   = $start + (int)round($hours * 3600);
        $sql .= " AND NOT EXISTS (
                    SELECT 1 FROM bookings b
                    WHERE b.animator_id = a.animator_id
                      AND b.event_date = ?
                      AND b.status IN ('pending','confirmed')
                      AND ? < (TIME_TO_SEC(b.event_time) + b.duration_hours * 3600)
                      AND ? > TIME_TO_SEC(b.event_time)
                  )";
        $params[] = $f['date'];
        $params[] = $start;
        $params[] = $end;
    }

    $sql .= ' GROUP BY a.animator_id, u.name, u.email, u.phone, a.user_id, a.specialization,
                     a.experience_years, a.hourly_rate, a.rating, a.bio, a.city, a.photo,
                     a.is_active, a.created_at
              ORDER BY avg_rating DESC, reviews_count DESC, a.experience_years DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function weekly_bookings(PDO $pdo, ?int $animatorId = null): array
{
    $sql = 'SELECT * FROM view_weekly_bookings';
    $params = [];
    if ($animatorId) {
        $sql .= ' WHERE animator_id = ?';
        $params[] = $animatorId;
    }
    $sql .= ' ORDER BY event_date, event_time';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function week_dates(): array
{
    $monday = new DateTime('monday this week');
    $days = [];
    for ($i = 0; $i < 7; $i++) {
        $day = clone $monday;
        if ($i > 0) {
            $day->modify('+' . $i . ' day');
        }
        $days[] = $day;
    }
    return $days;
}

function month_label(string $month): string
{
    $months = [1 => 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    $ts = strtotime($month . '-01');
    return ($months[(int)date('n', $ts)] ?? '') . ' ' . date('Y', $ts);
}

function month_bookings(PDO $pdo, int $animatorId, string $month): array
{
    $start = $month . '-01';
    $end   = date('Y-m-t', strtotime($start));
    $stmt = $pdo->prepare(
        "SELECT event_date, COUNT(*) AS cnt FROM bookings
         WHERE animator_id = ? AND event_date BETWEEN ? AND ? AND status IN ('pending','confirmed')
         GROUP BY event_date"
    );
    $stmt->execute([$animatorId, $start, $end]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['event_date']] = (int)$row['cnt'];
    }
    return $map;
}

function get_program(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM programs WHERE program_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_booking(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM bookings WHERE booking_id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function review_exists(PDO $pdo, int $bookingId): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE booking_id = ?');
    $stmt->execute([$bookingId]);
    return (bool)$stmt->fetchColumn();
}

function recalc_animator_rating(PDO $pdo, int $animatorId): void
{
    $stmt = $pdo->prepare(
        'UPDATE animators
         SET rating = (SELECT ROUND(AVG(rating), 1) FROM reviews WHERE animator_id = ?)
         WHERE animator_id = ? AND EXISTS (SELECT 1 FROM reviews WHERE animator_id = ?)'
    );
    $stmt->execute([$animatorId, $animatorId, $animatorId]);
}
