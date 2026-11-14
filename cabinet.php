<?php
require 'db.php';
require_login('cabinet.php');
verify_csrf();

$me = current_user();
$role = $me['role'];
$tab = $_GET['tab'] ?? ($role === 'animator' ? 'requests' : ($role === 'admin' ? 'dashboard' : 'orders'));
$allowedTabs = [
    'customer' => ['orders', 'photos', 'settings'],
    'animator' => ['requests', 'calendar', 'portfolio', 'programs', 'settings'],
    'admin'    => ['dashboard', 'settings'],
];
if (!in_array($tab, $allowedTabs[$role] ?? ['settings'], true)) {
    $tab = $allowedTabs[$role][0] ?? 'settings';
}

/* --------------------------- Обработка действий --------------------------- */
$action = $_POST['action'] ?? '';
if ($action !== '') {
    $back = 'cabinet.php?tab=' . urlencode($_POST['tab'] ?? $tab);

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $p1    = $_POST['password'] ?? '';
        $p2    = $_POST['password2'] ?? '';

        if (mb_strlen($name) < 2) {
            flash('error', 'Имя должно содержать не менее 2 символов.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Введите корректный email.');
        } elseif (strlen(preg_replace('/\D+/', '', $phone)) !== 11) {
            flash('error', 'Телефон должен содержать 11 цифр.');
        } elseif ($p1 !== '' && strlen($p1) < 6) {
            flash('error', 'Новый пароль должен быть не менее 6 символов.');
        } elseif ($p1 !== '' && $p1 !== $p2) {
            flash('error', 'Пароли не совпадают.');
        } else {
            $stmt = $pdo->prepare('SELECT user_id FROM users WHERE (email = ? OR phone = ?) AND user_id <> ? LIMIT 1');
            $stmt->execute([$email, $phone, $me['user_id']]);
            if ($stmt->fetch()) {
                flash('error', 'Такой email или телефон уже используется другим пользователем.');
            } else {
                if ($p1 !== '') {
                    $upd = $pdo->prepare('UPDATE users SET name=?, email=?, phone=?, password=? WHERE user_id=?');
                    $upd->execute([$name, $email, $phone, password_hash($p1, PASSWORD_BCRYPT), $me['user_id']]);
                } else {
                    $upd = $pdo->prepare('UPDATE users SET name=?, email=?, phone=? WHERE user_id=?');
                    $upd->execute([$name, $email, $phone, $me['user_id']]);
                }
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_phone'] = $phone;
                flash('success', 'Профиль обновлён.');
            }
        }
        redirect($back);
    }

    if ($action === 'cancel_booking') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE booking_id = ? AND customer_id = ? LIMIT 1');
        $stmt->execute([$bookingId, $me['user_id']]);
        $booking = $stmt->fetch();
        if (!$booking) {
            flash('error', 'Заказ не найден.');
        } elseif (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
            flash('error', 'Этот заказ уже нельзя отменить.');
        } else {
            $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE booking_id=?")->execute([$bookingId]);
            flash('success', 'Заказ отменён.');
        }
        redirect($back);
    }

    if ($action === 'add_review') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $text = trim($_POST['text'] ?? '');
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE booking_id = ? AND customer_id = ? LIMIT 1');
        $stmt->execute([$bookingId, $me['user_id']]);
        $booking = $stmt->fetch();

        if (!$booking) {
            flash('error', 'Заказ не найден.');
        } elseif ($booking['status'] !== 'completed') {
            flash('error', 'Отзыв можно оставить только после завершения заказа.');
        } elseif (review_exists($pdo, $bookingId)) {
            flash('error', 'Отзыв по этому заказу уже оставлен.');
        } elseif ($rating < 1 || $rating > 5) {
            flash('error', 'Поставьте оценку от 1 до 5.');
        } elseif (mb_strlen($text) < 5) {
            flash('error', 'Напишите текст отзыва (не менее 5 символов).');
        } else {
            $ins = $pdo->prepare('INSERT INTO reviews (booking_id, customer_id, animator_id, rating, text) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$bookingId, $me['user_id'], $booking['animator_id'], $rating, $text]);
            recalc_animator_rating($pdo, (int)$booking['animator_id']);
            flash('success', 'Спасибо за отзыв!');
        }
        redirect($back);
    }

    if ($action === 'upload_photo') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $roleNow = $me['role'];
        $booking = null;

        if ($roleNow === 'customer') {
            $stmt = $pdo->prepare('SELECT * FROM bookings WHERE booking_id = ? AND customer_id = ? LIMIT 1');
            $stmt->execute([$bookingId, $me['user_id']]);
            $booking = $stmt->fetch();
            if (!$booking || $booking['status'] !== 'completed') {
                flash('error', 'Фото можно добавить только к завершённому заказу.');
                redirect($back);
            }
        } elseif ($roleNow === 'animator') {
            $bookingId = null;
        } else {
            flash('error', 'Недоступное действие.');
            redirect($back);
        }

        [$ok, $pathOrError] = upload_image($_FILES['photo'] ?? [], 'photos');
        if (!$ok) {
            flash('error', $pathOrError);
        } else {
            $ins = $pdo->prepare('INSERT INTO photos (user_id, booking_id, title, file_path) VALUES (?, ?, ?, ?)');
            $ins->execute([$me['user_id'], $bookingId ?: null, $title !== '' ? $title : 'Фото с праздника', $pathOrError]);
            flash('success', 'Фотография добавлена.');
        }
        redirect($back);
    }

    if ($action === 'delete_photo') {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM photos WHERE photo_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$photoId, $me['user_id']]);
        $photo = $stmt->fetch();
        if ($photo) {
            delete_upload($photo['file_path']);
            $pdo->prepare('DELETE FROM photos WHERE photo_id=?')->execute([$photoId]);
            flash('success', 'Фотография удалена.');
        } else {
            flash('error', 'Фотография не найдена.');
        }
        redirect($back);
    }

    if ($action === 'animator_update') {
        if ($role !== 'animator') {
            flash('error', 'Действие доступно только аниматору.');
            redirect($back);
        }
        $animator = get_animator_by_user($pdo, $me['user_id']);
        if (!$animator) {
            flash('error', 'Профиль аниматора не найден.');
            redirect($back);
        }
        $spec = trim($_POST['specialization'] ?? '');
        $exp = max(0, min(60, (int)($_POST['experience_years'] ?? 0)));
        $rate = max(0, (float)($_POST['hourly_rate'] ?? 0));
        $bio = trim($_POST['bio'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $ageIds = array_map('intval', (array)($_POST['age_groups'] ?? []));
        $programIds = array_map('intval', (array)($_POST['program_ids'] ?? []));

        if ($spec === '' || $rate <= 0) {
            flash('error', 'Укажите специализацию и ставку.');
            redirect($back);
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE animators SET specialization=?, experience_years=?, hourly_rate=?, bio=?, city=? WHERE animator_id=?')
                ->execute([$spec, $exp, $rate, $bio, $city, $animator['animator_id']]);

            $pdo->prepare('DELETE FROM animator_age_groups WHERE animator_id=?')->execute([$animator['animator_id']]);
            $insAge = $pdo->prepare('INSERT INTO animator_age_groups (animator_id, age_group_id) VALUES (?, ?)');
            foreach (array_unique(array_filter($ageIds)) as $ageId) {
                $insAge->execute([$animator['animator_id'], $ageId]);
            }

            $pdo->prepare('DELETE FROM animator_programs WHERE animator_id=?')->execute([$animator['animator_id']]);
            $insProg = $pdo->prepare('INSERT INTO animator_programs (animator_id, program_id) VALUES (?, ?)');
            foreach (array_unique(array_filter($programIds)) as $programId) {
                $insProg->execute([$animator['animator_id'], $programId]);
            }
            $pdo->commit();
            flash('success', 'Профиль аниматора обновлён.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'Ошибка сохранения: ' . $e->getMessage());
        }
        redirect($back);
    }

    if ($action === 'booking_status') {
        if ($role !== 'animator') {
            flash('error', 'Недоступное действие.');
            redirect($back);
        }
        $animator = get_animator_by_user($pdo, $me['user_id']);
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        $allowed = ['confirmed', 'cancelled', 'completed'];
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE booking_id = ? AND animator_id = ? LIMIT 1');
        $stmt->execute([$bookingId, $animator['animator_id'] ?? 0]);
        $booking = $stmt->fetch();

        if (!$animator || !$booking) {
            flash('error', 'Заявка не найдена.');
        } elseif (!in_array($newStatus, $allowed, true)) {
            flash('error', 'Недопустимый статус.');
        } else {
            $pdo->prepare('UPDATE bookings SET status=? WHERE booking_id=?')->execute([$newStatus, $bookingId]);
            flash('success', 'Статус заявки обновлён: ' . status_label($newStatus) . '.');
        }
        redirect($back);
    }
}

/* --------------------------- Данные для страницы --------------------------- */
$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
$stmt->execute([$me['user_id']]);
$profile = $stmt->fetch() ?: $me;

$customerOrders = [];
$customerPhotos = [];
$animator = null;
$animatorBookings = [];
$animatorWeek = [];
$animatorBusy = [];
$allPrograms = get_programs($pdo);
$allAgeGroups = get_age_groups($pdo);
$adminStats = [];

if ($role === 'customer') {
    $stmt = $pdo->prepare(
        'SELECT b.*, au.name AS animator_name, p.title AS program_title,
                r.review_id, r.rating AS review_rating, r.text AS review_text
         FROM bookings b
         JOIN animators a ON a.animator_id = b.animator_id
         JOIN users au ON au.user_id = a.user_id
         LEFT JOIN programs p ON p.program_id = b.program_id
         LEFT JOIN reviews r ON r.booking_id = b.booking_id
         WHERE b.customer_id = ?
         ORDER BY b.event_date DESC, b.event_time DESC'
    );
    $stmt->execute([$me['user_id']]);
    $customerOrders = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT ph.*, b.event_date, p.title AS program_title
         FROM photos ph
         LEFT JOIN bookings b ON b.booking_id = ph.booking_id
         LEFT JOIN programs p ON p.program_id = b.program_id
         WHERE ph.user_id = ?
         ORDER BY ph.created_at DESC'
    );
    $stmt->execute([$me['user_id']]);
    $customerPhotos = $stmt->fetchAll();
}

if ($role === 'animator') {
    $animator = get_animator_by_user($pdo, $me['user_id']);
    if ($animator) {
        $stmt = $pdo->prepare(
            "SELECT b.*, c.name AS customer_name, c.email AS customer_email, p.title AS program_title
             FROM bookings b
             LEFT JOIN users c ON c.user_id = b.customer_id
             LEFT JOIN programs p ON p.program_id = b.program_id
             WHERE b.animator_id = ?
             ORDER BY FIELD(b.status, 'pending', 'confirmed', 'completed', 'cancelled'), b.event_date DESC, b.event_time DESC"
        );
        $stmt->execute([$animator['animator_id']]);
        $animatorBookings = $stmt->fetchAll();
        $animatorWeek = weekly_bookings($pdo, (int)$animator['animator_id']);

        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $animatorBusy = month_bookings($pdo, (int)$animator['animator_id'], $month);
    }
}

if ($role === 'admin') {
    $adminStats = [
        'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'animators' => (int)$pdo->query('SELECT COUNT(*) FROM animators')->fetchColumn(),
        'bookings' => (int)$pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
        'reviews' => (int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn(),
        'requests' => (int)$pdo->query("SELECT COUNT(*) FROM contact_requests WHERE status='new'")->fetchColumn(),
    ];
    $adminWeek = weekly_bookings($pdo);
    $adminRequests = $pdo->query('SELECT * FROM contact_requests ORDER BY created_at DESC LIMIT 5')->fetchAll();
}

$pageTitle = 'Личный кабинет';
$active = '';
require 'includes/header.php';
?>
<div class="page-wrap">
  <div class="page-inner">
    <div class="cabinet-layout">
      <aside class="cabinet-sidebar">
        <div class="cabinet-avatar"><?= e(initials($profile['name'])) ?></div>
        <div class="cabinet-name"><?= e($profile['name']) ?></div>
        <div class="cabinet-role"><?= e(role_label($role)) ?></div>
        <div class="cabinet-info">
          <?= e($profile['phone']) ?><br>
          <?= e($profile['email']) ?>
        </div>
        <ul class="cabinet-nav">
          <?php if ($role === 'customer'): ?>
            <li><a href="?tab=orders" class="<?= $tab === 'orders' ? 'active' : '' ?>">Мои заказы</a></li>
            <li><a href="?tab=photos" class="<?= $tab === 'photos' ? 'active' : '' ?>">Фото с праздников</a></li>
          <?php elseif ($role === 'animator'): ?>
            <li><a href="?tab=requests" class="<?= $tab === 'requests' ? 'active' : '' ?>">Входящие заявки</a></li>
            <li><a href="?tab=calendar" class="<?= $tab === 'calendar' ? 'active' : '' ?>">Календарь занятости</a></li>
            <li><a href="?tab=portfolio" class="<?= $tab === 'portfolio' ? 'active' : '' ?>">Портфолио</a></li>
            <li><a href="?tab=programs" class="<?= $tab === 'programs' ? 'active' : '' ?>">Программы и возраст</a></li>
          <?php else: ?>
            <li><a href="?tab=dashboard" class="<?= $tab === 'dashboard' ? 'active' : '' ?>">Сводка</a></li>
            <li><a href="admin/index.php">Админ-панель</a></li>
          <?php endif; ?>
          <li><a href="?tab=settings" class="<?= $tab === 'settings' ? 'active' : '' ?>">Настройки</a></li>
          <li><a href="logout.php" class="logout">Выйти</a></li>
        </ul>
      </aside>

      <div>
        <?php if ($role === 'customer' && $tab === 'orders'): ?>
          <div class="orders-title">Мои заказы</div>
          <?php if (!$customerOrders): ?>
            <div class="empty-orders">У вас пока нет заказов.<br><br><a href="animators.php">Подобрать аниматора →</a></div>
          <?php else: ?>
            <?php foreach ($customerOrders as $o): ?>
              <div class="order-item order-item-block">
                <div class="order-info">
                  <div class="order-name"><?= e($o['program_title'] ?: 'Работа аниматора') ?> · <?= e($o['animator_name']) ?></div>
                  <div class="order-details">
                    <?= e(format_date($o['event_date'])) ?>, <?= e(format_time($o['event_time'])) ?>,
                    <?= e($o['duration_hours']) ?> ч · <?= e($o['address']) ?>
                  </div>
                  <div class="order-details">Заказ №<?= (int)$o['booking_id'] ?> от <?= e(format_datetime($o['created_at'])) ?></div>
                  <div class="status-badge <?= e(status_class($o['status'])) ?>"><?= e(status_label($o['status'])) ?></div>
                  <div class="order-total">Сумма: <b><?= format_price($o['total_price']) ?></b></div>
                </div>
                <div class="order-actions">
                  <?php if (in_array($o['status'], ['pending', 'confirmed'], true)): ?>
                    <form method="POST" action="">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="cancel_booking">
                      <input type="hidden" name="tab" value="orders">
                      <input type="hidden" name="booking_id" value="<?= (int)$o['booking_id'] ?>">
                      <button type="submit" class="btn-danger btn-small" data-confirm="Отменить заказ №<?= (int)$o['booking_id'] ?>?">Отменить</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($o['status'] === 'completed'): ?>
                    <?php if ($o['review_id']): ?>
                      <div class="my-review">
                        <div class="review-stars"><?= stars((int)$o['review_rating']) ?></div>
                        <div class="muted"><?= e($o['review_text']) ?></div>
                      </div>
                    <?php else: ?>
                      <details class="review-form">
                        <summary>Оставить отзыв</summary>
                        <form method="POST" action="">
                          <?php csrf_field(); ?>
                          <input type="hidden" name="action" value="add_review">
                          <input type="hidden" name="tab" value="orders">
                          <input type="hidden" name="booking_id" value="<?= (int)$o['booking_id'] ?>">
                          <div class="form-group">
                            <label>Оценка</label>
                            <select class="form-input" name="rating">
                              <option value="5">★★★★★ — отлично</option>
                              <option value="4">★★★★☆ — хорошо</option>
                              <option value="3">★★★☆☆ — нормально</option>
                              <option value="2">★★☆☆☆ — плохо</option>
                              <option value="1">★☆☆☆☆ — ужасно</option>
                            </select>
                          </div>
                          <div class="form-group">
                            <textarea class="form-input" name="text" rows="3" placeholder="Расскажите о празднике" required></textarea>
                          </div>
                          <button class="btn-orange btn-small" type="submit">Отправить отзыв</button>
                        </form>
                      </details>
                    <?php endif; ?>
                    <details class="review-form">
                      <summary>Добавить фото</summary>
                      <form method="POST" action="" enctype="multipart/form-data">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="upload_photo">
                        <input type="hidden" name="tab" value="orders">
                        <input type="hidden" name="booking_id" value="<?= (int)$o['booking_id'] ?>">
                        <div class="form-group">
                          <input class="form-input" type="text" name="title" placeholder="Подпись к фото" maxlength="150">
                        </div>
                        <div class="form-group">
                          <input class="form-input" type="file" name="photo" accept="image/*" required>
                        </div>
                        <button class="btn-teal btn-small" type="submit">Загрузить</button>
                      </form>
                    </details>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

        <?php elseif ($role === 'customer' && $tab === 'photos'): ?>
          <div class="orders-title">Фото с праздников</div>
          <?php if (!$customerPhotos): ?>
            <div class="empty-orders">Фотографии появятся здесь после завершённых заказов.<br><br><a href="?tab=orders">Мои заказы →</a></div>
          <?php else: ?>
            <div class="photo-grid">
              <?php foreach ($customerPhotos as $ph): ?>
                <figure class="photo-card">
                  <a href="<?= e($ph['file_path']) ?>" target="_blank"><img src="<?= e($ph['file_path']) ?>" alt="<?= e($ph['title']) ?>"></a>
                  <figcaption>
                    <b><?= e($ph['title']) ?></b>
                    <span><?= $ph['event_date'] ? e(format_date($ph['event_date'])) : '' ?> <?= e($ph['program_title'] ?? '') ?></span>
                    <form method="POST" action="" style="margin-top:6px">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="delete_photo">
                      <input type="hidden" name="tab" value="photos">
                      <input type="hidden" name="photo_id" value="<?= (int)$ph['photo_id'] ?>">
                      <button class="link-danger" type="submit" data-confirm="Удалить фотографию?">Удалить</button>
                    </form>
                  </figcaption>
                </figure>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        <?php elseif ($role === 'animator' && $tab === 'requests'): ?>
          <div class="orders-title">Входящие заявки</div>
          <?php if (!$animator): ?>
            <div class="alert alert-error">Профиль аниматора не найден. Обратитесь к администратору.</div>
          <?php elseif (!$animatorBookings): ?>
            <div class="empty-orders">Заявок пока нет.</div>
          <?php else: ?>
            <?php foreach ($animatorBookings as $b): ?>
              <div class="order-item order-item-block">
                <div class="order-info">
                  <div class="order-name"><?= e($b['program_title'] ?: 'Работа аниматора') ?> · заказ №<?= (int)$b['booking_id'] ?></div>
                  <div class="order-details"><?= e(format_date($b['event_date'])) ?>, <?= e(format_time($b['event_time'])) ?>, <?= e($b['duration_hours']) ?> ч</div>
                  <div class="order-details"><?= e($b['address']) ?> · <?= e($b['children_age'] ?: 'возраст не указан') ?></div>
                  <div class="order-details">Клиент: <?= e($b['customer_name'] ?: $b['contact_name']) ?> · <?= e($b['contact_phone']) ?><?= $b['contact_email'] ? ' · ' . e($b['contact_email']) : '' ?></div>
                  <?php if ($b['comment']): ?><div class="order-comment">💬 <?= e($b['comment']) ?></div><?php endif; ?>
                  <div class="status-badge <?= e(status_class($b['status'])) ?>"><?= e(status_label($b['status'])) ?></div>
                  <div class="order-total">Сумма: <b><?= format_price($b['total_price']) ?></b></div>
                </div>
                <div class="order-actions">
                  <?php if ($b['status'] === 'pending'): ?>
                    <form method="POST" action="">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="booking_status"><input type="hidden" name="tab" value="requests">
                      <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>"><input type="hidden" name="status" value="confirmed">
                      <button class="btn-teal btn-small" type="submit">Подтвердить</button>
                    </form>
                    <form method="POST" action="">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="booking_status"><input type="hidden" name="tab" value="requests">
                      <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>"><input type="hidden" name="status" value="cancelled">
                      <button class="btn-danger btn-small" type="submit" data-confirm="Отклонить заявку?">Отклонить</button>
                    </form>
                  <?php elseif ($b['status'] === 'confirmed'): ?>
                    <form method="POST" action="">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="booking_status"><input type="hidden" name="tab" value="requests">
                      <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>"><input type="hidden" name="status" value="completed">
                      <button class="btn-orange btn-small" type="submit">Завершить</button>
                    </form>
                    <form method="POST" action="">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="booking_status"><input type="hidden" name="tab" value="requests">
                      <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>"><input type="hidden" name="status" value="cancelled">
                      <button class="btn-danger btn-small" type="submit" data-confirm="Отменить заявку?">Отменить</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

        <?php elseif ($role === 'animator' && $tab === 'calendar'): ?>
          <?php
          $month = $_GET['month'] ?? date('Y-m');
          if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
              $month = date('Y-m');
          }
          $firstDay = strtotime($month . '-01');
          $daysInMonth = (int)date('t', $firstDay);
          $startWeekday = (int)date('N', $firstDay);
          ?>
          <div class="orders-title">Календарь занятости</div>
          <div class="calendar-box">
            <div class="cal-head">
              <div class="cal-month"><?= e(month_label($month)) ?></div>
              <div class="cal-nav">
                <a href="?tab=calendar&month=<?= e(date('Y-m', strtotime($month . '-01 -1 month'))) ?>">←</a>
                <a href="?tab=calendar&month=<?= e(date('Y-m', strtotime($month . '-01 +1 month'))) ?>">→</a>
              </div>
            </div>
            <div class="cal-grid">
              <?php foreach (['Пн','Вт','Ср','Чт','Пт','Сб','Вс'] as $d): ?><div class="cal-day-name"><?= $d ?></div><?php endforeach; ?>
              <?php for ($i = 1; $i < $startWeekday; $i++): ?><div class="cal-empty"></div><?php endfor; ?>
              <?php for ($day = 1; $day <= $daysInMonth; $day++):
                $date = sprintf('%s-%02d', $month, $day);
                $isBusy = isset($animatorBusy[$date]);
                $isPast = strtotime($date) < strtotime(date('Y-m-d'));
              ?>
                <div class="cal-cell <?= $isBusy ? 'busy' : ($isPast ? 'past' : 'free') ?>" title="<?= e(format_date($date)) ?>: <?= $isBusy ? 'есть бронирование' : 'свободен' ?>"><?= $day ?></div>
              <?php endfor; ?>
            </div>
            <div class="cal-legend"><span><i class="dot-free"></i> свободен</span><span><i class="dot-busy"></i> занят</span></div>
          </div>

          <div class="orders-title" style="margin-top:26px">Бронирования на текущую неделю</div>
          <?php if (!$animatorWeek): ?>
            <div class="empty-orders">На эту неделю бронирований нет.</div>
          <?php else: ?>
            <div class="table-wrap">
              <table class="data-table">
                <thead><tr><th>Дата</th><th>Время</th><th>Клиент</th><th>Программа</th><th>Статус</th></tr></thead>
                <tbody>
                  <?php foreach ($animatorWeek as $w): ?>
                    <tr>
                      <td><?= e(format_date($w['event_date'])) ?></td>
                      <td><?= e(format_time($w['event_time'])) ?></td>
                      <td><?= e($w['customer_name']) ?></td>
                      <td><?= e($w['program_title'] ?: '—') ?></td>
                      <td><span class="status-badge <?= e(status_class($w['status'])) ?>"><?= e(status_label($w['status'])) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

        <?php elseif ($role === 'animator' && $tab === 'portfolio'): ?>
          <div class="orders-title">Портфолио</div>
          <div class="profile-section-title" style="margin-top:0">Добавить фотографию</div>
          <form method="POST" action="" enctype="multipart/form-data" class="inline-upload">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="upload_photo"><input type="hidden" name="tab" value="portfolio">
            <input class="form-input" type="text" name="title" placeholder="Подпись" maxlength="150">
            <input class="form-input" type="file" name="photo" accept="image/*" required>
            <button class="btn-teal" type="submit">Загрузить</button>
          </form>

          <?php $portfolio = $animator['portfolio'] ?? []; ?>
          <?php if (!$portfolio): ?>
            <div class="empty-orders">В портфолио пока нет фотографий.</div>
          <?php else: ?>
            <div class="photo-grid">
              <?php foreach ($portfolio as $ph): ?>
                <figure class="photo-card">
                  <a href="<?= e($ph['file_path']) ?>" target="_blank"><img src="<?= e($ph['file_path']) ?>" alt="<?= e($ph['title']) ?>"></a>
                  <figcaption>
                    <b><?= e($ph['title']) ?></b>
                    <form method="POST" action="" style="margin-top:6px">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="delete_photo"><input type="hidden" name="tab" value="portfolio">
                      <input type="hidden" name="photo_id" value="<?= (int)$ph['photo_id'] ?>">
                      <button class="link-danger" type="submit" data-confirm="Удалить фотографию?">Удалить</button>
                    </form>
                  </figcaption>
                </figure>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        <?php elseif ($role === 'animator' && $tab === 'programs'): ?>
          <div class="orders-title">Программы и возрастные группы</div>
          <?php if (!$animator): ?>
            <div class="alert alert-error">Профиль аниматора не найден.</div>
          <?php else: ?>
            <form method="POST" action="" class="card-form">
              <?php csrf_field(); ?>
              <input type="hidden" name="action" value="animator_update"><input type="hidden" name="tab" value="programs">
              <div class="form-row">
                <div class="form-group">
                  <label>Специализация</label>
                  <select class="form-input" name="specialization">
                    <?php foreach (['Детские праздники', 'Шоу-программы', 'Квесты'] as $spec): ?>
                      <option value="<?= e($spec) ?>" <?= $animator['specialization'] === $spec ? 'selected' : '' ?>><?= e($spec) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Город</label>
                  <input class="form-input" type="text" name="city" value="<?= e($animator['city']) ?>">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Опыт, лет</label>
                  <input class="form-input" type="number" name="experience_years" min="0" max="60" value="<?= (int)$animator['experience_years'] ?>">
                </div>
                <div class="form-group">
                  <label>Ставка, ₽/час</label>
                  <input class="form-input" type="number" name="hourly_rate" min="0" step="100" value="<?= (int)$animator['hourly_rate'] ?>">
                </div>
              </div>
              <div class="form-group">
                <label>О себе</label>
                <textarea class="form-input" name="bio" rows="4"><?= e($animator['bio']) ?></textarea>
              </div>

              <div class="form-group">
                <label>Возрастные группы</label>
                <div class="check-grid">
                  <?php $selectedAges = array_column($animator['age_groups'], 'age_group_id'); ?>
                  <?php foreach ($allAgeGroups as $ag): ?>
                    <label class="check-row"><input type="checkbox" name="age_groups[]" value="<?= (int)$ag['age_group_id'] ?>" <?= in_array((int)$ag['age_group_id'], array_map('intval', $selectedAges), true) ? 'checked' : '' ?>><span><?= e($ag['title']) ?></span></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="form-group">
                <label>Тематические программы</label>
                <div class="check-grid">
                  <?php $selectedPrograms = array_column($animator['programs'], 'program_id'); ?>
                  <?php foreach ($allPrograms as $p): ?>
                    <label class="check-row"><input type="checkbox" name="program_ids[]" value="<?= (int)$p['program_id'] ?>" <?= in_array((int)$p['program_id'], array_map('intval', $selectedPrograms), true) ? 'checked' : '' ?>><span><?= e(program_icon($p['title'])) ?> <?= e($p['title']) ?></span></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <button class="form-btn" type="submit">Сохранить изменения</button>
            </form>
          <?php endif; ?>

        <?php elseif ($role === 'admin' && $tab === 'dashboard'): ?>
          <div class="orders-title">Сводка администратора</div>
          <div class="stats-grid">
            <div class="stat-card"><b><?= $adminStats['users'] ?></b><span>пользователей</span></div>
            <div class="stat-card"><b><?= $adminStats['animators'] ?></b><span>аниматоров</span></div>
            <div class="stat-card"><b><?= $adminStats['bookings'] ?></b><span>бронирований</span></div>
            <div class="stat-card"><b><?= $adminStats['reviews'] ?></b><span>отзывов</span></div>
            <div class="stat-card"><b><?= $adminStats['requests'] ?></b><span>новых обращений</span></div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0">
            <a href="admin/index.php" class="btn-orange">Перейти в админ-панель</a>
            <a href="admin/index.php?page=views" class="btn-teal">Представления (5 запросов)</a>
          </div>

          <div class="orders-title" style="margin-top:20px">Бронирования на текущую неделю</div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Дата</th><th>Время</th><th>Аниматор</th><th>Клиент</th><th>Программа</th><th>Статус</th></tr></thead>
              <tbody>
                <?php foreach ($adminWeek as $w): ?>
                  <tr>
                    <td><?= e(format_date($w['event_date'])) ?></td>
                    <td><?= e(format_time($w['event_time'])) ?></td>
                    <td><?= e($w['animator_name']) ?></td>
                    <td><?= e($w['customer_name']) ?></td>
                    <td><?= e($w['program_title'] ?: '—') ?></td>
                    <td><span class="status-badge <?= e(status_class($w['status'])) ?>"><?= e(status_label($w['status'])) ?></span></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$adminWeek): ?><tr><td colspan="6" class="muted">На текущую неделю бронирований нет.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="orders-title" style="margin-top:20px">Последние обращения</div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Дата</th><th>Имя</th><th>Контакт</th><th>Сообщение</th><th>Статус</th></tr></thead>
              <tbody>
                <?php foreach ($adminRequests as $r): ?>
                  <tr>
                    <td><?= e(format_datetime($r['created_at'])) ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e($r['email']) ?><br><small><?= e($r['phone']) ?></small></td>
                    <td><?= e(mb_strimwidth($r['message'], 0, 80, '…')) ?></td>
                    <td><span class="status-badge <?= e(status_class($r['status'])) ?>"><?= e(status_label($r['status'])) ?></span></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$adminRequests): ?><tr><td colspan="5" class="muted">Обращений нет.</td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>

        <?php elseif ($tab === 'settings'): ?>
          <div class="orders-title">Настройки профиля</div>
          <form method="POST" action="" class="card-form">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="update_profile"><input type="hidden" name="tab" value="settings">
            <div class="form-group">
              <label>Имя и фамилия</label>
              <input class="form-input" type="text" name="name" value="<?= e($profile['name']) ?>" data-dadata="fio" data-clean required>
              <span class="dadata-status"></span>
            </div>
            <div class="form-group">
              <label>Email</label>
              <input class="form-input" type="email" name="email" value="<?= e($profile['email']) ?>" data-dadata="email" data-clean required>
              <span class="dadata-status"></span>
            </div>
            <div class="form-group">
              <label>Телефон</label>
              <input class="form-input" type="tel" name="phone" value="<?= e($profile['phone']) ?>" data-phone-mask data-clean data-dadata="phone" required>
              <span class="dadata-status"></span>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Новый пароль</label>
                <input class="form-input" type="password" name="password" placeholder="Оставьте пустым, чтобы не менять" minlength="6">
              </div>
              <div class="form-group">
                <label>Повторите пароль</label>
                <input class="form-input" type="password" name="password2" placeholder="Ещё раз" minlength="6">
              </div>
            </div>
            <button class="form-btn" type="submit">Сохранить</button>
          </form>
          <div class="account-meta">
            Роль: <b><?= e(role_label($profile['role'])) ?></b> · аккаунт создан <?= e(format_date($profile['created_at'])) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require 'includes/footer.php'; ?>
