<?php
/**
 * ============================================================
 *  index.php — фронтенд-роутер сайта «Казачья Воля»
 * ============================================================
 *  Поддерживает ЧПУ (красивые адреса через .htaccess):
 *     /               — главная
 *     /afisha         — афиша (+ /afisha/export.ics — календарь)
 *     /news           — новости (+ /news/12 — детальная страница)
 *     /repertuar …    — страницы из pages.json
 *  и старый query-формат: index.php?page=slug&id=N
 *  Сервисные маршруты: /feed.xml (RSS), /sitemap.xml.
 *
 *  Все данные читаются из JSON-файлов папки /data/ через
 *  функции kv_read_json() / helpers из includes/helpers.php.
 * ============================================================
 */

declare(strict_types=1);

define('KV_SITE', true); // разрешение на прямое подключение файлов темы (header/footer)

// --- Подключаем конфигурацию и вспомогательные функции ---
$CONFIG  = require __DIR__ . '/config.php';
require   __DIR__ . '/includes/helpers.php';
require   __DIR__ . '/includes/blocks.php'; // рендеринг блоков страниц

$dataDir = $CONFIG['paths']['data'];

// --- Разбираем путь: ЧПУ (/afisha, /news/12) или query (?page=afisha&id=12) ---
$pathInfo = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
// при запуске без .htaccess путь может включать index.php — убираем
// при запуске без .htaccess (или без AllowOverride) путь включает index.php —
// значит ЧПУ не работает: переводим все ссылки сайта на query-формат
if ($pathInfo !== '' && str_starts_with($pathInfo, 'index.php')) {
    $GLOBALS['kv_chpu_off'] = true;
}
$pathInfo = preg_replace('#^index\.php/#', '', $pathInfo);
$segments = $pathInfo === '' ? [] : explode('/', $pathInfo);

$pageSlug = kv_slug($segments[0] ?? ($_GET['page'] ?? '')); // только латиница/цифры/дефис
$itemId   = (int)($segments[1] ?? $_GET['id'] ?? 0);       // id новости для /news/12
if ($itemId === 0 && !empty($_GET['id'])) {
    $itemId = (int)$_GET['id'];
}
if ($pageSlug === '') {
    $pageSlug = 'home'; // по умолчанию — Главная
}

// --- Загружаем контент ---
$settings = kv_read_json("$dataDir/settings.json");
$pages    = kv_read_json("$dataDir/pages.json");
$news     = kv_read_json("$dataDir/news.json");
$afisha   = kv_read_json("$dataDir/afisha.json");

// --- Сервисные маршруты: RSS, sitemap, экспорт .ics (до проверки страниц) ---
$action = $_GET['action'] ?? '';
if ($pageSlug === 'feed.xml' || $action === 'rss') {
    require __DIR__ . '/includes/feed.php';
    exit;
}
if ($pageSlug === 'sitemap.xml' || $action === 'sitemap') {
    require __DIR__ . '/includes/sitemap.php';
    exit;
}
if (($pageSlug === 'afisha' && $action === 'export') || $pageSlug === 'export.ics' || $pageSlug === 'events.ics') {
    $afisha = kv_read_json("$dataDir/afisha.json");
    require __DIR__ . '/includes/export-ics.php';
    exit;
}

// Ищем нужную страницу в pages.json
$current = null;
foreach ($pages as $p) {
    if (kv_slug($p['slug'] ?? '') === $pageSlug) {
        $current = $p;
        break;
    }
}

// --- Системные страницы (вне pages.json): «Афиша», «Новости», «Контакты+форма» ---
$systemSlugs = ['afisha', 'news', 'contact'];
if (in_array($pageSlug, $systemSlugs, true)) {
    $titles = ['afisha' => 'Афиша', 'news' => 'Новости', 'contact' => 'Связаться с нами'];
    $current = ['slug' => $pageSlug, 'title' => $titles[$pageSlug]];
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
            'cta'   => ['text' => 'На главную', 'url' => kv_url('')],
            'cta2'  => ['text' => 'Афиша', 'url' => kv_url('afisha')],
        ]],
    ];
}

// Собираем меню из pages.json (поле visible = true) + системные пункты.
// Поддержка ВЛОЖЕННОГО меню: у страницы может быть поле "parent" — slug
// родительского пункта. Тогда страница попадает в выпадающий список родителя.
$rawMenu   = array_values(array_filter($pages, fn($p) => !empty($p['visible'])));
$systemPts = [
    ['slug' => 'afisha',  'menu_title' => 'Афиша'],
    ['slug' => 'news',    'menu_title' => 'Новости'],
    ['slug' => 'contact', 'menu_title' => 'Контакты', 'parent' => 'o-kollektive'],
];

/**
 * Построение дерева меню из плоского списка.
 * Каждый узел получает 'children' => [...]. Дубли slug исключаются,
 * ссылки на несуществующего/невидимого родителя и циклы безопасны.
 */
function kv_build_menu(array $items): array
{
    // нормализуем: slug => item (первое вхождение важнее)
    $indexed = [];
    foreach ($items as $it) {
        $s = kv_slug((string)($it['slug'] ?? ''));
        if ($s !== '' && !isset($indexed[$s])) {
            $it['children'] = [];
            $indexed[$s] = $it;
        }
    }
    $roots   = [];
    $isRoot  = function (array $item) use (&$isRoot, &$indexed): bool {
        $p = kv_slug((string)($item['parent'] ?? ''));
        return $p === '' || !isset($indexed[$p]);
    };
    $attach  = function (string $slug, int $depth) use (&$attach, &$indexed, &$roots, &$isRoot): void {
        $item = $indexed[$slug];
        $p    = kv_slug((string)($item['parent'] ?? ''));
        if ($isRoot($item)) {
            $roots[$slug] = $item;
        } else {
            $child = $item; $child['children'] = [];
            $roots_parent = $p;
            // находим корень цепочки родителей (глубина ограничена)
            $node = $indexed[$p];
            $chain = [$slug];
            while (!$isRoot($node) && count($chain) < 10) {
                $chain[] = kv_slug((string)$node['slug']);
                $node = $indexed[kv_slug((string)$node['parent'])];
            }
            $rootSlug = kv_slug((string)$node['slug']);
            // вставляем по цепочке от корня вниз
            $ref = &$roots[$rootSlug];
            for ($i = count($chain) - 2; $i >= 0; $i--) {
                $cs = kv_slug((string)$chain[$i]);
                $found = false;
                foreach ($ref['children'] as &$c) {
                    if (kv_slug((string)$c['slug']) === $cs) { $ref = &$c; $found = true; break; }
                }
                unset($c);
                if (!$found) {
                    $ref['children'][] = ['slug' => $cs] + $indexed[$cs];
                    $lastKey = array_key_last($ref['children']);
                    $ref = &$ref['children'][$lastKey];
                }
            }
            unset($ref);
        }
    };
    foreach (array_keys($indexed) as $s) { $attach($s, 0); }
    // обход children рекурсивно: подтягиваем актуальные данные узлов
    $hydrate = function (array $node) use (&$hydrate, &$indexed): array {
        $s = kv_slug((string)($node['slug'] ?? ''));
        if (isset($indexed[$s])) {
            $kids = $node['children'] ?? [];
            $node = $indexed[$s];
            $node['children'] = [];
            foreach ($kids as $k) { $node['children'][] = $hydrate($k); }
        }
        return $node;
    };
    $out = [];
    foreach ($roots as $r) { $out[] = $hydrate($r); }
    return $out;
}

$menu = kv_build_menu(array_merge($rawMenu, $systemPts));

// Ближайшие мероприятия из афиши (для главной страницы)
$upcoming = kv_afisha_upcoming($afisha, 3);

// Последние 3 новости
$latestNews = array_slice($news, 0, 3);

// Общий контекст для рендера блоков и системных страниц
$ctx = compact('settings', 'pages', 'news', 'afisha', 'menu', 'upcoming', 'latestNews');

// --- Сервисные маршруты: RSS и sitemap (работают и как /feed.xml, и как ?feed) ---
// --- Дальнейшая маршрутизация ---
$systemPage = __DIR__ . '/includes/' . $pageSlug . '.php';
if (in_array($pageSlug, $systemSlugs, true) && is_file($systemPage)) {
    require $systemPage; // эти шаблоны сами подключают header/footer
    exit;
}
// Страница «Контакты» из админки: блоки + снизу форма обратной связи
if ($pageSlug === 'kontakty') {
    $kv_contact_logic_only = true;
    $current = ['slug' => 'kontakty', 'title' => $current['title'] ?? 'Контакты', 'blocks' => $current['blocks'] ?? []];
    require __DIR__ . '/includes/contact.php'; // сам подключит header, блоки, форму и footer
    exit;
}

// Все страницы (включая 404) рендерятся единым блочным шаблоном
require sprintf('%s/%s/page-default.php', $CONFIG['paths']['theme'], $CONFIG['site']['theme']);
