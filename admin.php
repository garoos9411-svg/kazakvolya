<?php
/**
 * ============================================================
 *  admin.php — панель управления сайтом «Казачья Воля» (v2)
 * ============================================================
 *  Что умеет:
 *   • Дашборд со сводкой и быстрыми действиями
 *   • СТРАНИЦЫ: создание / удаление / переименование ЛЮБЫХ
 *     страниц, порядок в меню, видимость + поблочный редактор
 *     (hero, текст, HTML-текст, цифры, галерея, команда,
 *      список, цитата, CTA, контакты, афиша, новости, медиа)
 *     с перемещением ↑↓, дублированием и удалением блоков
 *   • НОВОСТИ, АФИША, НАСТРОЙКИ — полноценные редакторы
 *   • Загрузка картинок в uploads/
 *   • Резервная копия всего data/ одним кликом
 *
 *  Защита: пароля в исходниках нет — хэш лежит в data/credentials.json.
 *  Сессия + CSRF-токены на всех формах. Все POST-действия проходят
 *  через kv_admin_check_post() (метод, токен, вход).
 *  Данные пишутся в JSON с LOCK_EX; при записи pages.json создаётся
 *  однотипная backup-копия data/pages.bak.json.
 * ============================================================
 */

define('KV_SITE', true);

$CONFIG = require __DIR__ . '/config.php';
require  __DIR__ . '/includes/helpers.php';

$dataDir = rtrim($CONFIG['paths']['data'], '/\\') . DIRECTORY_SEPARATOR;

/* ---------- Хранилище учётных данных (вне исходников!) ---------- */
$credFile = rtrim($dataDir, '/\\') . '/credentials.json';
// kv_credentials() сам создаст файл с паролем по умолчанию, если его нет
// или если формат повреждён (например, старым генератором).
$CRED = kv_credentials($credFile);
// Если хэш не проходит проверку формата bcrypt — тоже пересоздаём заново.
if (!preg_match('/^\$2y\$/', (string)$CRED['hash'])) {
    $CRED = [
        'user' => 'admin',
        'hash' => password_hash('admin123', PASSWORD_DEFAULT),
    ];
    kv_write_json($credFile, $CRED);
}

/* ---------- Старт защищённой сессии ---------- */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true, 'samesite' => 'Lax', 'path' => '/',
    ]);
    session_start();
}

$isLoggedIn     = !empty($_SESSION['kv_admin']);
$requiresLogout = !empty($CRED['force_logout']);

/* ---------- Вход: проверка лимита попыток ---------- */
$lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);
$isLocked    = time() < $lockedUntil;

/* ---------- Вспомогательные функции админки ---------- */

/** Простейшая защита от XSS при выводе в форму */
function a_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Проверка метода + CSRF + факта входа для любого POST */
function kv_admin_check_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    if (!kv_verify_csrf($_POST['csrf'] ?? '')) {
        kv_flash('error', 'Сессия истекла. Войдите и попробуйте снова.');
        header('Location: admin.php'); exit;
    }
    if (empty($_SESSION['kv_admin'])) {
        kv_flash('error', 'Требуется вход в админку.');
        header('Location: admin.php'); exit;
    }
}

/** Запись JSON c блокировкой + бэкап страниц */
function a_save(string $file, $data): bool
{
    global $dataDir;
    if ($file === 'pages.json' && is_file($dataDir . 'pages.json')) {
        @copy($dataDir . 'pages.json', $dataDir . 'pages.bak.json');
    }
    return kv_write_json($dataDir . $file, $data);
}

/** slug из заголовка (транслит) */
function a_slug(string $title): string
{
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z',
            'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
            'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s = mb_strtolower(trim($title), 'UTF-8');
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? $s : 'page-' . substr(md5((string)microtime(true)), 0, 6);
}

/** Очистка поля ссылки от javascript: и прочей гадости */
function a_url($v): string
{
    $v = trim((string)$v);
    if ($v === '') return '';
    if (preg_match('#^(https?://|mailto:|tel:|/|index\.php|\.)#i', $v)) return $v;
    return '#';
}

/** Числовой массив строк из textarea (по строкам) */
function a_lines($v): array
{
    $v = preg_split('/\R/u', str_replace("\r", '', (string)$v));
    return array_values(array_filter(array_map('trim', (array)$v), fn($x) => $x !== ''));
}

/** Поле формы → строка (обрезанная по разумной длине) */
function a_str($v, int $max = 5000): string
{
    return mb_substr(trim(strip_tags((string)$v)), 0, $max);
}

/** Загрузка файла изображения в uploads/, возвращает путь или null */
function a_upload_image(array $file): ?string
{
    global $CONFIG;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = $CONFIG['security']['upload_allowed'];
    $isRealImage = in_array($ext, ['jpg','jpeg','png','gif','webp'], true)
                || $ext === 'svg';
    if (!$isRealImage || $file['size'] > $CONFIG['security']['upload_max_size']) return null;
    // svg принимаем только если это действительно svg-разметка
    if ($ext === 'svg' && stripos(file_get_contents($file['tmp_name'], false, null, 0, 512), '<svg') === false) return null;

    $dir = $CONFIG['paths']['root'] . 'uploads/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) return null;
    return 'uploads/' . $name;
}

/** Присвоение значения по «пути» вида ['blocks'][0]['image'] */
function a_path_set(array &$arr, array $keys, $value): void
{
    $ref =& $arr;
    foreach ($keys as $k) {
        if (!isset($ref[$k]) || !is_array($ref[$k])) $ref[$k] = [];
        $ref =& $ref[$k];
    }
    $ref = $value;
}

/**
 * Обработка загруженных файлов формы. Ключ поля — путь внутри $_POST,
 * напр. name="upload[blocks][2][image]" => путь blocks.2.image.
 * Возвращает число успешно загруженных файлов.
 */
function a_process_uploads(array &$target): int
{
    $ok = 0;
    $stack = [];
    foreach (($_FILES['upload']['name'] ?? []) as $field => $nm) {
        if (!$nm) continue;
        $keys = preg_split('/\]\[/', trim((string)$field, '[]'));
        $path = a_upload_image([
            'tmp_name' => $_FILES['upload']['tmp_name'][$field] ?? '',
            'error'    => $_FILES['upload']['error'][$field]    ?? UPLOAD_ERR_NO_FILE,
            'size'     => $_FILES['upload']['size'][$field]     ?? 0,
            'name'     => $nm,
        ]);
        if ($path) {
            a_path_set($target, $keys, $path);
            kv_flash('success', "Файл {$nm} загружен.");
            $ok++;
        } else {
            kv_flash('error', "Не удалось загрузить {$nm}: допустимы jpg/png/gif/webp/svg до 5 МБ.");
        }
    }
    return $ok;
}

/** Пустой блок по умолчанию для каждого типа */
function a_blank_block(string $type): array
{
    switch ($type) {
        case 'hero':
            return ['type'=>'hero','kicker'=>'','title'=>'Заголовок','subtitle'=>'',
                    'image'=>'theme/img/hero.svg','image_alt'=>'',
                    'cta'=>['text'=>'','url'=>''],'cta2'=>['text'=>'','url'=>''],
                    'facts'=>[],'marquee'=>''];
        case 'page_header': return ['type'=>'page_header','kicker'=>'','title'=>'Заголовок страницы','subtitle'=>''];
        case 'text':        return ['type'=>'text','kicker'=>'','title'=>'','html'=>''];
        case 'rich_html':   return ['type'=>'rich_html','kicker'=>'','title'=>'','html'=>''];
        case 'numbers':     return ['type'=>'numbers','kicker'=>'','title'=>'','items'=>[['value'=>'','label'=>'']]];
        case 'gallery':     return ['type'=>'gallery','kicker'=>'','title'=>'','items'=>[['image'=>'theme/img/placeholder.svg','alt'=>'','caption'=>'']]];
        case 'team':        return ['type'=>'team','kicker'=>'','title'=>'','items'=>[['name'=>'','role'=>'','note'=>'','image'=>'theme/img/portrait.svg']]];
        case 'list':        return ['type'=>'list','kicker'=>'','title'=>'','items'=>[['name'=>'','note'=>'','meta'=>'']]];
        case 'quote':       return ['type'=>'quote','text'=>'','author'=>''];
        case 'cta':         return ['type'=>'cta','kicker'=>'','title'=>'','text'=>'','cta'=>['text'=>'','url'=>''],'cta2'=>['text'=>'','url'=>'']];
        case 'contacts':    return ['type'=>'contacts','kicker'=>'','title'=>'','text'=>'','map_html'=>''];
        case 'afisha':      return ['type'=>'afisha','kicker'=>'','title'=>'Ближайшие концерты'];
        case 'news':        return ['type'=>'news','kicker'=>'','title'=>'Новости'];
        case 'media':       return ['type'=>'media','kicker'=>'','title'=>'','image'=>'theme/img/placeholder.svg','image_alt'=>'','html'=>'','cta'=>['text'=>'','url'=>'']];
    }
    return ['type'=>'text'];
}

/** Человекочитаемые названия типов блоков */
const BLOCK_TYPES = [
    'hero'=>'Hero — главный экран', 'page_header'=>'Шапка страницы',
    'text'=>'Текст (абзацы)', 'rich_html'=>'Текст с HTML',
    'numbers'=>'Числа / достижения', 'gallery'=>'Галерея фото',
    'team'=>'Команда (карточки)', 'list'=>'Нумерованный список',
    'quote'=>'Цитата', 'cta'=>'Призыв к действию',
    'contacts'=>'Контакты + карта', 'afisha'=>'Афиша (из afisha.json)',
    'news'=>'Новости (из news.json)', 'media'=>'Фото + текст',
];

/* ============================================================
 *  ОБРАБОТКА POST (все действия — только после CSRF и входа)
 * ============================================================ */
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ---- вход (до CSRF-проверки токена сессии не существует) ---- */
    if ($action === 'login') {
        if ($isLocked) {
            kv_flash('error', 'Слишком много попыток. Подождите ещё немного.');
        } else {
            sleep(1); // простая противо brute-force пауза
            $u = a_str($_POST['username'] ?? '');
            $p = (string)($_POST['password'] ?? '');
            if (hash_equals($CRED['user'], $u) && password_verify($p, $CRED['hash'])) {
                session_regenerate_id(true);
                $_SESSION['kv_admin'] = true;
                unset($_SESSION['login_attempts']);
                if (!empty($CRED['force_logout'])) {
                    $CRED['force_logout'] = false;
                    kv_write_json($credFile, $CRED);
                }
                kv_flash('success', 'Добро пожаловать!');
                header('Location: admin.php'); exit;
            }
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= $CONFIG['security']['max_login_attempts']) {
                $_SESSION['login_locked_until'] = time() + $CONFIG['security']['lock_time'];
                $_SESSION['login_attempts'] = 0;
            }
            kv_flash('error', 'Неверный логин или пароль.');
        }
        header('Location: admin.php'); exit;
    }

    kv_admin_check_post(); // дальше все действия требуют авторизации + CSRF

    /* ---- выход ---- */
    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: admin.php'); exit;
    }

    /* ---- смещение блока вверх/вниз ---- */
    if ($action === 'block_move') {
        $pages = kv_read_json($dataDir . 'pages.json');
        $pi = (int)$_POST['page_index']; $bi = (int)$_POST['block_index'];
        $to = $bi + (int)$_POST['delta'];
        if (isset($pages[$pi]) && isset($pages[$pi]['blocks'][$bi]) && isset($pages[$pi]['blocks'][$to])) {
            $b = $pages[$pi]['blocks'];
            [$b[$bi], $b[$to]] = [$b[$to], $b[$bi]];
            $pages[$pi]['blocks'] = $b;
            a_save('pages.json', $pages);
        }
        header('Location: admin.php?page-edit=' . $pi); exit;
    }

    /* ---- дублирование блока ---- */
    if ($action === 'block_duplicate') {
        $pages = kv_read_json($dataDir . 'pages.json');
        $pi = (int)$_POST['page_index']; $bi = (int)$_POST['block_index'];
        if (isset($pages[$pi]['blocks'][$bi])) {
            $b = $pages[$pi]['blocks'];
            array_splice($b, $bi + 1, 0, [$b[$bi]]);
            $pages[$pi]['blocks'] = $b;
            a_save('pages.json', $pages);
            kv_flash('success', 'Блок продублирован.');
        }
        header('Location: admin.php?page-edit=' . $pi); exit;
    }

    /* ---- удаление блока ---- */
    if ($action === 'block_delete') {
        $pages = kv_read_json($dataDir . 'pages.json');
        $pi = (int)$_POST['page_index']; $bi = (int)$_POST['block_index'];
        if (isset($pages[$pi]['blocks'][$bi])) {
            $b = $pages[$pi]['blocks'];
            array_splice($b, $bi, 1);
            $pages[$pi]['blocks'] = $b;
            a_save('pages.json', $pages);
            kv_flash('success', 'Блок удалён.');
        }
        header('Location: admin.php?page-edit=' . $pi); exit;
    }

    /* ---- добавление нового блока ---- */
    if ($action === 'block_add') {
        $pages = kv_read_json($dataDir . 'pages.json');
        $pi = (int)$_POST['page_index'];
        $type = (string)$_POST['block_type'];
        if (isset($pages[$pi]) && isset(BLOCK_TYPES[$type])) {
            $pages[$pi]['blocks'][] = a_blank_block($type);
            a_save('pages.json', $pages);
            kv_flash('success', 'Блок «' . BLOCK_TYPES[$type] . '» добавлен — заполните его ниже.');
        }
        header('Location: admin.php?page-edit=' . $pi); exit;
    }

    /* ---- сохранение всей страницы (мета + все блоки разом) ---- */
    if ($action === 'page_save') {
        $pages = kv_read_json($dataDir . 'pages.json');
        $pi = (int)$_POST['page_index'];
        if (!isset($pages[$pi])) { header('Location: admin.php'); exit; }

        $title = a_str($_POST['title'] ?? '', 200) ?: 'Без названия';
        $slugIn = a_str($_POST['slug'] ?? '', 120);
        $slug = $slugIn !== '' ? a_slug($slugIn) : a_slug($title);

        /* Защита главной: slug страницы «home» менять нельзя —
           на него ссылается роутер index.php по умолчанию */
        $isHome = ($pages[$pi]['slug'] ?? '') === 'home' || $slug === 'home';
        if ($isHome) {
            $slug = 'home';
        } else {
            // уникальность slug (не считая текущей страницы)
            $taken = array_map(fn($p) => $p['slug'] ?? '', $pages);
            unset($taken[$pi]);
            if (in_array($slug, $taken, true)) {
                $base = $slug; $i = 2;
                while (in_array($slug, $taken, true)) { $slug = $base . '-' . $i++; }
            }
        }

        // загруженные картинки попадают прямо в массив блоков
        if (!empty($_FILES['upload']['name'])) {
            a_process_uploads($rawAll = &$_POST['blocks']);
        }
        $p = $pages[$pi];
        $p['title'] = $title;
        $p['menu_title'] = a_str($_POST['menu_title'] ?? '', 80) ?: $title;
        $p['slug'] = $slug;
        $p['visible'] = !empty($_POST['visible']);
        $oldCount = count($p['blocks'] ?? []);
        $newBlocks = [];
        for ($i = 0; $i < $oldCount; $i++) {
            $type = $p['blocks'][$i]['type'] ?? 'text';
            $raw = $_POST['blocks'][$i] ?? null;
            if (!is_array($raw)) { $newBlocks[] = $p['blocks'][$i]; continue; }
            $newBlocks[] = a_normalize_block($type, $raw, $p['blocks'][$i]);
        }
        $p['blocks'] = $newBlocks;
        $pages[$pi] = $p;
        a_save('pages.json', $pages);
        kv_flash('success', 'Страница «' . $title . '» сохранена.');
        header('Location: admin.php?page-edit=' . $pi); exit;
    }

    /* ---- новая страница ---- */
    if ($action === 'page_create') {
        $pages = kv_read_json($dataDir . 'pages.json');
        $title = a_str($_POST['title'] ?? '', 200) ?: 'Новая страница';
        $slug = a_slug(a_str($_POST['slug'] ?? '', 120) ?: $title);
        // «home» и «afisha/news» зарезервированы роутером — не даём создать дубль
        if ($slug === '' || in_array($slug, ['home', 'afisha', 'news'], true)) { $slug = 'page-' . (count($pages) + 1); }
        $taken = array_map(fn($p) => $p['slug'] ?? '', $pages);
        if (in_array($slug, $taken, true)) { $slug .= '-' . (count($pages) + 1); }
        $pages[] = [
            'slug'=>$slug,'title'=>$title,'menu_title'=>$title,'visible'=>true,
            'blocks'=>[
                ['type'=>'page_header','kicker'=>'','title'=>$title,'subtitle'=>''],
                ['type'=>'text','kicker'=>'','title'=>'','html'=>'Текст новой страницы…'],
            ],
        ];
        a_save('pages.json', $pages);
        kv_flash('success', 'Страница «' . $title . '» создана.');
        header('Location: admin.php?page-edit=' . (count($pages) - 1)); exit;
    }

    /* ---- удаление страницы ---- */
    if ($action === 'page_delete') {
        $pages = kv_read_json($dataDir . 'pages.json');
        $pi = (int)$_POST['page_index'];
        if (($pages[$pi]['slug'] ?? '') === 'home') {
            kv_flash('error', 'Главную страницу удалить нельзя.');
        } elseif (isset($pages[$pi])) {
            $t = $pages[$pi]['title'];
            array_splice($pages, $pi, 1);
            a_save('pages.json', $pages);
            kv_flash('success', 'Страница «' . $t . '» удалена.');
        }
        header('Location: admin.php'); exit;
    }

    /* ---- настройки ---- */
    if ($action === 'settings') {
        $settings = kv_read_json($dataDir . 'settings.json');
        $strFields = ['site_name','tagline','address','phone','email','hours',
                      'director','copyright','seo_description'];
        foreach ($strFields as $f) {
            if (array_key_exists($f, $_POST)) $settings[$f] = a_str($_POST[$f] ?? '', 500);
        }
        foreach (['phone_raw','vk_url','ticket_url'] as $f) {
            if (array_key_exists($f, $_POST)) $settings[$f] = a_url($_POST[$f]);
        }
        // видео на фоне главной: принимаем только http(s)/протокол-относительные mp4-ссылки
        if (array_key_exists('hero_video_url', $_POST)) {
            $v = trim((string)$_POST['hero_video_url']);
            $settings['hero_video_url'] = ($v !== '' && preg_match('#^(https?://|//)#i', $v)) ? $v : '';
        }
        if (array_key_exists('hero_poster', $_POST)) {
            $settings['hero_poster'] = a_str($_POST['hero_poster'], 300);
        }
        a_save('settings.json', $settings);
        kv_flash('success', 'Настройки сохранены.');
        header('Location: admin.php?section=settings'); exit;
    }

    /* ---- смена пароля ---- */
    if ($action === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $conf= (string)($_POST['new_confirm'] ?? '');
        if (!password_verify($cur, $CRED['hash'])) {
            kv_flash('error', 'Текущий пароль указан неверно.');
        } elseif (strlen($new) < 8) {
            kv_flash('error', 'Новый пароль должен быть не короче 8 символов.');
        } elseif ($new !== $conf) {
            kv_flash('error', 'Пароли не совпадают.');
        } else {
            $CRED['hash'] = password_hash($new, PASSWORD_DEFAULT);
            kv_write_json($credFile, $CRED);
            kv_flash('success', 'Пароль изменён.');
        }
        header('Location: admin.php?section=settings'); exit;
    }

    /* ---- новости ---- */
    if ($action === 'news_save') {
        $news = kv_read_json($dataDir . 'news.json');
        $id = (int)($_POST['id'] ?? 0);
        $item = [
            'date'  => a_str($_POST['date'] ?? date('Y-m-d'), 10),
            'title' => a_str($_POST['title'] ?? 'Без заголовка', 300),
            'image' => a_str($_POST['image'] ?? 'theme/img/placeholder.svg', 300),
            'image_alt' => a_str($_POST['image_alt'] ?? '', 200),
            'text'  => trim((string)($_POST['text'] ?? '')),
        ];
        a_process_uploads($item);
        if ($id) {
            foreach ($news as $k => $n) if ((int)$n['id'] === $id) $news[$k] = ['id'=>$id] + $item;
        } else {
            $maxId = 0; foreach ($news as $n) $maxId = max($maxId, (int)($n['id'] ?? 0));
            array_unshift($news, ['id'=>$maxId + 1] + $item);
        }
        usort($news, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        a_save('news.json', $news);
        kv_flash('success', 'Новость сохранена.');
        header('Location: admin.php?section=news'); exit;
    }
    if ($action === 'news_delete') {
        $news = kv_read_json($dataDir . 'news.json');
        $id = (int)$_POST['id'];
        $news = array_values(array_filter($news, fn($n) => (int)($n['id'] ?? 0) !== $id));
        a_save('news.json', $news);
        kv_flash('success', 'Новость удалена.');
        header('Location: admin.php?section=news'); exit;
    }

    /* ---- афиша ---- */
    if ($action === 'afisha_save') {
        $afisha = kv_read_json($dataDir . 'afisha.json');
        $id = (int)($_POST['id'] ?? 0);
        $item = [
            'date'  => a_str($_POST['date'] ?? '', 10),
            'time'  => a_str($_POST['time'] ?? '', 5),
            'title' => a_str($_POST['title'] ?? 'Без названия', 300),
            'venue' => a_str($_POST['venue'] ?? '', 300),
            'price' => a_str($_POST['price'] ?? '', 60),
            'ticket_url' => a_url($_POST['ticket_url'] ?? ''),
        ];
        if ($id) {
            foreach ($afisha as $k => $a) if ((int)$a['id'] === $id) $afisha[$k] = ['id'=>$id] + $item;
        } else {
            $maxId = 0; foreach ($afisha as $a) $maxId = max($maxId, (int)($a['id'] ?? 0));
            $afisha[] = ['id'=>$maxId + 1] + $item;
        }
        a_save('afisha.json', $afisha);
        kv_flash('success', 'Событие сохранено.');
        header('Location: admin.php?section=afisha'); exit;
    }
    if ($action === 'afisha_delete') {
        $afisha = kv_read_json($dataDir . 'afisha.json');
        $id = (int)$_POST['id'];
        $afisha = array_values(array_filter($afisha, fn($a) => (int)($a['id'] ?? 0) !== $id));
        a_save('afisha.json', $afisha);
        kv_flash('success', 'Событие удалено.');
        header('Location: admin.php?section=afisha'); exit;
    }

    /* ---- резервная копия ---- */
    if ($action === 'backup') {
        $files = glob($dataDir . '*.json') ?: [];
        $zipName = 'kv-backup-' . date('Y-m-d-His') . '.txt';
        $out = '';
        foreach ($files as $f) {
            if (str_contains($f, 'credentials')) continue;
            $out .= "\n===== " . basename($f) . " =====\n" . file_get_contents($f);
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        echo ltrim($out);
        exit;
    }
}

/**
 * Приведение «сырых» данных формы блока к безопасному виду.
 * $old — прежнее содержимое блока (для полей, которые не отправлялись).
 */
function a_normalize_block(string $type, array $raw, array $old): array
{
    $b = $old;
    $b['type'] = $type;
    $b['kicker'] = a_str($raw['kicker'] ?? '', 200);
    $b['title']  = a_str($raw['title'] ?? '', 300);

    $btn = function ($key) use ($raw) {
        return ['text' => a_str($raw[$key]['text'] ?? '', 120),
                'url'  => a_url($raw[$key]['url'] ?? '')];
    };

    switch ($type) {
        case 'hero':
            $b['subtitle'] = a_str($raw['subtitle'] ?? '', 600);
            $hv = trim((string)($raw['video_url'] ?? ''));
            $b['video_url'] = $hv !== '' && kv_clean_url($hv) !== '' ? $hv : '';
            $b['poster']    = a_str($raw['poster'] ?? '', 300);
            $b['image']    = a_str($raw['image'] ?? '', 300) ?: 'theme/img/hero.svg';
            $b['image_alt']= a_str($raw['image_alt'] ?? '', 200);
            $b['cta']  = $btn('cta');  $b['cta2'] = $btn('cta2');
            $b['facts']    = a_lines($raw['facts'] ?? '');
            $b['marquee']  = a_str($raw['marquee'] ?? '', 400);
            break;
        case 'page_header':
            $b['subtitle'] = a_str($raw['subtitle'] ?? '', 600);
            break;
        case 'text':
            $b['html'] = trim(preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', (string)($raw['html'] ?? '')));
            break;
        case 'media':
            $b['html'] = trim(preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', (string)($raw['html'] ?? '')));
            $b['image'] = a_str($raw['image'] ?? '', 300) ?: 'theme/img/placeholder.svg';
            $b['image_alt'] = a_str($raw['image_alt'] ?? '', 200);
            $b['cta'] = $btn('cta');
            // видео в медиа-блоке: принимаем только http(s)/относительные ссылки и data:
            $v = trim((string)($raw['video_url'] ?? ''));
            $b['video_url'] = $v !== '' && kv_clean_url($v) !== '' ? $v : '';
            $b['poster']    = a_str($raw['poster'] ?? '', 300);
            break;
        case 'rich_html':
            $b['html'] = kv_sanitize_html_simple((string)($raw['html'] ?? ''));
            break;
        case 'numbers':
            $b['items'] = a_rows($raw['items'] ?? [], ['value','label']);
            break;
        case 'gallery':
            $b['items'] = a_rows($raw['items'] ?? [], ['image','alt','caption']);
            foreach ($b['items'] as &$it) $it['image'] = $it['image'] ?: 'theme/img/placeholder.svg';
            break;
        case 'team':
            $b['items'] = a_rows($raw['items'] ?? [], ['name','role','note','image']);
            foreach ($b['items'] as &$it) $it['image'] = $it['image'] ?: 'theme/img/portrait.svg';
            break;
        case 'list':
            $b['items'] = a_rows($raw['items'] ?? [], ['name','note','meta']);
            break;
        case 'quote':
            $b['text'] = a_str($raw['text'] ?? '', 1000);
            $b['author'] = a_str($raw['author'] ?? '', 200);
            break;
        case 'cta':
            $b['text'] = a_str($raw['text'] ?? '', 600);
            $b['cta']  = $btn('cta'); $b['cta2'] = $btn('cta2');
            break;
        case 'contacts':
            $b['text'] = a_str($raw['text'] ?? '', 2000);
            // iframe карты остаётся как есть (только <iframe>/<div> от админа)
            $map = trim((string)($raw['map_html'] ?? ''));
            $b['map_html'] = $map !== '' && stripos($map, '<script') === false ? $map : '';
            break;
        // afisha/news — только kicker/title
    }
    return $b;
}

/** Массив строк form-значений → массив записей с нужными ключами */
function a_rows($rawItems, array $keys): array
{
    $out = [];
    $n = max(array_map(fn($k) => is_array($rawItems[$k] ?? null) ? count($rawItems[$k]) : 0, $keys) ?: [0]);
    for ($i = 0; $i < $n; $i++) {
        $row = [];
        $empty = true;
        foreach ($keys as $k) {
            $v = $rawItems[$k][$i] ?? '';
            $row[$k] = is_array($v) ? '' : a_str($v, 1000);
            if ($row[$k] !== '') $empty = false;
        }
        if (!$empty) $out[] = $row;
    }
    return $out;
}

/** Санитайзер для rich_html (дублирует логику blocks.php) */
function kv_sanitize_html_simple(string $html): string
{
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><a><ul><ol><li><h2><h3><h4><blockquote><hr>');
    $html = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>/i', function ($m) {
        $href = trim($m[1]);
        return preg_match('#^(https?://|mailto:|tel:|/|index\.php)#i', $href)
            ? '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" target="_blank" rel="noopener">' : '';
    }, $html);
    return $html;
}

/* ============================================================
 *  ВЫВОД
 * ============================================================ */
$flashes = kv_get_flashes();
$csrf = kv_generate_csrf();
// фон экрана входа = то же видео, что и на сайте (можно отключить в настройках)
$LOGIN_VIDEO = empty($GLOBALS['KV_LOGIN_BG_DISABLED']) ? kv_read_json($dataDir . 'settings.json')['hero_video_url'] ?? '' : '';
$GLOBALS['LOGIN_VIDEO'] = $LOGIN_VIDEO;

/* ---------- Экран входа ---------- */
if (!$isLoggedIn || $requiresLogout) { ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Вход — админка «Казачья Воля»</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap&subset=cyrillic" rel="stylesheet">
<style>
:root{--wine:#800020;--gold:#D4AF37;--ink:#161311}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
 font-family:'Inter',system-ui,Arial,sans-serif;background:var(--ink);padding:20px;overflow:hidden}
/* фон: видео (если доступно) + градиенты поверх */
.bg{position:fixed;inset:0;z-index:-2}
video.bg{width:100%;height:100%;object-fit:cover}
.veil{position:fixed;inset:0;z-index:-1;
 background:radial-gradient(900px 500px at 80% -10%,rgba(128,0,32,.6),transparent 60%),
            radial-gradient(700px 400px at 0% 110%,rgba(212,175,55,.25),transparent 60%),
            linear-gradient(160deg,rgba(22,19,17,.72),rgba(22,19,17,.9));
 backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px)}
.card{width:100%;max-width:410px;padding:40px 36px;border-radius:26px;color:#fff;
 background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);
 backdrop-filter:blur(24px) saturate(1.4);-webkit-backdrop-filter:blur(24px) saturate(1.4);
 box-shadow:0 30px 90px -20px rgba(0,0,0,.7);
 animation:rise .8s cubic-bezier(.22,.61,.36,1) both}
@keyframes rise{from{opacity:0;transform:translateY(26px) scale(.98)}to{opacity:1;transform:none}}
h1{font-family:'Playfair Display',Georgia,serif;margin:0 0 4px;font-size:1.7rem;color:var(--gold)}
p.sub{margin:0 0 26px;color:rgba(255,255,255,.6);font-size:.92rem}
label{display:block;font-size:.74rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;
 color:rgba(255,255,255,.55);margin:14px 0 6px}
input{width:100%;padding:13px 14px;border:1.5px solid rgba(255,255,255,.18);border-radius:14px;
 font:inherit;color:#fff;background:rgba(255,255,255,.06);transition:border-color .2s,background .2s,box-shadow .2s}
input::placeholder{color:rgba(255,255,255,.3)}
input:focus{outline:none;border-color:var(--gold);background:rgba(255,255,255,.1);
 box-shadow:0 0 0 4px rgba(212,175,55,.15)}
button{margin-top:24px;width:100%;padding:14px;border:0;border-radius:999px;position:relative;overflow:hidden;
 background:linear-gradient(120deg,var(--wine),#a30b2e);color:#fff;font:inherit;font-weight:600;font-size:1rem;
 cursor:pointer;transition:transform .18s,box-shadow .18s,filter .2s;
 box-shadow:0 14px 34px -14px rgba(128,0,32,.9)}
button:hover{filter:brightness(1.12);transform:translateY(-2px)}
button:active{transform:scale(.97)}
button::after{content:"";position:absolute;top:0;left:-80%;width:50%;height:100%;
 background:linear-gradient(100deg,transparent,rgba(255,255,255,.4),transparent);
 transform:skewX(-20deg);transition:left .6s cubic-bezier(.22,.61,.36,1)}
button:hover::after{left:130%}
button:disabled{filter:grayscale(.6) brightness(.7);cursor:not-allowed;transform:none}
.msg{padding:11px 14px;border-radius:12px;font-size:.9rem;margin-top:14px;animation:rise .5s both}
.err{background:rgba(255,90,110,.14);border:1px solid rgba(255,90,110,.35);color:#ffb9c4}
.ok{background:rgba(60,200,120,.12);border:1px solid rgba(60,200,120,.35);color:#a9edc3}
.lock{background:rgba(212,175,55,.12);border:1px solid rgba(212,175,55,.4);color:#f0d998;
 padding:12px 14px;border-radius:12px;font-size:.9rem;margin-top:14px}
.shake{animation:shake .5s}
@keyframes shake{10%,90%{transform:translateX(-2px)}20%,80%{transform:translateX(4px)}
 30%,50%,70%{transform:translateX(-7px)}40%,60%{transform:translateX(7px)}}
</style>
</head>
<body>
<?php $loginBg = kv_clean_url($GLOBALS['LOGIN_VIDEO'] ?? ''); ?>
<?php if ($loginBg !== ''): ?>
<video class="bg" autoplay muted loop playsinline preload="metadata" tabindex="-1" aria-hidden="true">
    <source src="<?= htmlspecialchars($loginBg, ENT_QUOTES) ?>" type="video/mp4">
</video>
<?php endif; ?>
<div class="veil" aria-hidden="true"></div>
<form class="card <?= $isLocked || !empty(array_filter($flashes, fn($f)=>$f['type']==='error')) ? 'shake' : '' ?>" method="post">
    <input type="hidden" name="action" value="login">
    <h1>✦ Казачья Воля</h1>
    <p class="sub">Панель управления сайтом</p>
    <?php foreach ($flashes as $f): ?><div class="msg <?= $f['type']==='error'?'err':'ok' ?>"><?= a_e($f['message']) ?></div><?php endforeach; ?>
    <?php if ($isLocked): ?>
        <div class="lock">Превышено число попыток входа. Попробуйте через
        <?= ceil(($lockedUntil - time()) / 60) ?> мин.</div>
    <?php endif; ?>
    <label for="u">Логин</label>
    <input id="u" name="username" placeholder="admin" autocomplete="username" required autofocus>
    <label for="p">Пароль</label>
    <input id="p" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
    <button <?= $isLocked ? 'disabled' : '' ?>>Войти</button>
</form>
<script>
/* остановить фоновое видео пользователям с reduced-motion */
if (matchMedia('(prefers-reduced-motion: reduce)').matches)
    document.querySelectorAll('video.bg').forEach(function(v){v.pause();});
</script>
</body></html>
<?php exit; }

/* ============================================================
 *  ИНТЕРФЕЙС АДМИНКИ
 * ============================================================ */
$pages   = kv_read_json($dataDir . 'pages.json');
$news    = kv_read_json($dataDir . 'news.json');
$afisha  = kv_read_json($dataDir . 'afisha.json');
$settings= kv_read_json($dataDir . 'settings.json');

$section = $_GET['section'] ?? 'pages';
$pageEdit = isset($_GET['page-edit']) ? (int)$_GET['page-edit'] : null;
$newsEdit = isset($_GET['news-edit']) ? (int)$_GET['news-edit'] : null;
$afEdit   = isset($_GET['af-edit']) ? (int)$_GET['af-edit'] : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Админка — Казачья Воля</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600;700&display=swap&subset=cyrillic" rel="stylesheet">
<style>
:root{--wine:#800020;--wine-d:#5C0017;--gold:#D4AF37;--ink:#161311;--bg:#F4F2EE;--line:#E3DFD7;--mut:#6E6A64;--r:14px}
*{box-sizing:border-box}body{margin:0;font-family:'Inter',system-ui,Arial,sans-serif;background:var(--bg);color:#1A1A1A;font-size:15px;line-height:1.55}
h1,h2,h3{font-family:'Playfair Display',Georgia,serif;margin:0 0 .4em}
a{color:var(--wine)}
.topbar{position:sticky;top:0;z-index:40;background:var(--ink);color:#fff;display:flex;align-items:center;gap:18px;padding:0 20px;height:58px}
.topbar .brand{font-family:'Playfair Display',serif;font-size:1.15rem;color:var(--gold)}
.topbar nav{display:flex;gap:4px;flex-wrap:wrap}
.topbar nav a{color:#cfc9bf;text-decoration:none;padding:8px 14px;border-radius:999px;font-size:.92rem;font-weight:500}
.topbar nav a.on,.topbar nav a:hover{background:rgba(255,255,255,.12);color:#fff}
.topbar .right{margin-left:auto;display:flex;gap:10px;align-items:center}
.topbar .right a,.topbar .right button{color:#cfc9bf;background:none;border:0;font:inherit;cursor:pointer;text-decoration:none;font-size:.9rem}
.topbar .right a:hover{color:var(--gold)}
.wrap{max-width:1100px;margin:0 auto;padding:26px 20px 80px}
.flash{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:.94rem}
.flash.success{background:#e8f5ea;color:#186a2c;border:1px solid #bfe3c6}
.flash.error{background:#fbeaec;color:var(--wine);border:1px solid #eec4cd}
.panel{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:22px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.03)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
@media(max-width:760px){.grid2,.grid3{grid-template-columns:1fr}}
label.f{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--mut);margin-bottom:5px}
input[type=text],input[type=password],input[type=url],input[type=email],input[type=date],input[type=time],input[type=number],textarea,select{
 width:100%;padding:10px 12px;border:1.5px solid var(--line);border-radius:10px;font:inherit;background:#fff}
textarea{min-height:110px;resize:vertical}
input:focus,textarea:focus,select:focus{outline:none;border-color:var(--wine)}
.btn{display:inline-flex;align-items:center;gap:6px;border:0;cursor:pointer;font:inherit;font-weight:600;
 padding:10px 20px;border-radius:999px;background:var(--wine);color:#fff;text-decoration:none;font-size:.92rem;transition:filter .15s,transform .1s}
.btn:hover{filter:brightness(1.12)}.btn:active{transform:scale(.97)}
.btn.gold{background:var(--gold);color:#241d05}
.btn.ghost{background:#fff;color:var(--wine);border:1.5px solid var(--wine)}
.btn.sm{padding:6px 12px;font-size:.82rem}
.btn.danger{background:#fff;color:#a11235;border:1.5px solid #e3b7c1}
.icon-btn{border:1px solid var(--line);background:#fff;border-radius:8px;width:30px;height:30px;cursor:pointer;font-size:.9rem;line-height:1}
.icon-btn:hover{border-color:var(--wine)}
table{width:100%;border-collapse:collapse}
th{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--mut);text-align:left;padding:8px 10px;border-bottom:1px solid var(--line)}
td{padding:10px;border-bottom:1px solid #efece6;vertical-align:middle}
tr:hover td{background:#faf9f6}
.tag{display:inline-block;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px;background:#f1ece2;color:#6b5b2a;text-transform:uppercase;letter-spacing:.05em}
.tag.dark{background:#e8e2f2;color:#4b3b66}.tag.green{background:#e3f2e5;color:#1e6b2b}.tag.off{background:#eee;color:#888}
.block-card{border:1px solid var(--line);border-radius:var(--r);margin-bottom:14px;background:#fff;overflow:hidden}
.block-head{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#faf8f4;border-bottom:1px solid var(--line);flex-wrap:wrap}
.block-title{font-weight:700}
.block-tools{margin-left:auto;display:flex;gap:6px;align-items:center}
.block-body{padding:16px;display:grid;gap:12px}
.hint{font-size:.8rem;color:var(--mut)}
.row-item{display:grid;grid-template-columns:1fr 1fr 1fr auto auto;gap:8px;align-items:center;margin-bottom:8px}
.row-item input{padding:8px 10px}
.row-del{background:none;border:0;color:#a11235;cursor:pointer;font-size:1.05rem}
.stat-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px}
.stat{background:#fff;border:1px solid var(--line);border-radius:var(--r);padding:18px}
.stat b{font-family:'Playfair Display',serif;font-size:1.9rem;color:var(--wine);display:block}
fieldset.addblock{border:1.5px dashed var(--line);border-radius:var(--r);padding:14px 16px;background:#fff;display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:20px}
.imgprev{max-height:70px;max-width:160px;border-radius:8px;border:1px solid var(--line);object-fit:cover}
.two-col{display:grid;grid-template-columns:1.1fr .9fr;gap:20px}
@media(max-width:900px){.two-col{grid-template-columns:1fr}}
.mono{font-family:ui-monospace,Menlo,monospace;font-size:.85rem;background:#f4f1ea;padding:2px 8px;border-radius:6px}
details summary{cursor:pointer;font-weight:600;color:var(--wine);padding:6px 0}
</style>
</head>
<body>
<div class="topbar">
    <span class="brand">✦ Казачья Воля</span>
    <nav>
        <a class="<?= $section==='pages'?'on':'' ?>" href="admin.php">Страницы</a>
        <a class="<?= $section==='news'?'on':'' ?>" href="admin.php?section=news">Новости</a>
        <a class="<?= $section==='afisha'?'on':'' ?>" href="admin.php?section=afisha">Афиша</a>
        <a class="<?= $section==='settings'?'on':'' ?>" href="admin.php?section=settings">Настройки</a>
    </nav>
    <div class="right">
        <a href="index.php" target="_blank">Открыть сайт ↗</a>
        <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
            <input type="hidden" name="action" value="logout">
            <button type="submit">Выйти</button>
        </form>
    </div>
</div>
<div class="wrap">

<?php foreach ($flashes as $f): ?>
    <div class="flash <?= a_e($f['type']) ?>"><?= a_e($f['message']) ?></div>
<?php endforeach; ?>

<?php /* ==================== ДАШБОРД / СПИСОК СТРАНИЦ ==================== */ ?>
<?php if ($section === 'pages' && $pageEdit === null): ?>

    <div class="stat-cards">
        <div class="stat"><b><?= count($pages) ?></b>страниц в CMS</div>
        <div class="stat"><b><?= count($news) ?></b>новостей</div>
        <div class="stat"><b><?= count($afisha) ?></b>событий в афише</div>
        <div class="stat"><b><?= array_sum(array_map(fn($p)=>count($p['blocks']??[]),$pages)) ?></b>контентных блоков</div>
    </div>

    <div class="two-col">
        <div class="panel">
            <h2>Страницы сайта</h2>
            <p class="hint">Все страницы редактируются поблочно — можно менять любой текст, добавлять и удалять секции.</p>
            <table>
                <tr><th>Страница</th><th>URL</th><th>Блоков</th><th>В меню</th><th></th></tr>
                <?php foreach ($pages as $i => $p): ?>
                <tr>
                    <td><strong><a href="admin.php?page-edit=<?= $i ?>"><?= a_e($p['title']) ?></a></strong></td>
                    <td><span class="mono">?page=<?= a_e($p['slug']) ?></span></td>
                    <td><?= count($p['blocks'] ?? []) ?></td>
                    <td><?= !empty($p['visible']) ? '<span class="tag green">да</span>' : '<span class="tag off">нет</span>' ?></td>
                    <td style="text-align:right">
                        <a class="btn sm ghost" href="index.php?page=<?= a_e($p['slug']) ?>" target="_blank">👁</a>
                        <?php if (($p['slug'] ?? '') !== 'home'): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Удалить страницу «<?= a_e($p['title']) ?>»?')">
                            <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
                            <input type="hidden" name="action" value="page_delete">
                            <input type="hidden" name="page_index" value="<?= $i ?>">
                            <button class="btn sm danger" type="submit">✕</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p class="hint">Системные страницы «Афиша» и «Новости» управляются в соответствующих разделах.</p>
        </div>

        <div>
            <div class="panel">
                <h2>Быстрые действия</h2>
                <p>
                    <a class="btn" href="admin.php?section=news&news-edit=new">＋ Добавить новость</a>
                    &nbsp;
                    <a class="btn gold" href="admin.php?section=afisha&af-edit=new">＋ Добавить событие</a>
                </p>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
                    <input type="hidden" name="action" value="backup">
                    <button class="btn ghost" type="submit">⬇ Скачать резервную копию данных</button>
                </form>
            </div>
            <div class="panel">
                <h2>Создать страницу</h2>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
                    <input type="hidden" name="action" value="page_create">
                    <label class="f">Заголовок страницы</label>
                    <input type="text" name="title" placeholder="Например: История" required>
                    <label class="f">URL (slug, латиницей — можно оставить пустым)</label>
                    <input type="text" name="slug" placeholder="istoriya">
                    <p><button class="btn" type="submit">Создать</button></p>
                </form>
                <p class="hint">Новая страница сразу появится в меню и будет полностью редактируема блоками.</p>
            </div>
        </div>
    </div>

<?php /* ==================== РЕДАКТОР СТРАНИЦЫ ==================== */ ?>
<?php elseif ($section === 'pages' && $pageEdit !== null && isset($pages[$pageEdit])):
    $p = $pages[$pageEdit]; ?>

    <p style="margin:0 0 14px"><a href="admin.php">← Все страницы</a></p>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
        <input type="hidden" name="action" value="page_save">
        <input type="hidden" name="page_index" value="<?= $pageEdit ?>">

        <div class="panel">
            <h2>Редактор: <?= a_e($p['title']) ?>
                <a class="btn sm ghost" style="float:right" href="index.php?page=<?= a_e($p['slug']) ?>" target="_blank">Просмотр ↗</a></h2>
            <div class="grid3">
                <div><label class="f">Заголовок (H1 / title)</label>
                     <input type="text" name="title" value="<?= a_e($p['title']) ?>"></div>
                <div><label class="f">Пункт меню</label>
                     <input type="text" name="menu_title" value="<?= a_e($p['menu_title'] ?? '') ?>"></div>
                <div><label class="f">URL (slug)<?= ($p['slug'] ?? '') === 'home' ? ' — главная, не меняется' : '' ?></label>
                     <input type="text" name="slug" value="<?= a_e($p['slug']) ?>" <?= ($p['slug'] ?? '') === 'home' ? 'readonly style="opacity:.6"' : '' ?>></div>
            </div>
            <label style="display:flex;gap:8px;align-items:center;margin-top:10px">
                <input type="checkbox" style="width:auto" name="visible" value="1" <?= !empty($p['visible'])?'checked':'' ?>>
                Показывать в главном меню
            </label>
        </div>

        <?php foreach ($p['blocks'] ?? [] as $bi => $b):
            $type = $b['type'] ?? 'text'; ?>
        <div class="block-card" data-bi="<?= $bi ?>">
            <div class="block-head">
                <span class="tag"><?= a_e(BLOCK_TYPES[$type] ?? $type) ?></span>
                <span class="block-title"><?= a_e($b['title'] ?? '—') ?></span>
                <div class="block-tools">
                    <?php if ($bi > 0): ?>
                    <button type="button" class="icon-btn" onclick="moveBlock(<?= $bi ?>,-1)" title="Выше">↑</button>
                    <?php else: ?><span class="icon-btn" style="opacity:.3">↑</span><?php endif; ?>
                    <?php if ($bi < count($p['blocks']) - 1): ?>
                    <button type="button" class="icon-btn" onclick="moveBlock(<?= $bi ?>,1)" title="Ниже">↓</button>
                    <?php else: ?><span class="icon-btn" style="opacity:.3">↓</span><?php endif; ?>
                    <button type="button" class="icon-btn" onclick="dupBlock(<?= $bi ?>)" title="Дублировать">⧉</button>
                    <button type="button" class="icon-btn" onclick="delBlock(<?= $bi ?>)" title="Удалить блок" style="color:#a11235">✕</button>
                </div>
            </div>
            <div class="block-body">
                <?= a_render_block_fields($bi, $b) ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="panel" style="text-align:center">
            <button class="btn gold" type="submit">💾 Сохранить страницу</button>
        </div>
    </form>

    <!-- служебные формы для перемещения/дублирования/удаления блоков -->
    <form method="post" id="moveForm" action="admin.php">
        <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
        <input type="hidden" name="action" value="block_move">
        <input type="hidden" name="page_index" value="<?= $pageEdit ?>">
        <input type="hidden" name="block_index" id="mvBi"><input type="hidden" name="delta" id="mvDelta">
    </form>
    <form method="post" id="dupForm" action="admin.php">
        <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
        <input type="hidden" name="action" value="block_duplicate">
        <input type="hidden" name="page_index" value="<?= $pageEdit ?>">
        <input type="hidden" name="block_index" id="dupBi">
    </form>
    <form method="post" id="delForm" action="admin.php">
        <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
        <input type="hidden" name="action" value="block_delete">
        <input type="hidden" name="page_index" value="<?= $pageEdit ?>">
        <input type="hidden" name="block_index" id="delBi">
    </form>
    <script>
      function moveBlock(i,d){document.getElementById('mvBi').value=i;document.getElementById('mvDelta').value=d;document.getElementById('moveForm').submit();}
      function dupBlock(i){document.getElementById('dupBi').value=i;document.getElementById('dupForm').submit();}
      function delBlock(i){if(confirm('Удалить этот блок?')){document.getElementById('delBi').value=i;document.getElementById('delForm').submit();}}
      // «＋ строку» — клонирует последнюю строку списка полей внутри блока
      function addRow(btn){var box=btn.closest('.rows');var rows=box.querySelectorAll('.row-item');
        if(!rows.length)return;var r=rows[rows.length-1].cloneNode(true);
        r.querySelectorAll('input').forEach(function(inp){inp.value='';});box.appendChild(r);}
      function delRow(btn){var box=btn.closest('.rows');
        if(box.querySelectorAll('.row-item').length>1)btn.closest('.row-item').remove();}
    </script>

    <fieldset class="addblock">
        <legend style="padding:0 8px;font-weight:700">Добавить блок на страницу</legend>
        <select form="addBlockForm" name="block_type" style="max-width:320px">
            <?php foreach (BLOCK_TYPES as $t => $lbl): ?>
                <option value="<?= a_e($t) ?>"><?= a_e($lbl) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit" form="addBlockForm">＋ Добавить</button>
    </fieldset>
    <form method="post" id="addBlockForm" action="admin.php">
        <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
        <input type="hidden" name="action" value="block_add">
        <input type="hidden" name="page_index" value="<?= $pageEdit ?>">
    </form>

    <?php /* ==================== НОВОСТИ ==================== */ ?>
<?php elseif ($section === 'news'): ?>

    <?php if ($newsEdit !== null):
        $item = ['id'=>0,'date'=>date('Y-m-d'),'title'=>'','image'=>'theme/img/placeholder.svg','image_alt'=>'','text'=>''];
        if ($newsEdit !== 'new') foreach ($news as $n) if ((int)$n['id'] === (int)$newsEdit) $item = $n; ?>
    <p style="margin:0 0 14px"><a href="admin.php?section=news">← Все новости</a></p>
    <div class="panel">
        <h2><?= $item['id'] ? 'Редактирование новости' : 'Новая новость' ?></h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
            <input type="hidden" name="action" value="news_save">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <div class="grid2">
                <div><label class="f">Заголовок</label><input type="text" name="title" value="<?= a_e($item['title']) ?>" required></div>
                <div><label class="f">Дата</label><input type="date" name="date" value="<?= a_e($item['date']) ?>"></div>
            </div>
            <label class="f" style="margin-top:12px">Текст (пустая строка = новый абзац)</label>
            <textarea name="text" rows="10"><?= a_e($item['text']) ?></textarea>
            <div class="grid2" style="margin-top:12px">
                <div><label class="f">Картинка (путь)</label><input type="text" name="image" value="<?= a_e($item['image']) ?>">
                    <img class="imgprev" src="<?= a_e($item['image']) ?>" alt=""></div>
                <div><label class="f">…или загрузить файл</label><input type="file" name="upload[image]" accept="image/*">
                    <input type="text" name="image_alt" placeholder="Описание для слепых (alt)" style="margin-top:8px"></div>
            </div>
            <p style="margin-top:16px"><button class="btn gold" type="submit">💾 Сохранить</button></p>
        </form>
    </div>
    <?php else: ?>
    <div class="panel">
        <h2>Новости <a class="btn sm" style="float:right" href="admin.php?section=news&news-edit=new">＋ Добавить</a></h2>
        <table>
            <tr><th>ID</th><th>Дата</th><th>Заголовок</th><th></th></tr>
            <?php foreach ($news as $n): ?>
            <tr>
                <td><?= (int)$n['id'] ?></td>
                <td><?= a_e(kv_date_ru($n['date'] ?? '')) ?></td>
                <td><strong><?= a_e($n['title']) ?></strong></td>
                <td style="text-align:right;white-space:nowrap">
                    <a class="btn sm ghost" href="admin.php?section=news&news-edit=<?= (int)$n['id'] ?>">✎ Изменить</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Удалить новость?')">
                        <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
                        <input type="hidden" name="action" value="news_delete">
                        <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button class="btn sm danger">✕</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

<?php /* ==================== АФИША ==================== */ ?>
<?php elseif ($section === 'afisha'): ?>

    <?php if ($afEdit !== null):
        $item = ['id'=>0,'date'=>date('Y-m-d',strtotime('+1 month')),'time'=>'18:00','title'=>'','venue'=>'','price'=>'','ticket_url'=>''];
        if ($afEdit !== 'new') foreach ($afisha as $a) if ((int)$a['id'] === (int)$afEdit) $item = $a; ?>
    <p style="margin:0 0 14px"><a href="admin.php?section=afisha">← Вся афиша</a></p>
    <div class="panel">
        <h2><?= $item['id'] ? 'Редактирование события' : 'Новое событие' ?></h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
            <input type="hidden" name="action" value="afisha_save">
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <div class="grid2">
                <div><label class="f">Название программы</label><input type="text" name="title" value="<?= a_e($item['title']) ?>" required></div>
                <div><label class="f">Место проведения</label><input type="text" name="venue" value="<?= a_e($item['venue'] ?? '') ?>"></div>
                <div><label class="f">Дата</label><input type="date" name="date" value="<?= a_e($item['date']) ?>"></div>
                <div><label class="f">Время</label><input type="time" name="time" value="<?= a_e($item['time'] ?? '') ?>"></div>
                <div><label class="f">Цена</label><input type="text" name="price" value="<?= a_e($item['price'] ?? '') ?>" placeholder="от 800 ₽"></div>
                <div><label class="f">Ссылка на билеты</label><input type="url" name="ticket_url" value="<?= a_e($item['ticket_url'] ?? '') ?>" placeholder="https://…"></div>
            </div>
            <p style="margin-top:16px"><button class="btn gold" type="submit">💾 Сохранить</button></p>
        </form>
    </div>
    <?php else: ?>
    <div class="panel">
        <h2>Афиша <a class="btn sm" style="float:right" href="admin.php?section=afisha&af-edit=new">＋ Добавить</a></h2>
        <table>
            <tr><th>Дата</th><th>Событие</th><th>Место</th><th>Цена</th><th></th></tr>
            <?php $today = date('Y-m-d');
            usort($afisha, fn($a,$b)=>strcmp($a['date'],$b['date']));
            foreach ($afisha as $a): ?>
            <tr>
                <td><?= a_e(kv_date_ru($a['date'])) ?><?php if(($a['date']??'')<$today)echo ' <span class="tag off">прошло</span>'; ?></td>
                <td><strong><?= a_e($a['title']) ?></strong></td>
                <td><?= a_e($a['venue'] ?? '') ?></td>
                <td><?= a_e($a['price'] ?? '') ?></td>
                <td style="text-align:right;white-space:nowrap">
                    <a class="btn sm ghost" href="admin.php?section=afisha&af-edit=<?= (int)$a['id'] ?>">✎</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Удалить событие?')">
                        <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
                        <input type="hidden" name="action" value="afisha_delete">
                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                        <button class="btn sm danger">✕</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

<?php /* ==================== НАСТРОЙКИ ==================== */ ?>
<?php elseif ($section === 'settings'): ?>
    <div class="two-col">
        <div class="panel">
            <h2>Настройки сайта</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
                <input type="hidden" name="action" value="settings">
                <?php
                $fields = [
                    'site_name'=>'Название организации','tagline'=>'Слоган под логотипом',
                    'address'=>'Адрес','phone'=>'Телефон (отображаемый)','phone_raw'=>'Телефон для tel:',
                    'email'=>'E-mail','hours'=>'Часы работы','director'=>'Руководитель',
                    'vk_url'=>'Группа ВКонтакте','ticket_url'=>'Ссылка на билеты','copyright'=>'Строка © внизу',
                    'seo_description'=>'Meta description',
                    'hero_video_url'=>'Видео на фоне главной (mp4, https://…)','hero_poster'=>'Постер/фон видео (путь к картинке)',
                ];
                foreach ($fields as $k=>$lbl): ?>
                    <label class="f"><?= a_e($lbl) ?></label>
                    <input type="<?= in_array($k,['vk_url','ticket_url'],true)?'url':(strpos($k,'email')!==false?'email':'text') ?>"
                           name="<?= a_e($k) ?>" value="<?= a_e($settings[$k] ?? '') ?>" style="margin-bottom:10px">
                <?php endforeach; ?>
                <p><button class="btn gold" type="submit">💾 Сохранить</button></p>
            </form>
        </div>
        <div class="panel">
            <h2>Смена пароля</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= a_e($csrf) ?>">
                <input type="hidden" name="action" value="password">
                <label class="f">Текущий пароль</label><input type="password" name="current" required style="margin-bottom:10px">
                <label class="f">Новый пароль (мин. 8 символов)</label><input type="password" name="new_password" minlength="8" required style="margin-bottom:10px">
                <label class="f">Повторите новый</label><input type="password" name="new_confirm" minlength="8" required style="margin-bottom:14px">
                <button class="btn" type="submit">Изменить пароль</button>
            </form>
            <p class="hint" style="margin-top:16px">Хэш пароля хранится в <span class="mono">data/credentials.json</span> и не попадает в исходный код.</p>
        </div>
    </div>
<?php else: ?>
    <div class="panel"><h2>Раздел не найден</h2><a href="admin.php">← На главную админки</a></div>
<?php endif; ?>

</div>
</body>
</html>
<?php
/**
 * Рендер полей редактирования одного блока внутри формы страницы.
 * Имена вида blocks[<bi>][field] сохраняются обработчиком page_save.
 */
function a_render_block_fields(int $bi, array $b): string
{
    $type = $b['type'] ?? 'text';
    $n = "blocks[$bi]";

    /** обычное текстовое поле / textarea */
    $in = function ($k, $lbl, $ph = '', $area = false) use ($b, $n) {
        $v = a_e($b[$k] ?? '');
        $f = $area ? "<textarea name=\"$n[$k]\" rows=\"5\" placeholder=\"$ph\">$v</textarea>"
                   : "<input type=\"text\" name=\"$n[$k]\" value=\"$v\" placeholder=\"$ph\">";
        return "<div><label class=\"f\">$lbl</label>$f</div>";
    };
    /** пара «текст кнопки + ссылка» */
    $btnPair = function ($prefix, $lbl) use ($b, $n) {
        $c = $b[$prefix] ?? ['text' => '', 'url' => ''];
        return '<div class="grid2">'
             . '<div><label class="f">' . $lbl . ' — текст</label><input type="text" name="' . $n . '[' . $prefix . '][text]" value="' . a_e($c['text'] ?? '') . '"></div>'
             . '<div><label class="f">' . $lbl . ' — ссылка</label><input type="text" name="' . $n . '[' . $prefix . '][url]" value="' . a_e($c['url'] ?? '') . '"></div></div>';
    };
    /** заголовок секции kicker+title одной строкой */
    $head = $in('kicker', 'Надзаголовок (kicker)') . $in('title', 'Заголовок');
    /** список строк-карточек с возможностью добавлять/удалять строки */
    $rowsEditor = function (array $fields, array $items, int $cols) use ($n) {
        // $fields: [ключ => placeholder]
        $html = '<div class="rows">';
        $items = array_values($items ?: [[]]);
        foreach ($items as $row) {
            $html .= '<div class="row-item" style="grid-template-columns:repeat(' . $cols . ',1fr) 60px 60px">';
            foreach ($fields as $k => $ph) {
                $html .= '<input type="text" name="' . $n . '[items][' . $k . '][]" value="' . a_e($row[$k] ?? '') . '" placeholder="' . a_e($ph) . '">';
            }
            $html .= '<button type="button" class="icon-btn" onclick="addRow(this)" title="Добавить строку">＋</button>';
            $html .= '<button type="button" class="icon-btn" onclick="delRow(this)" title="Удалить строку" style="color:#a11235">✕</button>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    };

    $out = '';
    switch ($type) {
        case 'hero':
            $out .= '<div class="grid2">' . $head . '</div>';
            $out .= $in('subtitle', 'Подзаголовок');
            $out .= '<div class="grid2">' . $in('image', 'Картинка (путь)', 'theme/img/hero.svg')
                  . '<div><label class="f">…или загрузить файл</label>'
                  . '<input type="file" name="upload[' . $n . '[image]]" accept="image/*">'
                  . '<img class="imgprev" style="margin-top:8px" src="' . a_e($b['image'] ?? '') . '" alt=""></div></div>';
            $out .= $in('image_alt', 'Описание картинки (alt)');
            $out .= $btnPair('cta', 'Кнопка 1') . $btnPair('cta2', 'Кнопка 2');
            $out .= '<div class="grid2">' . $in('video_url', 'Видео на фоне (mp4-ссылка)')
                  . $in('poster', 'Постер видео (путь к картинке)') . '</div>';
            $out .= $in('facts', 'Факты — по одному на строку', '35 лет на сцене', true);
            $out .= $in('marquee', 'Бегущая строка названий программ');
            break;
        case 'page_header':
            $out .= '<div class="grid2">' . $head . '</div>' . $in('subtitle', 'Подзаголовок');
            break;
        case 'text':
            $out .= '<div class="grid2">' . $head . '</div>'
                  . $in('html', 'Текст (пустая строка = новый абзац)', '', true);
            break;
        case 'rich_html':
            $out .= '<div class="grid2">' . $head . '</div>'
                  . $in('html', 'HTML-текст: p, strong, em, ul, ol, li, h2–h4, a, blockquote', '<p>…</p>', true)
                  . '<p class="hint">Опасная разметка (script, iframe, onclick, javascript:) вырезается автоматически.</p>';
            break;
        case 'media':
            $out .= '<div class="grid2">' . $head . '</div>';
            $out .= '<div class="grid2">' . $in('image', 'Картинка', 'theme/img/placeholder.svg')
                  . '<div><label class="f">…или загрузить файл</label>'
                  . '<input type="file" name="upload[' . $n . '[image]]" accept="image/*">'
                  . '<img class="imgprev" style="margin-top:8px" src="' . a_e($b['image'] ?? '') . '" alt=""></div></div>';
            $out .= $in('image_alt', 'Alt картинки');
            $out .= '<div class="grid2">' . $in('video_url', 'Видео (mp4-ссылка) — показывает плеер вместо фото')
                  . $in('poster', 'Постер видео (путь к картинке)') . '</div>';
            $out .= $in('html', 'Текст справа от фото', '', true);
            $out .= $btnPair('cta', 'Ссылка «подробнее»');
            break;
        case 'numbers':
            $out .= '<div class="grid2">' . $head . '</div>'
                  . '<label class="f">Показатели</label>'
                  . $rowsEditor(['value' => 'Значение (напр. 40+)', 'label' => 'Подпись'], $b['items'] ?? [], 2);
            break;
        case 'gallery':
            $out .= '<div class="grid2">' . $head . '</div>'
                  . '<label class="f">Фотографии</label>'
                  . $rowsEditor(['image' => 'Путь к фото', 'alt' => 'Alt', 'caption' => 'Подпись'], $b['items'] ?? [], 3);
            break;
        case 'team':
            $out .= '<div class="grid2">' . $head . '</div>'
                  . '<label class="f">Артисты</label>'
                  . $rowsEditor(['name' => 'Имя Фамилия', 'role' => 'Амплуа', 'image' => 'Фото (путь)'], $b['items'] ?? [], 3);
            break;
        case 'list':
            $out .= '<div class="grid2">' . $head . '</div>'
                  . '<label class="f">Позиции списка</label>'
                  . $rowsEditor(['name' => 'Название', 'note' => 'Описание', 'meta' => 'Метка'], $b['items'] ?? [], 3);
            break;
        case 'quote':
            $out .= $in('text', 'Текст цитаты', '', true) . $in('author', 'Автор / источник');
            break;
        case 'cta':
            $out .= '<div class="grid2">' . $head . '</div>'
                  . $in('text', 'Пояснительный текст')
                  . $btnPair('cta', 'Кнопка 1') . $btnPair('cta2', 'Кнопка 2');
            break;
        case 'contacts':
            $out .= '<div class="grid2">' . $head . '</div>'
                  . $in('text', 'Вступительный текст', '', true)
                  . $in('map_html', 'HTML встраиваемой карты (iframe Яндекс/Гугл)', '<iframe …></iframe>', true);
            break;
        case 'afisha':
        case 'news':
            $out .= '<div class="grid2">' . $head . '</div>'
                  . '<p class="hint">Список мероприятий/новостей берётся из разделов «Афиша» и «Новости».</p>';
            break;
        default:
            $out .= '<p class="hint">Неизвестный тип блока — данные не будут изменены.</p>';
    }
    return $out;
}
