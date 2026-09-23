<?php
/**
 * ============================================================
 *  newpass.php — ГЕНЕРАТОР ХЭША ДЛЯ ПАРОЛЯ АДМИНА
 * ============================================================
 *  Как пользоваться (это же описано в README_ДЛЯ_АДМИНА.md):
 *   1. Скопируйте сайт на сервер как обычно.
 *   2. Откройте в браузере:  ваш-сайт.ру/newpass.php
 *   3. Введите новый пароль и нажмите «Сгенерировать».
 *   4. Скопируйте полученный хэш в файл config.php
 *      (строка 'pass_hash' => '...').
 *   5. ОБЯЗАТЕЛЬНО удалите файл newpass.php с сервера!
 * ============================================================
 */
$hash = '';
$pass = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string)($_POST['password'] ?? '');
    if (mb_strlen($pass) >= 8) {
        // password_hash сам добавит «соль» и алгоритм bcrypt
        $hash = password_hash($pass, PASSWORD_BCRYPT);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Генератор пароля — Казачья Воля</title>
<style>
body{font-family:Arial,sans-serif;background:#FAFAFA;color:#1A1A1A;max-width:560px;margin:60px auto;padding:0 20px}
.card{background:#fff;border-radius:14px;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
h1{color:#800020;font-size:1.4rem}
input{width:100%;padding:12px;font-size:1rem;border:1px solid #ccc;border-radius:8px;margin:8px 0 16px}
button{background:#800020;color:#fff;border:0;padding:14px 24px;font-size:1rem;border-radius:999px;cursor:pointer}
code{display:block;background:#f2efe9;padding:14px;border-radius:8px;word-break:break-all;font-size:.9rem}
.warn{color:#800020;font-weight:bold}
.ok{color:#1d7a1d}
</style>
</head>
<body>
<div class="card">
    <h1>🔑 Генератор хэша для config.php</h1>
    <p>Введите новый пароль администратора (минимум 8 символов), скопируйте результат в <b>config.php</b>.</p>
    <form method="post">
        <label>Новый пароль:</label>
        <input type="password" name="password" required minlength="8" autocomplete="off">
        <button type="submit">Сгенерировать</button>
    </form>
    <?php if ($hash !== ''): ?>
        <p class="ok">✅ Готово! Вставьте эту строку в config.php вместо старой:</p>
        <code>'pass_hash' =&gt; '<?= htmlspecialchars($hash, ENT_QUOTES) ?>',</code>
        <p class="warn">⚠️ После смены пароля УДАЛИТЕ файл newpass.php с сервера!</p>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <p class="warn">Пароль слишком короткий — нужно не менее 8 символов.</p>
    <?php endif; ?>
</div>
</body>
</html>
