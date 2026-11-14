<?php
require 'db.php';
verify_csrf();

$errors = [];
$form = ['name' => '', 'email' => '', 'phone' => '', 'message' => ''];

if (is_post()) {
    foreach ($form as $key => $value) {
        $form[$key] = trim((string)($_POST[$key] ?? ''));
    }
    if (mb_strlen($form['name']) < 2) {
        $errors[] = 'Укажите имя.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Укажите корректный email.';
    }
    if (mb_strlen($form['message']) < 10) {
        $errors[] = 'Сообщение должно быть не короче 10 символов.';
    }
    if (!$errors) {
        $ins = $pdo->prepare('INSERT INTO contact_requests (name, email, phone, message) VALUES (?, ?, ?, ?)');
        $ins->execute([$form['name'], $form['email'], $form['phone'], $form['message']]);
        flash('success', 'Сообщение отправлено. Мы свяжемся с вами в течение рабочего дня.');
        redirect('contacts.php');
    }
}

$pageTitle = 'Контакты';
$active = 'contacts';
require 'includes/header.php';
?>
<div class="page-wrap">
  <div class="page-inner">
    <h1 class="section-title-center">Контакты</h1>
    <p class="section-sub-center">Свяжитесь с нами любым удобным способом</p>

    <div class="contacts-layout">
      <div class="contacts-card">
        <div class="contacts-card-title">Связь с нами</div>
        <div class="contact-row">📞 <a href="tel:+74951234567"><?= e(SITE_PHONE) ?></a></div>
        <div class="contact-row">✉️ <a href="mailto:<?= e(SITE_EMAIL) ?>"><?= e(SITE_EMAIL) ?></a></div>
        <div class="contact-row">📍 <?= e(SITE_ADDRESS) ?></div>
        <div class="contacts-work">
          <strong>Режим работы:</strong><br>
          Ежедневно с 9:00 до 21:00
        </div>

        <div class="contact-form-title">Написать нам</div>
        <?php if ($errors): ?>
          <div class="alert alert-error"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
          <?php csrf_field(); ?>
          <div class="form-group">
            <input class="form-input" type="text" name="name" value="<?= e($form['name']) ?>" placeholder="Ваше имя" data-dadata="fio" data-clean required>
          </div>
          <div class="form-group">
            <input class="form-input" type="email" name="email" value="<?= e($form['email']) ?>" placeholder="Email" data-dadata="email" data-clean required>
          </div>
          <div class="form-group">
            <input class="form-input" type="tel" name="phone" value="<?= e($form['phone']) ?>" placeholder="Телефон (необязательно)" data-phone-mask data-clean data-dadata="phone">
          </div>
          <div class="form-group">
            <textarea class="form-input" name="message" rows="4" placeholder="Ваш вопрос" required><?= e($form['message']) ?></textarea>
          </div>
          <button class="form-btn teal" type="submit">Отправить сообщение</button>
        </form>
      </div>

      <div class="map-wrap">
        <iframe
          src="https://yandex.ru/map-widget/v1/?um=constructor%3A&amp;source=constructor&amp;ll=37.606430%2C55.762690&amp;z=16&amp;pt=37.606430%2C55.762690"
          width="100%" height="320" frameborder="0" allowfullscreen="true"
          style="border-radius:10px;display:block;" title="Карта: Москва, Тверская 15"></iframe>
        <div class="map-links">
          <a href="https://yandex.ru/maps/?text=%D0%A2%D0%B2%D0%B5%D1%80%D1%81%D0%BA%D0%B0%D1%8F+%D1%83%D0%BB.%2C+15%2C+%D0%9C%D0%BE%D1%81%D0%BA%D0%B2%D0%B0" target="_blank" rel="noopener" class="map-btn">🗺 Яндекс.Карты</a>
          <a href="https://2gis.ru/moscow/search/%D0%A2%D0%B2%D0%B5%D1%80%D1%81%D0%BA%D0%B0%D1%8F+15" target="_blank" rel="noopener" class="map-btn">📍 2GIS</a>
          <a href="https://maps.google.com/?q=%D0%A2%D0%B2%D0%B5%D1%80%D1%81%D0%BA%D0%B0%D1%8F+%D1%83%D0%BB.+15+%D0%9C%D0%BE%D1%81%D0%BA%D0%B2%D0%B0" target="_blank" rel="noopener" class="map-btn">🌐 Google Maps</a>
        </div>

        <div class="map-address-block">
          <div class="map-address-icon">🏢</div>
          <div>
            <div class="map-address-title">Офис ШоуБум</div>
            <div class="map-address-value"><?= e(SITE_ADDRESS) ?></div>
            <div class="map-address-index">5 минут от м. Тверская / Пушкинская</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require 'includes/footer.php'; ?>
