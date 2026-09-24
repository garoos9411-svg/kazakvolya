<?php
/**
 * ============================================================
 *  includes/helpers.php — общие функции проекта (без зависимостей)
 * ============================================================
 */

declare(strict_types=1);

/**
 * Безопасное чтение JSON-файла. Возвращает массив;
 * если файла нет или он повреждён — пустой массив (сайт не упадёт).
 */
function kv_read_json(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? kv_sanitize_json($data) : [];
}

/**
 * Рекурсивно заменяет некорректные структуры: строки вида "{"..."}" или
 * "["..."]", которые могли попасть в JSON из-за ошибки кодирования, обратно
 * в массивы. Это защищает админку и фронтенд от падения при повреждённых данных.
 */
function kv_sanitize_json(mixed $v): mixed
{
    if (is_string($v)) {
        $s = trim($v);
        if ((str_starts_with($s, '{') && str_ends_with($s, '}')) ||
            (str_starts_with($s, '[') && str_ends_with($s, ']'))) {
            $decoded = json_decode($s, true);
            if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded))) {
                // декодируем рекурсивно, чтобы исправить вложенные «строковые» объекты
                return kv_sanitize_json($decoded);
            }
        }
        return $v;
    }
    if (is_array($v)) {
        foreach ($v as $k => $item) {
            $v[$k] = kv_sanitize_json($item);
        }
    }
    return $v;
}

/**
 * Безопасная запись JSON-файла с блокировкой (LOCK_EX),
 * чтобы два процесса не перезаписали файл одновременно.
 */
function kv_write_json(string $file, array $data): bool
{
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    return file_put_contents($file, $json . "\n", LOCK_EX) !== false;
}

/**
 * Санитизация строки для хранения в JSON (убираем управляющие символы).
 * Экранирование при ВЫВОДЕ делает шаблон через kv_e().
 */
function kv_clean_string(mixed $v): string
{
    return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $v));
}

/**
 * Экранирование для безопасного HTML-вывода.
 */
function kv_e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Делает slug из строки: оставляем латиницу, цифры и дефис.
 * Русские названия транслитерируются простым словарём.
 */
function kv_slug(string $s): string
{
    static $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh',
        'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n',
        'о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h',
        'ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'',
        'э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    $s = mb_strtolower(trim($s), 'UTF-8');
    $out = '';
    foreach (mb_str_split($s) as $ch) {
        $out .= $map[$ch] ?? $ch;
    }
    $out = preg_replace('/[^a-z0-9-]+/u', '-', $out); // всё лишнее → дефис
    $out = trim((string) preg_replace('/-+/', '-', $out), '-');
    return substr($out, 0, 60);
}

/**
 * Фильтр URL: разрешаем только http/https. Иначе — пустая строка.
 */
function kv_clean_url(mixed $v): string
{
    $v = trim((string) $v);
    if ($v === '') {
        return '';
    }
    // разрешаем внешние http(s)-ссылки и ссылки «протокол не важен» (//cdn…)
    if (filter_var($v, FILTER_VALIDATE_URL) || str_starts_with($v, '//')) {
        // запретим javascript:, data:, file: внутри «странных» значений
        return preg_match('#^(https?:)?//#i', $v) ? $v : '';
    }
    // локальные относительные пути вида theme/img/….svg или uploads/….mp4 — тоже ок
    if (preg_match('#^[A-Za-z0-9_\-./]+$#', $v) && !str_contains($v, '..')) {
        return $v;
    }
    return '';
}

/**
 * Название месяца по-русски (родительный падеж): 10 → «октября».
 */
function kv_month_ru(int $m): string
{
    $months = [1=>'января','февраля','марта','апреля','мая','июня',
               'июля','августа','сентября','октября','ноября','декабря'];
    return $months[$m] ?? '';
}

/**
 * Форматирование даты «2026-10-05» → «5 октября 2026».
 */
function kv_date_ru(string $iso): string
{
    $ts = strtotime($iso);
    if (!$ts) {
        return kv_e($iso);
    }
    return date('j', $ts) . ' ' . kv_month_ru((int) date('n', $ts)) . ' ' . date('Y', $ts);
}

/**
 * Возвращает N ближайших мероприятий из афиши (по дате, без прошедших).
 */
function kv_afisha_upcoming(array $afisha, int $limit = 3): array
{
    $today = date('Y-m-d');
    $items = array_values(array_filter($afisha, fn($a) => ($a['date'] ?? '9999') >= $today));
    usort($items, fn($a, $b) => strcmp($a['date'] ?? '', $b['date'] ?? ''));
    return array_slice($items, 0, $limit);
}

/**
 * Простое превращение многострочного текста в абзацы <p>…</p>
 * (каждая строка экранируется — защита от XSS).
 */
function kv_text_to_html(string $text): string
{
    $html = '';
    foreach (preg_split('/\R{2,}/u', $text) as $para) {
        $para = trim($para);
        if ($para !== '') {
            $html .= '<p>' . nl2br(kv_e($para)) . '</p>';
        }
    }
    return $html;
}

/* ============================================================
 *  ФУНКЦИИ, КОТОРЫЕ ИСПОЛЬЗУЕТ admin.php:
 *  flash-сообщения, CSRF-токены, учётные данные.
 *  Хранятся в сессии PHP — никаких файлов и БД не требуется.
 * ============================================================ */

/**
 * Положить одноразовое сообщение («Сохранено ✓») во временное хранилище сессии.
 */
function kv_flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return; // без активной сессии сообщения не копим — это не ошибка
    }
    $_SESSION['kv_flashes'][] = ['type' => $type, 'message' => $message];
}

/**
 * Забрать все накопленные flash-сообщения и очистить список.
 * Возвращает массив вида [['type'=>'success','message'=>'…'], …].
 */
function kv_get_flashes(): array
{
    $list = $_SESSION['kv_flashes'] ?? [];
    unset($_SESSION['kv_flashes']);
    return $list;
}

/**
 * Выдать (при необходимости создать) CSRF-токен текущей сессии.
 */
function kv_generate_csrf(): string
{
    if (empty($_SESSION['kv_csrf'])) {
        $_SESSION['kv_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['kv_csrf'];
}

/**
 * Проверить присланный из формы CSRF-токен.
 */
function kv_verify_csrf(string $token): bool
{
    return !empty($_SESSION['kv_csrf'])
        && is_string($token)
        && hash_equals($_SESSION['kv_csrf'], $token);
}

/**
 * Прочитать учётные данные администратора из data/credentials.json.
 * Если файла нет или он повреждён — создаём новый с паролем по умолчанию
 * (admin / admin123) и возвращаем его. Формат поля «hash» — стандартный
 * bcrypt-хэш password_hash(), совместимый с password_verify().
 */
function kv_credentials(string $credFile): array
{
    $cred = kv_read_json($credFile);
    if (empty($cred['user']) || empty($cred['hash'])) {
        $cred = [
            'user' => 'admin',
            'hash' => password_hash('admin123', PASSWORD_DEFAULT),
        ];
        kv_write_json($credFile, $cred);
        @chmod($credFile, 0640);
    }
    return $cred;
}

/* ============================================================
   URL-ХЕЛПЕРЫ (ЧПУ + canonical + OG)
   ============================================================ */

/** Базовый URL сайта без завершающего слэша (автоопределение). */
function kv_base_url(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base   = ($script === '/' || $script === '.') ? '' : rtrim($script, '/');
    return $base;
}

/**
 * Человекочитаемый URL страницы: /afisha, /news, /news/12.
 * При включённом ЧПУ (.htaccess) даёт красивые адреса, иначе — query-формат.
 */
function kv_url(string $page = '', int $id = 0): string
{
    $base = kv_base_url();
    if (!kv_is_chpu()) { // нет mod_rewrite — работаем через index.php?page=…
        if ($page === '' || $page === 'home') return $base . '/index.php';
        return $base . '/index.php?page=' . rawurlencode($page) . ($id > 0 ? '&id=' . $id : '');
    }
    if ($page === '' || $page === 'home') {
        return $base . '/';
    }
    $url = $base . '/' . rawurlencode($page);
    if ($id > 0) {
        $url .= '/' . $id;
    }
    return $url;
}

/**
 * Включён ли ЧПУ. Определяется АВТОМАТИЧЕСКИ при первом же запросе:
 * если адрес содержит «index.php/…» — значит Apache не применяет .htaccess
 * (нет mod_rewrite или AllowOverride None) → переключаем весь сайт на
 * query-формат ссылок (?page=…). Иначе используем красивые адреса.
 * Это гарантирует, что меню и ссылки работают в любой конфигурации XAMPP.
 */
function kv_is_chpu(): bool
{
    return empty($GLOBALS['kv_chpu_off']);
}

/** Canonical текущего запроса (абсолютный). */
function kv_canonical(string $page = '', int $id = 0): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    // HTTP_HOST обычно уже содержит порт; если нет и порт нестандартный — добавляем
    if ($host === '' || $host === 'localhost') {
        $host = 'localhost';
    } elseif (!str_contains($host, ':')) {
        $port = (int)($_SERVER['SERVER_PORT'] ?? 80);
        if ($port !== 80 && $port !== 443) {
            $host .= ':' . $port;
        }
    }
    return $scheme . '://' . $host . kv_url($page, $id);
}

/**
 * Приведение URL из JSON-данных к текущему формату адресов.
 * Чинит устаревшие ссылки вида «index.php?page=afisha» (после перехода на ЧПУ),
 * а также относительные пути без ведущего слэша при вложенной установке сайта.
 */
function kv_data_url(string $url): string
{
    $u = trim($url);
    if ($u === '') {
        return '#';
    }
    // внешние / протокол-независимые / mailto / tel — как есть
    if (preg_match('#^(https?:)?//#i', $u) || preg_match('#^(mailto|tel):#i', $u)) {
        return $u;
    }
    // старый query-формат → ЧПУ
    if (preg_match('#(?:^|[/?&])page=([a-z0-9_-]+)#i', $u, $m)) {
        $id = preg_match('#[?&](?:amp;)?id=(\d+)#i', $u, $mi) ? (int)$mi[1] : 0;
        return kv_url(kv_slug($m[1]), $id);
    }
    // якорь или абсолютный путь — не трогаем
    if ($u[0] === '#' || $u[0] === '/') {
        return $u;
    }
    // относительный путь («theme/img/…», «uploads/…») → с базой сайта
    return kv_base_url() . '/' . ltrim($u, './');
}

/* ============================================================
   RATE-LIMITING ВХОДА В АДМИНКУ (по IP, переживает перезапуск сессии)
   ============================================================ */

/**
 * Проверить/зафиксировать попытку входа для текущего IP.
 * Возвращает секунды до разблокировки (0 = входить можно).
 */
function kv_login_throttle(string $dataDir, int $maxTries = 5, int $lockTime = 300, bool $register = false): int
{
    $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file   = rtrim($dataDir, '/\\') . '/login_attempts.json';
    $attempts = kv_read_json($file);
    $now    = time();
    $rec    = $attempts[$ip] ?? ['count' => 0, 'locked_until' => 0, 'ts' => $now];

    // окно накопления ошибок — 10 минут
    if ($now - (int)$rec['ts'] > 600) {
        $rec = ['count' => 0, 'locked_until' => 0, 'ts' => $now];
    }
    if ((int)$rec['locked_until'] > $now) {
        return (int)$rec['locked_until'] - $now;
    }
    if ($register) {
        $rec['count']++;
        $rec['ts'] = $now;
        if ($rec['count'] >= $maxTries) {
            $rec['locked_until'] = $now + $lockTime;
            $rec['count'] = 0;
        }
        // чистим старые записи, чтобы файл не разрастался
        foreach ($attempts as $k => $v) {
            if (($v['ts'] ?? 0) < $now - 1800) unset($attempts[$k]);
        }
        $attempts[$ip] = $rec;
        kv_write_json($file, $attempts);
        @chmod($file, 0640);
        if ((int)($attempts[$ip]['locked_until'] ?? 0) > $now) {
            return (int)$attempts[$ip]['locked_until'] - $now;
        }
    }
    return 0;
}

/** Сброс счётчика неудачных входов для IP после успешного логина. */
function kv_login_throttle_reset(string $dataDir): void
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = rtrim($dataDir, '/\\') . '/login_attempts.json';
    $attempts = kv_read_json($file);
    unset($attempts[$ip]);
    kv_write_json($file, $attempts);
}
