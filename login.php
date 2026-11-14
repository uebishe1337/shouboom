<?php
require 'db.php';
verify_csrf();

$error = '';
$login = '';
$next  = safe_next($_GET['next'] ?? $_POST['next'] ?? '', 'cabinet.php');

if (current_user()) {
    redirect($next);
}

if (is_post()) {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Заполните все поля.';
    } elseif (!filter_var($login, FILTER_VALIDATE_EMAIL) && strlen(preg_replace('/\D+/', '', $login)) < 10) {
        $error = 'Введите корректный email или телефон.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT user_id, name, email, phone, password, role
             FROM users WHERE email = ? OR phone = ? LIMIT 1'
        );
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            login_user($user);
            flash('success', 'Вы успешно вошли, ' . $user['name'] . '!');
            if ($user['role'] === 'admin') {
                redirect('admin/index.php');
            }
            redirect($next);
        }
        $error = 'Неверный email/телефон или пароль.';
    }
}

$pageTitle = 'Вход';
$active = '';
require 'includes/header.php';
?>
<div class="page-wrap">
  <div class="page-inner">
    <h1 class="section-title-center">Вход в аккаунт</h1>
    <p class="section-sub-center">Войдите, чтобы управлять заказами и бронированиями</p>

    <div class="auth-wrap" style="max-width:440px">
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <form method="POST" action="" novalidate>
        <?php csrf_field(); ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <div class="form-group">
          <label>Email или телефон <span class="req">*</span></label>
          <input class="form-input" type="text" name="login" value="<?= e($login) ?>"
                 placeholder="example@mail.ru или +7 (___) ___-__-__" required autofocus>
        </div>

        <div class="form-group">
          <label>Пароль <span class="req">*</span></label>
          <input class="form-input" type="password" name="password" placeholder="Введите пароль" required minlength="6">
        </div>

        <button type="submit" class="form-btn">Войти</button>

        <p style="text-align:center;margin-top:14px;font-size:13px;color:#666">
          Нет аккаунта? <a href="register.php" style="color:#1a9ea0;font-weight:600">Зарегистрироваться</a>
        </p>
      </form>

      <div class="demo-box">
        <div class="demo-title">Демо-доступ</div>
        <div class="demo-row"><b>Администратор:</b> admin@shouboom.ru / admin123</div>
        <div class="demo-row"><b>Заказчик:</b> maria@example.ru / 123456</div>
        <div class="demo-row"><b>Аниматор:</b> alexey@shouboom.ru / 123456</div>
      </div>
    </div>
  </div>
</div>
<?php require 'includes/footer.php'; ?>
