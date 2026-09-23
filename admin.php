<?php
/**
 * ============================================================
 *  admin.php — мини-CMS сайта «Казачья Воля» (один файл)
 * ============================================================
 *  Возможности:
 *   - вход по логину/паролю (хэш в config.php), защита от перебора;
 *   - редактирование настроек (settings.json);
 *   - добавление / редактирование / удаление новостей (news.json);
 *   - добавление / редактирование / удаление афиши (afisha.json).
 *
 *  Все данные при выводе экранируются htmlspecialchars(),
 *  запись в JSON идёт через file_put_contents(..., LOCK_EX).
 * ============================================================
 */

declare(strict_types=1);

$CONFIG = require __DIR__ . '/config.php';
require  __DIR__ . '/includes/helpers.php';

$dataDir = $CONFIG['paths']['data'];

/* ---------- Сессия администратора ---------- */
session_name($CONFIG['security']['session_name']);
session_start();
// Безопасные cookie-настройки сессии
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);

$isLogged = !empty($_SESSION['kv_admin']);

/* ---------- Выход ---------- */
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

/* ---------- Вход: проверка логина/пароля + защита от брутфорса ---------- */
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {

    // Простая блокировка после N неверных попыток
    $tries = $_SESSION['kv_tries'] ?? ['count' => 0, 'until' => 0];
    if ($tries['count'] >= $CONFIG['security']['max_login_tries'] && time() < $tries['until']) {
        $loginError = 'Слишком много попыток. Подождите пару минут.';
    } else {
        $login = kv_clean_string($_POST['login'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');
        if ($login === $CONFIG['admin']['login']
            && password_verify($pass, $CONFIG['admin']['pass_hash'])) {
            session_regenerate_id(true);          // новая сессия после входа
            $_SESSION['kv_admin'] = true;
            $_SESSION['kv_tries'] = null;
            $isLogged = true;
        } else {
            $tries['count']++;
            $tries['until'] = time() + $CONFIG['security']['lock_time'];
            $_SESSION['kv_tries'] = $tries;
            $loginError = 'Неверный логин или пароль.';
        }
    }
}

/* ---------- Обработчики сохранения (только для вошедших) ---------- */
$flash = '';
if ($isLogged && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* --- Настройки сайта (settings.json) --- */
    if ($action === 'save_settings') {
        $settings = kv_read_json("$dataDir/settings.json");
        foreach (['site_name','tagline','address','phone','email','hours',
                  'director','copyright','phone_raw'] as $key) {
            if (array_key_exists($key, $_POST)) {
                $settings[$key] = kv_clean_string($_POST[$key]);
            }
        }
        foreach (['vk_url','ticket_url'] as $key) {
            if (array_key_exists($key, $_POST)) {
                $settings[$key] = kv_clean_url($_POST[$key]);
            }
        }
        $flash = kv_write_json("$dataDir/settings.json", $settings)
               ? '✅ Настройки сохранены.' : '⚠️ Не удалось записать файл настроек.';
    }

    /* --- Новости (news.json): добавить / изменить / удалить --- */
    if ($action === 'save_news') {
        $news = kv_read_json("$dataDir/news.json");
        $id   = (int)($_POST['id'] ?? 0);
        $item = [
            'id'         => $id ?: (int)(max(array_column($news, 'id')) ?: 0) + 1,
            'date'       => kv_clean_string($_POST['date'] ?? date('Y-m-d')),
            'title'      => kv_clean_string($_POST['title'] ?? ''),
            'image'      => kv_clean_string($_POST['image'] ?? 'theme/img/news-placeholder.svg'),
            'image_alt'  => kv_clean_string($_POST['image_alt'] ?? ''),
            'text'       => kv_clean_string($_POST['text'] ?? ''),
        ];
        if ($item['title'] === '') {
            $flash = '⚠️ Заголовок новости обязателен.';
        } else {
            $found = false;
            foreach ($news as $i => $n) {
                if ((int)($n['id'] ?? 0) === $item['id']) { $news[$i] = $item; $found = true; break; }
            }
            if (!$found) { array_unshift($news, $item); } // новые — сверху
            usort($news, fn($a, $b) => strcmp($b['date'], $a['date'])); // по дате
            $flash = kv_write_json("$dataDir/news.json", $news)
                   ? '✅ Новость сохранена.' : '⚠️ Ошибка записи news.json.';
        }
    }
    if ($action === 'delete_news') {
        $id   = (int)($_POST['id'] ?? 0);
        $news = array_values(array_filter(
            kv_read_json("$dataDir/news.json"), fn($n) => (int)($n['id'] ?? 0) !== $id
        ));
        $flash = kv_write_json("$dataDir/news.json", $news)
               ? '🗑 Новость удалена.' : '⚠️ Ошибка удаления.';
    }

    /* --- Афиша (afisha.json) --- */
    if ($action === 'save_afisha') {
        $afisha = kv_read_json("$dataDir/afisha.json");
        $id     = (int)($_POST['id'] ?? 0);
        $item = [
            'id'         => $id ?: (int)(max(array_column($afisha, 'id')) ?: 0) + 1,
            'date'       => kv_clean_string($_POST['date'] ?? ''),
            'time'       => kv_clean_string($_POST['time'] ?? '18:00'),
            'title'      => kv_clean_string($_POST['title'] ?? ''),
            'venue'      => kv_clean_string($_POST['venue'] ?? ''),
            'price'      => kv_clean_string($_POST['price'] ?? ''),
            'ticket_url' => kv_clean_url($_POST['ticket_url'] ?? ''),
        ];
        if ($item['title'] === '' || $item['date'] === '') {
            $flash = '⚠️ Укажите название и дату мероприятия.';
        } else {
            $found = false;
            foreach ($afisha as $i => $a) {
                if ((int)($a['id'] ?? 0) === $item['id']) { $afisha[$i] = $item; $found = true; break; }
            }
            if (!$found) { $afisha[] = $item; }
            usort($afisha, fn($a, $b) => strcmp($b['date'], $a['date']));
            $flash = kv_write_json("$dataDir/afisha.json", $afisha)
                   ? '✅ Мероприятие сохранено.' : '⚠️ Ошибка записи afisha.json.';
        }
    }
    if ($action === 'delete_afisha') {
        $id     = (int)($_POST['id'] ?? 0);
        $afisha = array_values(array_filter(
            kv_read_json("$dataDir/afisha.json"), fn($a) => (int)($a['id'] ?? 0) !== $id
        ));
        $flash = kv_write_json("$dataDir/afisha.json", $afisha)
               ? '🗑 Мероприятие удалено.' : '⚠️ Ошибка удаления.';
    }
}

/* ---------- Данные для форм ---------- */
$settings = kv_read_json("$dataDir/settings.json");
$news     = kv_read_json("$dataDir/news.json");
$afisha   = kv_read_json("$dataDir/afisha.json");
$editNews   = isset($_GET['edit_news'])   ? (fn($l) => reset($l))(array_filter($news,   fn($n) => (int)($n['id'] ?? 0) === (int)$_GET['edit_news']))   : null;
$editAfisha = isset($_GET['edit_afisha']) ? (fn($l) => reset($l))(array_filter($afisha, fn($a) => (int)($a['id'] ?? 0) === (int)$_GET['edit_afisha'])) : null;

$tab = in_array($_GET['tab'] ?? '', ['news','afisha','settings'], true) ? $_GET['tab'] : 'news';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Админ-панель — Казачья Воля</title>
<link rel="stylesheet" href="theme/css/style.min.css?v=1">
<style>
    /* Дополнительные стили именно для админки (в одном месте) */
    body{background:#F2F1EE}
    .admin-wrap{max-width:860px;margin:0 auto;padding:20px}
    .admin-card{background:#fff;border-radius:14px;padding:24px;margin-bottom:20px;
                box-shadow:0 2px 12px rgba(26,26,26,.07)}
    .admin-tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
    .admin-tabs a{padding:9px 18px;border-radius:999px;text-decoration:none;
                  color:#1A1A1A;background:#fff;border:1px solid #ddd;font-weight:500}
    .admin-tabs a.on{background:#800020;color:#fff;border-color:#800020}
    .form-row{margin-bottom:14px}
    .form-row label{display:block;font-weight:600;margin-bottom:5px;font-size:.92rem}
    .form-row input,.form-row textarea{width:100%;padding:10px 12px;font:inherit;
        border:1px solid #ccc;border-radius:8px;background:#fff}
    .form-row textarea{min-height:120px;resize:vertical}
    table.admin-list{width:100%;border-collapse:collapse;font-size:.93rem}
    table.admin-list td,table.admin-list th{padding:9px 8px;border-bottom:1px solid #eee;text-align:left}
    .btn-del{color:#800020;background:none;border:none;cursor:pointer;font:inherit;text-decoration:underline}
    .login-box{max-width:380px;margin:12vh auto}
    .error{color:#800020;font-weight:600;margin:10px 0}
    .flash{background:#eef7ee;border:1px solid #bfdcbf;color:#1d5c1d;
           padding:12px 16px;border-radius:10px;margin-bottom:18px}
</style>
</head>
<body>
<div class="admin-wrap">

<?php if (!$isLogged): ?>

    <!-- ================= ФОРМА ВХОДА ================= -->
    <div class="admin-card login-box">
        <h1 style="font-family:Georgia,serif;color:#800020">Вход в админ-панель</h1>
        <p class="muted" style="color:#777;font-size:.9rem">ГБУК ГАПП «Казачья Воля»</p>
        <?php if ($loginError): ?><p class="error"><?= kv_e($loginError) ?></p><?php endif; ?>
        <form method="post" action="admin.php">
            <input type="hidden" name="action" value="login">
            <div class="form-row">
                <label for="l">Логин</label>
                <input id="l" name="login" required autocomplete="username">
            </div>
            <div class="form-row">
                <label for="p">Пароль</label>
                <input id="p" name="password" type="password" required autocomplete="current-password">
            </div>
            <button class="btn btn-primary btn-block" type="submit">Войти</button>
        </form>
    </div>

<?php else: ?>

    <!-- ================= САМИ ПАНЕЛЬ ================= -->
    <div class="admin-card" style="display:flex;justify-content:space-between;align-items:center">
        <strong>⚙️ Мини-CMS · Казачья Воля</strong>
        <span>
            <a href="index.php" target="_blank">На сайт ↗</a> &nbsp;|&nbsp;
            <a href="admin.php?logout=1">Выйти</a>
        </span>
    </div>

    <?php if ($flash): ?><div class="flash"><?= $flash /* содержимое формируем сами, безопасно */ ?></div><?php endif; ?>

    <div class="admin-tabs">
        <a class="<?= $tab==='news'?'on':'' ?>"    href="admin.php?tab=news">📰 Новости</a>
        <a class="<?= $tab==='afisha'?'on':'' ?>"  href="admin.php?tab=afisha">🎭 Афиша</a>
        <a class="<?= $tab==='settings'?'on':'' ?>" href="admin.php?tab=settings">⚙️ Настройки</a>
    </div>

    <?php /* ---------- ВКЛАДКА: НОВОСТИ ---------- */ ?>
    <?php if ($tab === 'news'): $n = $editNews ?? []; ?>
        <div class="admin-card">
            <h2><?= $editNews ? 'Редактировать новость' : 'Добавить новость' ?></h2>
            <form method="post" action="admin.php?tab=news">
                <input type="hidden" name="action" value="save_news">
                <input type="hidden" name="id" value="<?= (int)($n['id'] ?? 0) ?>">
                <div class="form-row"><label>Заголовок *</label>
                    <input name="title" required maxlength="200" value="<?= kv_e($n['title'] ?? '') ?>"></div>
                <div class="form-row"><label>Дата публикации</label>
                    <input name="date" type="date" value="<?= kv_e($n['date'] ?? date('Y-m-d')) ?>"></div>
                <div class="form-row"><label>Картинка (путь)</label>
                    <input name="image" value="<?= kv_e($n['image'] ?? 'theme/img/news-placeholder.svg') ?>">
                    <small style="color:#777">Загрузите фото в папку theme/img/ и укажите путь, напр. theme/img/koncerty.jpg</small></div>
                <div class="form-row"><label>Описание картинки (alt)</label>
                    <input name="image_alt" maxlength="150" value="<?= kv_e($n['image_alt'] ?? '') ?>"></div>
                <div class="form-row"><label>Текст новости</label>
                    <textarea name="text"><?= kv_e($n['text'] ?? '') ?></textarea></div>
                <button class="btn btn-primary" type="submit">💾 Сохранить</button>
                <?php if ($editNews): ?><a class="btn btn-outline" href="admin.php?tab=news">Отмена</a><?php endif; ?>
            </form>
        </div>

        <div class="admin-card">
            <h2>Все новости (<?= count($news) ?>)</h2>
            <table class="admin-list">
                <tr><th>Дата</th><th>Заголовок</th><th></th></tr>
                <?php foreach ($news as $item): ?>
                <tr>
                    <td><?= kv_e($item['date'] ?? '') ?></td>
                    <td><?= kv_e(mb_strimwidth($item['title'] ?? '', 0, 60, '…')) ?></td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="admin.php?tab=news&amp;edit_news=<?= (int)$item['id'] ?>">Изменить</a>
                        &nbsp;
                        <form method="post" action="admin.php?tab=news" style="display:inline"
                              onsubmit="return confirm('Удалить новость безвозвратно?')">
                            <input type="hidden" name="action" value="delete_news">
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <button class="btn-del" type="submit">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <?php /* ---------- ВКЛАДКА: АФИША ---------- */ ?>
    <?php if ($tab === 'afisha'): $a = $editAfisha ?? []; ?>
        <div class="admin-card">
            <h2><?= $editAfisha ? 'Редактировать мероприятие' : 'Добавить мероприятие' ?></h2>
            <form method="post" action="admin.php?tab=afisha">
                <input type="hidden" name="action" value="save_afisha">
                <input type="hidden" name="id" value="<?= (int)($a['id'] ?? 0) ?>">
                <div class="form-row"><label>Название *</label>
                    <input name="title" required maxlength="200" value="<?= kv_e($a['title'] ?? '') ?>"></div>
                <div class="form-row"><label>Дата *</label>
                    <input name="date" type="date" required value="<?= kv_e($a['date'] ?? '') ?>"></div>
                <div class="form-row"><label>Время</label>
                    <input name="time" type="time" value="<?= kv_e($a['time'] ?? '18:00') ?>"></div>
                <div class="form-row"><label>Место проведения</label>
                    <input name="venue" maxlength="200" value="<?= kv_e($a['venue'] ?? '') ?>"></div>
                <div class="form-row"><label>Цены</label>
                    <input name="price" maxlength="60" value="<?= kv_e($a['price'] ?? 'от 500 ₽') ?>"></div>
                <div class="form-row"><label>Ссылка на билеты (kassir.ru)</label>
                    <input name="ticket_url" type="url" placeholder="https://www.kassir.ru/..."
                           value="<?= kv_e($a['ticket_url'] ?? '') ?>"></div>
                <button class="btn btn-primary" type="submit">💾 Сохранить</button>
                <?php if ($editAfisha): ?><a class="btn btn-outline" href="admin.php?tab=afisha">Отмена</a><?php endif; ?>
            </form>
        </div>

        <div class="admin-card">
            <h2>Все мероприятия (<?= count($afisha) ?>)</h2>
            <table class="admin-list">
                <tr><th>Дата</th><th>Название</th><th>Билеты</th><th></th></tr>
                <?php foreach ($afisha as $item): ?>
                <tr>
                    <td><?= kv_e($item['date'] ?? '') ?></td>
                    <td><?= kv_e(mb_strimwidth($item['title'] ?? '', 0, 50, '…')) ?></td>
                    <td><?= !empty($item['ticket_url']) ? '<a href="'.kv_e($item['ticket_url']).'" target="_blank">↗</a>' : '—' ?></td>
                    <td style="text-align:right;white-space:nowrap">
                        <a href="admin.php?tab=afisha&amp;edit_afisha=<?= (int)$item['id'] ?>">Изменить</a>
                        &nbsp;
                        <form method="post" action="admin.php?tab=afisha" style="display:inline"
                              onsubmit="return confirm('Удалить мероприятие?')">
                            <input type="hidden" name="action" value="delete_afisha">
                            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                            <button class="btn-del" type="submit">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <?php /* ---------- ВКЛАДКА: НАСТРОЙКИ ---------- */ ?>
    <?php if ($tab === 'settings'): ?>
        <div class="admin-card">
            <h2>Основные настройки</h2>
            <form method="post" action="admin.php?tab=settings">
                <input type="hidden" name="action" value="save_settings">
                <div class="form-row"><label>Название учреждения</label>
                    <input name="site_name" value="<?= kv_e($settings['site_name'] ?? '') ?>"></div>
                <div class="form-row"><label>Слоган / подзаголовок</label>
                    <input name="tagline" value="<?= kv_e($settings['tagline'] ?? '') ?>"></div>
                <div class="form-row"><label>Адрес</label>
                    <input name="address" value="<?= kv_e($settings['address'] ?? '') ?>"></div>
                <div class="form-row"><label>Телефон (как показывать)</label>
                    <input name="phone" value="<?= kv_e($settings['phone'] ?? '') ?>"></div>
                <div class="form-row"><label>Телефон для кнопки «Позвонить» (без пробелов)</label>
                    <input name="phone_raw" value="<?= kv_e($settings['phone_raw'] ?? '') ?>"></div>
                <div class="form-row"><label>E-mail</label>
                    <input name="email" type="email" value="<?= kv_e($settings['email'] ?? '') ?>"></div>
                <div class="form-row"><label>Часы работы</label>
                    <input name="hours" value="<?= kv_e($settings['hours'] ?? '') ?>"></div>
                <div class="form-row"><label>Директор</label>
                    <input name="director" value="<?= kv_e($settings['director'] ?? '') ?>"></div>
                <div class="form-row"><label>Ссылка ВКонтакте</label>
                    <input name="vk_url" type="url" value="<?= kv_e($settings['vk_url'] ?? '') ?>"></div>
                <div class="form-row"><label>Общая ссылка на кассу (по кнопкам «Купить билет»)</label>
                    <input name="ticket_url" type="url" value="<?= kv_e($settings['ticket_url'] ?? '') ?>"></div>
                <div class="form-row"><label>Подпись в футере (копирайт)</label>
                    <input name="copyright" value="<?= kv_e($settings['copyright'] ?? '') ?>"></div>
                <button class="btn btn-primary" type="submit">💾 Сохранить настройки</button>
            </form>
        </div>
    <?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>
