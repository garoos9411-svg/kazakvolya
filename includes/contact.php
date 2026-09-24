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

if (!empty($kv_contact_post) && $_SERVER['REQUEST_METHOD'] === 'POST') {
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


// --- Рендер полноценной страницы /contact ---
require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/header.php';
if (!empty($current['blocks'])) {
    // страница «Контакты» из админки: сначала её блоки…
    foreach ($current['blocks'] as $b) { kv_render_block($b, $ctx); }
    $kv_form_action = kv_url('kontakty');
} else {
    // системная страница /contact
?>
<section class="page-hero">
    <div class="container">
        <p class="kicker">Мы всегда на связи</p>
        <h1 class="display page-hero-title">Связаться с нами</h1>
        <p class="hero-sub">Анкеты для сотрудничества, заказ концертов, вопросы по билетам — напишите нам.</p>
    </div>
</section>
<?php
    $kv_form_action = kv_url('contact');
}
require __DIR__ . '/contact-view.php';
require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/footer.php';
