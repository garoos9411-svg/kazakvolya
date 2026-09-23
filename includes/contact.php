<?php
/**
 * ============================================================
 *  includes/contact.php — страница «Контакты + форма» (/contact)
 *  Сообщения сохраняются в data/messages.json (читаются в админке).
 *  Защита: CSRF-токен, honeypot, rate-limit: 1 сообщение в минуту с IP.
 * ============================================================
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

$msgFile = $dataDir . '/messages.json';
$err = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
        session_start();
    }
    // honeypot: заполненное скрытое поле = бот
    if (!empty($_POST['company_hp'] ?? '')) { $ok = true; } // тихо делаем вид, что отправили
    elseif (!kv_verify_csrf($_POST['csrf'] ?? '')) {
        $err = 'Сессия истекла, обновите страницу и попробуйте ещё раз.';
    } else {
        // rate limit: не чаще 1 сообщения в минуту с IP
        $rlFile = $dataDir . '/msg_rl.json';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
        $rl = kv_read_json($rlFile);
        $last = (int)($rl[$ip] ?? 0);
        if (time() - $last < 60) {
            $err = 'Вы отправляете слишком часто. Подождите минуту.';
        } else {
            $name  = kv_clean_string($_POST['name'] ?? '');
            $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
            $text  = kv_clean_string($_POST['message'] ?? '');
            if ($name === '' || mb_strlen($name) < 2)      $err = 'Укажите, как к вам обращаться.';
            elseif (!$email)                                $err = 'Проверьте адрес электронной почты.';
            elseif (mb_strlen($text) < 10)                  $err = 'Сообщение слишком короткое (минимум 10 символов).';
            elseif (mb_strlen($text) > 3000)                $err = 'Сообщение слишком длинное (максимум 3000 символов).';
            else {
                $msgs = kv_read_json($msgFile);
                $msgs[] = [
                    'id' => count($msgs) + 1, 'time' => date('Y-m-d H:i:s'),
                    'name' => $name, 'email' => $email, 'text' => $text,
                ];
                kv_write_json($msgFile, array_slice($msgs, -200));
                $rl[$ip] = time();
                kv_write_json($rlFile, $rl);
                $ok = true;
            }
        }
    }
}

// CSRF-токен формы (сессия для гостя — только токен)
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
        <form class="contact-form glass" method="post" action="<?= kv_e(kv_url('contact')) ?>" novalidate>
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
