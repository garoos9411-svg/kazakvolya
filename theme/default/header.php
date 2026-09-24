<?php if (!defined('KV_SITE')) { exit('Access denied'); } ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    /* ---------- SEO: title, canonical, OG, Twitter ---------- */
    $pageTitle   = ($current['title'] ?? '') !== '' ? $current['title'] : ($settings['site_name'] ?? 'Казачья Воля');
    $pageDesc    = kv_clean_string($current['seo_description'] ?? $settings['seo_description'] ?? 'Ансамбль казачьей песни «Казачья Воля», Волгоград: концерты, гастроли, творческие вечера.');
    $canonicalId = (int)($single['id'] ?? 0);
    $ogImage     = kv_clean_url($single['image'] ?? $settings['og_image'] ?? 'theme/img/hero-bg.jpg');
    $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host        = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? ($CONFIG['site']['domain'] ?? 'kazakvolya.ru')));
    if ($host !== '' && !str_contains($host, ':')) {
        $port = (int)($_SERVER['SERVER_PORT'] ?? 80);
        if ($port !== 80 && $port !== 443) { $host .= ':' . $port; }
    }
    $ogImageUrl  = str_starts_with($ogImage, 'http') ? $ogImage : $scheme . '://' . $host . kv_base_url() . '/' . ltrim($ogImage, '/');
    ?>
    <title><?= kv_e($pageTitle) ?> — <?= kv_e($settings['site_name'] ?? 'Казачья Воля') ?></title>
    <meta name="description" content="<?= kv_e($pageDesc) ?>">
    <meta name="theme-color" content="#141014">
    <link rel="canonical" href="<?= kv_e(kv_canonical($current['slug'] ?? '', $canonicalId)) ?>">
    <link rel="alternate" type="application/rss+xml" title="Новости — Казачья Воля" href="<?= kv_e(kv_url('feed.xml')) ?>">

    <meta property="og:type" content="<?= $canonicalId > 0 ? 'article' : 'website' ?>">
    <meta property="og:site_name" content="<?= kv_e($settings['site_name'] ?? 'Казачья Воля') ?>">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:title" content="<?= kv_e($pageTitle) ?>">
    <meta property="og:description" content="<?= kv_e($pageDesc) ?>">
    <meta property="og:url" content="<?= kv_e(kv_canonical($current['slug'] ?? '', $canonicalId)) ?>">
    <?php if ($ogImage !== ''): ?><meta property="og:image" content="<?= kv_e($ogImageUrl) ?>"><?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">

    <?php /* Google Fonts с локальным фолбэком: если шрифты не загрузятся —
             браузер возьмёт системные Georgia / Arial (см. style.min.css) */ ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Inter:wght@400;500;600;700&display=swap&subset=cyrillic" rel="stylesheet">

    <link rel="stylesheet" href="<?= kv_e(kv_base_url()) ?>/theme/css/style.min.css?v=10">
    <link rel="icon" href="<?= kv_e(kv_base_url()) ?>/theme/img/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= kv_e(kv_base_url()) ?>/theme/img/favicon.svg">

    <script>document.documentElement.className+=' js-ready js-enabled';</script>
</head>
<body>

<!-- Декоративные «плавающие» пятна на фоне всего сайта -->
<div class="bg-blobs" aria-hidden="true"><i></i><i></i><i></i></div>

<!-- Перемычка для клавиатуры -->
<a class="skip-link" href="#main">Перейти к содержимому</a>

<header class="site-header" id="top">
    <div class="container header-inner">

        <a class="logo" href="<?= kv_e(kv_url('')) ?>" aria-label="На главную">
            <svg class="logo-mark" width="40" height="40" viewBox="0 0 32 32" aria-hidden="true">
                <circle cx="16" cy="16" r="15" fill="none" stroke="#D4AF37" stroke-width="1"/>
                <path d="M16 6l3.4 5h-6.8L16 6zm-7 7.5h14l-1.7 3.4H10.7l-1.7-3.4zM12 19h8l-4 6-4-6z" fill="#D4AF37"/>
            </svg>
            <span class="logo-text">
                <strong>Казачья&nbsp;Воля</strong>
                <small>ансамбль казачьей песни</small>
            </span>
        </a>

        <!-- Поиск по сайту (Ctrl+K) -->
        <button class="nav-search-btn" id="searchBtn" aria-label="Поиск по сайту (Ctrl+K)" title="Поиск — Ctrl+K">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <kbd>⌘K</kbd>
        </button>

        <!-- Бургер (виден только на мобильных) -->
        <button class="burger" id="burger" aria-label="Открыть меню"
                aria-expanded="false" aria-controls="nav">
            <span></span><span></span><span></span>
        </button>

        <nav class="site-nav" id="nav" aria-label="Основное меню">
            <ul class="nav-list" data-stagger>
                <?php
                /* Рекурсивный рендер пункта меню (поддержка вложенных выпадающих списков) */
                $kv_nav_item = function (array $m, int $level = 0) use (&$kv_nav_item): void {
                    $slug     = $m['slug'] ?? '';
                    $title    = kv_e($m['menu_title'] ?? $m['title'] ?? '');
                    $children = array_values(array_filter($m['children'] ?? []));
                    $active   = ($slug === ($GLOBALS['current']['slug'] ?? ''));
                    foreach ($children as $c) {
                        if (($c['slug'] ?? '') === ($GLOBALS['current']['slug'] ?? '')) { $active = true; }
                    }
                    ?>
                    <li class="nav-item<?= $children ? ' has-sub' : '' ?> lvl-<?= $level ?><?= $active ? ' is-current' : '' ?>">
                        <?php if ($children): ?>
                            <button type="button" class="nav-link nav-parent <?= $active ? 'is-active' : '' ?>"
                                    aria-expanded="false" aria-haspopup="true">
                                <?= $title ?><svg class="caret" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <ul class="sub-menu" role="menu">
                                <?php foreach ($children as $c): $kv_nav_item($c, $level + 1); endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <a href="<?= kv_e(kv_url($slug)) ?>" class="nav-link is-reveal<?= $active ? ' is-active' : '' ?>"><?= $title ?></a>
                        <?php endif; ?>
                    </li>
                    <?php
                };
                foreach ($menu as $m): $kv_nav_item($m); endforeach; ?>
            </ul>
            <a class="btn btn-gold nav-cta magnetic shine"
               href="<?= kv_e($settings['ticket_url'] ?? '#') ?>"
               target="_blank" rel="noopener">Купить билет</a>
        </nav>

    </div>
</header>

<!-- Затемнение фона при открытом мобильном меню -->
<div class="nav-overlay" id="navOverlay" aria-hidden="true"></div>

<!-- Оверлей поиска: индексирует страницы и новости на клиенте -->
<div class="search-modal" id="searchModal" role="dialog" aria-modal="true" aria-label="Поиск по сайту" hidden>
    <div class="search-panel glass">
        <input type="search" id="searchInput" placeholder="Концерты, репертуар, новости…"
               autocomplete="off" aria-label="Строка поиска">
        <ul class="search-results" id="searchResults" role="listbox"></ul>
        <p class="search-hint muted small">↑↓ — навигация · Enter — открыть · Esc — закрыть</p>
    </div>
</div>
<script type="application/json" id="searchIndex">[
<?php
$kv_flat = function (array $nodes) use (&$kv_flat): array {
    $out = [];
    foreach ($nodes as $n) {
        $out[] = $n;
        $out   = array_merge($out, $kv_flat($n['children'] ?? []));
    }
    return $out;
};
foreach ($kv_flat($menu) as $m): ?>{"t":<?= json_encode($m['menu_title'] ?? $m['title'] ?? '', JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,"u":<?= json_encode(kv_url($m['slug']), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,"k":"страница"},
<?php endforeach; ?>
<?php foreach ($news as $n): ?>{"t":<?= json_encode($n['title'] ?? '', JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,"u":<?= json_encode(kv_url('news', (int)($n['id'] ?? 0)), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,"k":"новость · <?= kv_e(date('d.m.Y', strtotime($n['date'] ?? 'now'))) ?>","x":<?= json_encode(mb_strimwidth(strip_tags($n['text'] ?? ''), 0, 90, '…'), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>},
<?php endforeach; ?>
{"t":"Купить билеты","u":<?= json_encode($settings['ticket_url'] ?? '#', JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,"k":"внешняя ссылка"}
]</script>

<main id="main">
<?php
/* ---------- Хлебные крошки ---------- */
$crumbs = [['title' => 'Главная', 'url' => kv_url('')]];
if (($current['slug'] ?? '') !== 'home') {
    $crumbs[] = ['title' => $current['title'] ?? '', 'url' => !empty($single) ? kv_url('news') : ''];
}
if (!empty($single)) {
    $crumbs[] = ['title' => $single['title'] ?? '', 'url' => ''];
}
if (count($crumbs) > 1): ?>
<nav class="breadcrumbs" aria-label="Хлебные крошки">
    <ol class="container" itemscope itemtype="https://schema.org/BreadcrumbList">
        <?php foreach ($crumbs as $ci => $c): $last = $ci === count($crumbs) - 1; ?>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <?php if (!$last && $c['url'] !== ''): ?>
                <a itemprop="item" href="<?= kv_e($c['url']) ?>"><span itemprop="name"><?= kv_e($c['title']) ?></span></a>
            <?php else: ?>
                <span aria-current="page"><?= kv_e($c['title']) ?></span>
            <?php endif; ?>
            <meta itemprop="position" content="<?= $ci + 1 ?>">
        </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
