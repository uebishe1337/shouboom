<?php
require 'db.php';
verify_csrf();

$errors = [];
$successBooking = null;
$user = current_user();

$animators = available_animators($pdo, []);
$programs = get_programs($pdo);
$ageGroups = get_age_groups($pdo);

// Какие аниматоры предлагают какие программы
$programAnimators = [];
$stmt = $pdo->query('SELECT animator_id, program_id, price FROM animator_programs');
foreach ($stmt->fetchAll() as $row) {
    $programAnimators[(int)$row['program_id']][(int)$row['animator_id']] = $row['price'];
}

$form = [
    'animator_id'    => (int)($_GET['animator_id'] ?? 0),
    'program_id'     => (int)($_GET['program_id'] ?? 0),
    'event_date'     => '',
    'event_time'     => '12:00',
    'duration_hours' => '1',
    'address'        => '',
    'children_age'   => '',
    'contact_name'   => $user['name'] ?? '',
    'contact_phone'  => $user['phone'] ?? '',
    'contact_email'  => $user['email'] ?? '',
    'comment'        => '',
];

if (isset($_GET['success'], $_GET['id'])) {
    $stmt = $pdo->prepare(
        'SELECT b.*, u.name AS animator_name, p.title AS program_title
         FROM bookings b
         JOIN animators a ON a.animator_id = b.animator_id
         JOIN users u ON u.user_id = a.user_id
         LEFT JOIN programs p ON p.program_id = b.program_id
         WHERE b.booking_id = ? LIMIT 1'
    );
    $stmt->execute([(int)$_GET['id']]);
    $successBooking = $stmt->fetch() ?: null;
}

if (is_post() && !$successBooking) {
    foreach (array_keys($form) as $key) {
        if (isset($_POST[$key])) {
            $form[$key] = trim((string)$_POST[$key]);
        }
    }

    $animator = null;
    foreach ($animators as $a) {
        if ((int)$a['animator_id'] === (int)$form['animator_id']) {
            $animator = $a;
            break;
        }
    }
    $program = $form['program_id'] ? get_program($pdo, (int)$form['program_id']) : null;

    if (!$animator) {
        $errors[] = 'Выберите аниматора.';
    }
    if ($program && !isset($programAnimators[(int)$program['program_id']][(int)$form['animator_id']])) {
        $errors[] = 'Выбранный аниматор не проводит эту программу.';
    }
    if ($form['event_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['event_date'])) {
        $errors[] = 'Укажите дату мероприятия.';
    } elseif ($form['event_date'] < date('Y-m-d')) {
        $errors[] = 'Дата мероприятия не может быть в прошлом.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $form['event_time'])) {
        $errors[] = 'Укажите время начала.';
    }
    if ((float)$form['duration_hours'] < 0.5 || (float)$form['duration_hours'] > 8) {
        $errors[] = 'Длительность должна быть от 0,5 до 8 часов.';
    }
    if (mb_strlen($form['address']) < 5) {
        $errors[] = 'Укажите адрес мероприятия.';
    }
    if (mb_strlen($form['contact_name']) < 2) {
        $errors[] = 'Укажите имя заказчика.';
    }
    if (strlen(preg_replace('/\D+/', '', $form['contact_phone'])) !== 11) {
        $errors[] = 'Укажите телефон в формате +7 (999) 123-45-67.';
    }
    if ($form['contact_email'] !== '' && !filter_var($form['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email.';
    }

    if (!$errors && $animator) {
        $hours = (float)$form['duration_hours'];
        if (booking_overlap_exists($pdo, (int)$animator['animator_id'], $form['event_date'], $form['event_time'], $hours)) {
            $errors[] = 'Этот аниматор уже занят в выбранное время. Выберите другое время или другого аниматора.';
        } else {
            $base = $program
                ? (float)($programAnimators[(int)$program['program_id']][(int)$animator['animator_id']] ?: $program['price'])
                : (float)$animator['hourly_rate'];
            $total = $base + max(0, $hours - 1) * (float)$animator['hourly_rate'];

            $ins = $pdo->prepare(
                "INSERT INTO bookings
                 (customer_id, animator_id, program_id, contact_name, contact_phone, contact_email,
                  event_date, event_time, duration_hours, address, children_age, comment, status, total_price)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
            );
            $ins->execute([
                $user['user_id'] ?? null,
                (int)$animator['animator_id'],
                $program ? (int)$program['program_id'] : null,
                $form['contact_name'],
                $form['contact_phone'],
                $form['contact_email'],
                $form['event_date'],
                $form['event_time'],
                $hours,
                $form['address'],
                $form['children_age'],
                $form['comment'],
                $total,
            ]);
            $newId = (int)$pdo->lastInsertId();
            redirect('order.php?success=1&id=' . $newId);
        }
    }
}

$pageTitle = 'Заказ';
$active = 'order';
require 'includes/header.php';
?>
<div class="page-wrap">
  <div class="page-inner">
    <?php if ($successBooking): ?>
      <div class="order-success">
        <div class="success-emoji">🎉</div>
        <h1>Заказ №<?= (int)$successBooking['booking_id'] ?> оформлен!</h1>
        <p>Мы передали заявку аниматору. Статус заказа можно отслеживать в личном кабинете.</p>
        <div class="order-success-card">
          <div><span>Аниматор</span><b><?= e($successBooking['animator_name']) ?></b></div>
          <div><span>Программа</span><b><?= e($successBooking['program_title'] ?: 'Индивидуальная программа') ?></b></div>
          <div><span>Дата и время</span><b><?= e(format_date($successBooking['event_date'])) ?>, <?= e(format_time($successBooking['event_time'])) ?></b></div>
          <div><span>Адрес</span><b><?= e($successBooking['address']) ?></b></div>
          <div><span>Стоимость</span><b><?= format_price($successBooking['total_price']) ?></b></div>
          <div><span>Статус</span><b class="<?= e(status_class($successBooking['status'])) ?>"><?= e(status_label($successBooking['status'])) ?></b></div>
        </div>
        <div class="success-actions-row">
          <?php if ($user): ?>
            <a href="cabinet.php" class="btn-orange">Перейти в личный кабинет</a>
          <?php else: ?>
            <a href="register.php" class="btn-orange">Зарегистрироваться</a>
            <a href="login.php?next=cabinet.php" class="btn-teal">Войти</a>
          <?php endif; ?>
          <a href="animators.php" class="btn-ghost">Выбрать ещё</a>
        </div>
        <?php if (!$user): ?>
          <p class="muted" style="margin-top:14px">Заказ сохранён без привязки к аккаунту. Зарегистрируйтесь с тем же телефоном — и мы свяжем заказ с вашим кабинетом при обращении в поддержку.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <h1 class="section-title-center">Оформление заказа</h1>
      <p class="section-sub-center">Заполните форму — система проверит занятость аниматора и сохранит бронирование</p>

      <div class="order-form-wrap">
        <?php if ($errors): ?>
          <div class="alert alert-error">
            <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="" id="orderForm" novalidate>
          <?php csrf_field(); ?>

          <div class="form-section-title">Праздник</div>
          <div class="form-group">
            <label>Аниматор <span class="req">*</span></label>
            <select class="form-input" name="animator_id" required>
              <option value="">— выберите аниматора —</option>
              <?php foreach ($animators as $a): ?>
                <option value="<?= (int)$a['animator_id'] ?>" data-rate="<?= e($a['hourly_rate']) ?>"
                  <?= (int)$form['animator_id'] === (int)$a['animator_id'] ? 'selected' : '' ?>>
                  <?= e($a['name']) ?> — <?= e($a['specialization']) ?> (<?= format_price($a['hourly_rate']) ?>/час)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Программа</label>
            <select class="form-input" name="program_id">
              <option value="">Без программы (работа аниматора)</option>
              <?php foreach ($programs as $p): ?>
                <?php $ids = array_keys($programAnimators[(int)$p['program_id']] ?? []); ?>
                <option value="<?= (int)$p['program_id'] ?>" data-price="<?= e($p['price']) ?>" data-animators="<?= e(implode(',', $ids)) ?>"
                  <?= (int)$form['program_id'] === (int)$p['program_id'] ? 'selected' : '' ?>>
                  <?= e($p['title']) ?> — <?= format_price($p['price']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="field-help">Список программ подстраивается под выбранного аниматора.</span>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Дата <span class="req">*</span></label>
              <input class="form-input" type="date" name="event_date" value="<?= e($form['event_date']) ?>" min="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
              <label>Время начала <span class="req">*</span></label>
              <input class="form-input" type="time" name="event_time" value="<?= e($form['event_time']) ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Длительность</label>
              <select class="form-input" name="duration_hours">
                <?php foreach ([['v' => 0.5, 'l' => '30 минут'], ['v' => 1, 'l' => '1 час'], ['v' => 1.5, 'l' => '1,5 часа'], ['v' => 2, 'l' => '2 часа'], ['v' => 3, 'l' => '3 часа']] as $opt): ?>
                  <option value="<?= $opt['v'] ?>" <?= abs((float)$form['duration_hours'] - $opt['v']) < 0.01 ? 'selected' : '' ?>><?= e($opt['l']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Возраст детей</label>
              <select class="form-input" name="children_age">
                <option value="">Не указывать</option>
                <?php foreach ($ageGroups as $ag): ?>
                  <option value="<?= e($ag['title']) ?>" <?= $form['children_age'] === $ag['title'] ? 'selected' : '' ?>><?= e($ag['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Адрес мероприятия <span class="req">*</span></label>
            <input class="form-input" type="text" name="address" value="<?= e($form['address']) ?>"
                   placeholder="Улица, дом, квартира" data-dadata="address" data-clean required>
            <span class="dadata-status"></span>
            <span class="field-help">Подсказки и нормализация адреса — DaData.</span>
          </div>

          <div class="price-preview">
            <span>Предварительная стоимость</span>
            <b id="pricePreview">—</b>
          </div>

          <div class="form-section-title" style="margin-top:24px">Ваши контакты</div>
          <div class="form-group">
            <label>Имя <span class="req">*</span></label>
            <input class="form-input" type="text" name="contact_name" value="<?= e($form['contact_name']) ?>"
                   placeholder="Как к вам обращаться" data-dadata="fio" data-clean required minlength="2">
            <span class="dadata-status"></span>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Телефон <span class="req">*</span></label>
              <input class="form-input" type="tel" name="contact_phone" value="<?= e($form['contact_phone']) ?>"
                     placeholder="+7 (___) ___-__-__" data-dadata="phone" data-clean data-phone-mask required>
              <span class="dadata-status"></span>
            </div>
            <div class="form-group">
              <label>Email</label>
              <input class="form-input" type="email" name="contact_email" value="<?= e($form['contact_email']) ?>"
                     placeholder="example@mail.ru" data-dadata="email" data-clean>
              <span class="dadata-status"></span>
            </div>
          </div>

          <div class="form-group">
            <label>Комментарий</label>
            <textarea class="form-input" name="comment" rows="3" placeholder="Пожелания, особенности ребёнка, детали площадки"><?= e($form['comment']) ?></textarea>
          </div>

          <?php if (!$user): ?>
            <div class="alert alert-info">Вы не авторизованы. Заказ сохранится, но для отслеживания статуса в личном кабинете лучше <a href="login.php?next=order.php">войти</a> или <a href="register.php">зарегистрироваться</a>.</div>
          <?php endif; ?>

          <button class="form-btn" type="submit">Забронировать</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  (function () {
    const animator = document.querySelector('#orderForm [name="animator_id"]');
    const program = document.querySelector('#orderForm [name="program_id"]');
    if (!animator || !program) return;

    const allOptions = Array.from(program.options).map(function (opt) {
      return { el: opt, ids: (opt.getAttribute('data-animators') || '').split(',').filter(Boolean) };
    });

    function filterPrograms() {
      const id = animator.value;
      let selectedStillAvailable = false;
      allOptions.forEach(function (item) {
        if (!item.el.value) { item.el.hidden = false; item.el.disabled = false; return; }
        const available = !id || item.ids.indexOf(id) !== -1;
        item.el.hidden = !available;
        item.el.disabled = !available;
        if (!available && item.el.selected) item.el.selected = false;
        if (available && item.el.selected) selectedStillAvailable = true;
      });
      if (!selectedStillAvailable && program.selectedOptions.length && program.selectedOptions[0].disabled) {
        program.value = '';
      }
      program.dispatchEvent(new Event('change'));
    }

    animator.addEventListener('change', filterPrograms);
    filterPrograms();
  })();
</script>
<?php require 'includes/footer.php'; ?>
