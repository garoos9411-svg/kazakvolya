<?php
/**
 * ============================================================
 *  index.php — фронтенд-роутер сайта «Казачья Воля»
 * ============================================================
 *  Работает без .htaccess и без БД: страница передаётся через
 *  параметр ?page=slug (например, index.php?page=repertuar).
 *  Запрос "/" без параметра показывает Главную.
 *
 *  Все данные читаются из JSON-файлов папки /data/ через
 *  функции kv_read_json() / helpers из includes/helpers.php.
 * ============================================================
 */

declare(strict_types=1);

define('KV_SITE', true); // разрешение на прямой подключение файлов темы (header/footer)

// --- Подключаем конфигурацию и вспомогательные функции ---
$CONFIG  = require __DIR__ . '/config.php';
require   __DIR__ . '/includes/helpers.php';
require   __DIR__ . '/includes/blocks.php'; // рендеринг блоков страниц

$dataDir = $CONFIG['paths']['data'];

// --- Определяем, какую страницу просит посетитель ---
$pageSlug = kv_slug($_GET['page'] ?? ''); // только латиница/цифры/дефис, не более 60 симв.
if ($pageSlug === '') {
    $pageSlug = 'home';                   // по умолчанию — Главная
}

// --- Загружаем контент ---
$settings = kv_read_json("$dataDir/settings.json");
$pages    = kv_read_json("$dataDir/pages.json");
$news     = kv_read_json("$dataDir/news.json");
$afisha   = kv_read_json("$dataDir/afisha.json");

// Ищем нужную страницу в pages.json
$current = null;
foreach ($pages as $p) {
    if (kv_slug($p['slug'] ?? '') === $pageSlug) {
        $current = $p;
        break;
    }
}

// --- Системные страницы (вне pages.json): «Афиша» и «Новости» ---
if (in_array($pageSlug, ['afisha', 'news'], true)) {
    $current = ['slug' => $pageSlug, 'title' => $pageSlug === 'afisha' ? 'Афиша' : 'Новости'];
}

// Если страницы нет — отдаём 404 и останавливаем работу
if ($current === null) {
    http_response_code(404);
    $current = [
        'slug'  => '404',
        'title' => 'Страница не найдена',
        'blocks'=> [[
            'type'  => 'cta',
            'kicker'=> 'Ошибка 404',
            'title' => 'Такой страницы нет',
            'text'  => 'Возможно, она была перенесена. Вернитесь на главную или загляните в афишу.',
            'cta'   => ['text' => 'На главную', 'url' => 'index.php'],
            'cta2'  => ['text' => 'Афиша', 'url' => 'index.php?page=afisha'],
        ]],
    ];
}

// Собираем меню из pages.json (поле visible = true) + системные пункты
$menu = array_values(array_filter($pages, fn($p) => !empty($p['visible'])));
$menu[] = ['slug' => 'afisha', 'menu_title' => 'Афиша'];
$menu[] = ['slug' => 'news',   'menu_title' => 'Новости'];

// Ближайшие 3 мероприятия из афиши (для главной страницы)
$upcoming = kv_afisha_upcoming($afisha, 3);

// Последние 3 новости
$latestNews = array_slice($news, 0, 3);

// Общий контекст для рендера блоков и системных страниц
$ctx = compact('settings', 'pages', 'news', 'afisha', 'menu', 'upcoming', 'latestNews');

// --- Дальнейшая маршрутизация ---
$systemPage = __DIR__ . '/includes/' . $pageSlug . '.php';
if (in_array($pageSlug, ['afisha', 'news'], true) && is_file($systemPage)) {
    require $systemPage; // эти шаблоны сами подключают header/footer
    exit;
}

// Все страницы (включая 404) рендерятся единым блочным шаблоном
require sprintf('%s/%s/page-default.php', $CONFIG['paths']['theme'], $CONFIG['site']['theme']);
