<?php
require 'db.php';
verify_csrf();

$error = '';
$form = [
    'name' => '', 'email' => '', 'phone' => '', 'role' => 'customer',
    'specialization' => 'Детские праздники', 'experience_years' => '1', 'hourly_rate' => '2500',
];

if (current_user()) {
    redirect('cabinet.php');
}

if (is_post()) {
    foreach ($form as $key => $value) {
        $form[$key] = trim($_POST[$key] ?? $value);
    }
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $agree     = !empty($_POST['agree']);

    $phoneDigits = preg_replace('/\D+/', '', $form['phone']);

    if ($form['name'] === '' || $form['email'] === '' || $form['phone'] === '' || $password === '' || $password2 === '') {
        $error = 'Заполните все обязательные поля.';
    } elseif (!in_array($form['role'], ['customer', 'animator'], true)) {
        $error = 'Выберите роль.';
    } elseif (mb_strlen($form['name']) < 2) {
        $error = 'Имя должно содержать не менее 2 символов.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email.';
    } elseif (strlen($phoneDigits) !== 11) {
        $error = 'Телефон должен содержать 11 цифр в формате +7 (999) 123-45-67.';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов.';
    } elseif ($password !== $password2) {
        $error = 'Пароли не совпадают.';
    } elseif (!$agree) {
        $error = 'Подтвердите согласие на обработку персональных данных.';
    } else {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? OR phone = ? LIMIT 1');
        $stmt->execute([$form['email'], $form['phone']]);
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким email или телефоном уже зарегистрирован.';
        } else {
            try {
                $pdo->beginTransaction();
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare('INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)');
                $ins->execute([$form['name'], $form['email'], $form['phone'], $hash, $form['role']]);
                $newId = (int)$pdo->lastInsertId();

                if ($form['role'] === 'animator') {
                    $insA = $pdo->prepare(
                        'INSERT INTO animators (user_id, specialization, experience_years, hourly_rate, bio, city)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $insA->execute([
                        $newId,
                        $form['specialization'] !== '' ? $form['specialization'] : 'Детские праздники',
                        max(0, (int)$form['experience_years']),
                        max(0, (float)$form['hourly_rate']),
                        'Новый аниматор на ШоуБум.',
                        'Москва',
                    ]);
                }
                $pdo->commit();

                $user = ['user_id' => $newId, 'name' => $form['name'], 'email' => $form['email'], 'phone' => $form['phone'], 'role' => $form['role']];
                login_user($user);
                flash('success', 'Регистрация прошла успешно! Добро пожаловать, ' . $form['name'] . '!');
                redirect('cabinet.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Не удалось создать аккаунт: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Регистрация';
$active = '';
require 'includes/header.php';
?>
<div class="page-wrap">
  <div class="page-inner">
    <h1 class="section-title-center">Регистрация</h1>
    <p class="section-sub-center">Создайте аккаунт заказчика или аниматора</p>

    <div class="auth-wrap" style="max-width:520px">
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <form method="POST" action="" id="regForm" novalidate>
        <?php csrf_field(); ?>

        <div class="form-group">
          <label>Я регистрируюсь как</label>
          <div class="role-switch">
            <label class="role-option">
              <input type="radio" name="role" value="customer" <?= $form['role'] === 'customer' ? 'checked' : '' ?>>
              <span>Заказчик</span>
            </label>
            <label class="role-option">
              <input type="radio" name="role" value="animator" <?= $form['role'] === 'animator' ? 'checked' : '' ?>>
              <span>Аниматор</span>
            </label>
          </div>
        </div>

        <div class="form-group">
          <label>Имя и фамилия <span class="req">*</span></label>
          <input class="form-input" type="text" name="name" value="<?= e($form['name']) ?>"
                 placeholder="Начните вводить имя" data-dadata="fio" data-clean required minlength="2">
          <span class="dadata-status"></span>
        </div>

        <div class="form-group">
          <label>Email <span class="req">*</span></label>
          <input class="form-input" type="email" name="email" value="<?= e($form['email']) ?>"
                 placeholder="example@mail.ru" data-dadata="email" data-clean required>
          <span class="dadata-status"></span>
        </div>

        <div class="form-group">
          <label>Телефон <span class="req">*</span></label>
          <input class="form-input" type="tel" name="phone" value="<?= e($form['phone']) ?>"
                 placeholder="+7 (___) ___-__-__" data-dadata="phone" data-clean data-phone-mask required>
          <span class="dadata-status"></span>
        </div>

        <div id="animatorFields" class="animator-fields"<?= $form['role'] === 'animator' ? '' : ' hidden' ?>>
          <div class="form-row">
            <div class="form-group">
              <label>Специализация</label>
              <select class="form-input" name="specialization">
                <?php foreach (['Детские праздники', 'Шоу-программы', 'Квесты'] as $spec): ?>
                  <option value="<?= e($spec) ?>" <?= $form['specialization'] === $spec ? 'selected' : '' ?>><?= e($spec) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Опыт, лет</label>
              <input class="form-input" type="number" name="experience_years" min="0" max="60" value="<?= e($form['experience_years']) ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Ставка за час, ₽</label>
            <input class="form-input" type="number" name="hourly_rate" min="0" step="100" value="<?= e($form['hourly_rate']) ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Пароль <span class="req">*</span></label>
            <input class="form-input" id="password" type="password" name="password" placeholder="Не менее 6 символов" required minlength="6">
          </div>
          <div class="form-group">
            <label>Повторите пароль <span class="req">*</span></label>
            <input class="form-input" id="password2" type="password" name="password2" placeholder="Ещё раз" required minlength="6">
          </div>
        </div>

        <label class="check-row">
          <input type="checkbox" name="agree" value="1" <?= !empty($_POST['agree']) ? 'checked' : '' ?> required>
          <span>Согласен(а) на обработку персональных данных в соответствии с 152-ФЗ</span>
        </label>

        <button type="submit" class="form-btn">Зарегистрироваться</button>

        <p style="text-align:center;margin-top:14px;font-size:13px;color:#666">
          Уже есть аккаунт? <a href="login.php" style="color:#1a9ea0;font-weight:600">Войти</a>
        </p>
      </form>
    </div>
  </div>
</div>

<script>
  const roleInputs = document.querySelectorAll('input[name="role"]');
  const animatorFields = document.getElementById('animatorFields');
  roleInputs.forEach(function (input) {
    input.addEventListener('change', function () {
      animatorFields.hidden = input.value !== 'animator' || !input.checked;
    });
  });

  const regForm = document.getElementById('regForm');
  const p1 = document.getElementById('password');
  const p2 = document.getElementById('password2');
  if (regForm) {
    regForm.addEventListener('submit', function (e) {
      if (p1.value !== p2.value) {
        e.preventDefault();
        p2.setCustomValidity('Пароли не совпадают');
        p2.reportValidity();
      }
    });
    p2.addEventListener('input', function () { p2.setCustomValidity(''); });
  }
</script>
<?php require 'includes/footer.php'; ?>
