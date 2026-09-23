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
    return is_array($data) ? $data : [];
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
