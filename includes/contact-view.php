<?php
/**
 *  includes/contact-view.php — секция с формой обратной связи.
 *  Подключается из /contact и со страницы «Контакты» (/kontakty).
 *  Ожидает: $CONFIG, $settings, $dataDir, $err, $ok, $kv_form_action (url формы).
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

// CSRF-токен формы (сессия для гостя — только токен)// CSRF-токен формы (сессия для гостя — только токен)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}
$csrf = kv_generate_csrf();

require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/header.php';
?>
<section class="page-hero">
    <div class="container">
        <p class="kicker">Мы всегда на связи</p>
        <h1 class="display page-hero-title">Связаться с нами</h1>
        <p class="hero-sub">Анкеты для сотрудничества, заказ концертов, вопросы по билетам — напишите нам.</p>
    </div>
</section>

<section class="section">
    <div class="container contact-grid">
        <form class="contact-form glass" method="post" action="<?= kv_e($kv_form_action ?? kv_url('contact')) ?>" novalidate>
            <?php if ($ok): ?>
                <div class="form-note is-success" role="status">✔ Спасибо! Сообщение отправлено — ответим в течение двух рабочих дней.</div>
            <?php elseif ($err !== ''): ?>
                <div class="form-note is-error" role="alert"><?= kv_e($err) ?></div>
            <?php endif; ?>

            <input type="hidden" name="csrf" value="<?= kv_e($csrf) ?>">
            <!-- honeypot: скрыт от людей, приманка для ботов -->
            <div class="hp-field" aria-hidden="true"><label>Фирма<input type="text" name="company_hp" tabindex="-1" autocomplete="off"></label></div>

            <label class="field">
                <span class="field-label">Ваше имя *</span>
                <input class="field-input" type="text" name="name" required maxlength="120"
                       value="<?= kv_e($_POST['name'] ?? '') ?>" autocomplete="name">
            </label>
            <label class="field">
                <span class="field-label">E-mail *</span>
                <input class="field-input" type="email" name="email" required maxlength="160"
                       value="<?= kv_e($_POST['email'] ?? '') ?>" autocomplete="email"
                       placeholder="you@example.com">
            </label>
            <label class="field">
                <span class="field-label">Сообщение *</span>
                <textarea class="field-input" name="message" rows="6" required minlength="10" maxlength="3000"
                          placeholder="Опишите ваш вопрос или предложение…"><?= kv_e($_POST['message'] ?? '') ?></textarea>
            </label>
            <p class="muted small">Нажимая «Отправить», вы соглашаетесь с обработкой обращения.</p>
            <button class="btn btn-primary btn-lg shine" type="submit">Отправить сообщение</button>
        </form>

        <aside class="contact-side">
            <h2 class="section-title display">Реквизиты и контакты</h2>
            <dl class="contacts-list">
                <dt>Адрес</dt><dd><?= kv_e($settings['address'] ?? '') ?></dd>
                <dt>Телефон</dt><dd><a href="tel:<?= kv_e($settings['phone_raw'] ?? '') ?>"><?= kv_e($settings['phone'] ?? '') ?></a></dd>
                <dt>E-mail</dt><dd><a href="mailto:<?= kv_e($settings['email'] ?? '') ?>"><?= kv_e($settings['email'] ?? '') ?></a></dd>
                <dt>Часы работы</dt><dd><?= kv_e($settings['hours'] ?? '') ?></dd>
                <dt>Директор</dt><dd><?= kv_e($settings['director'] ?? '') ?></dd>
            </dl>
            <?php if (!empty($settings['vk_url'])): ?>
                <a class="social-link" href="<?= kv_e($settings['vk_url']) ?>" target="_blank" rel="noopener">Наша группа ВКонтакте →</a>
            <?php endif; ?>
            <a class="btn btn-gold magnetic" href="<?= kv_e($settings['ticket_url'] ?? '#') ?>" target="_blank" rel="noopener">Купить билет</a>
        </aside>
    </div>
</section>
<?php
require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/footer.php';
