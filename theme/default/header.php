<?php if (!defined('KV_SITE')) { exit('Access denied'); } ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= kv_e($current['title'] ?? '') ?> — <?= kv_e($settings['site_name'] ?? 'Казачья Воля') ?></title>
    <meta name="description" content="<?= kv_e($settings['seo_description'] ?? 'Ансамбль казачьей песни «Казачья Воля», Волгоград') ?>">

    <?php /* Google Fonts с локальным фолбэком: если шрифты не загрузятся —
             браузер возьмёт системные Georgia / Arial (см. style.min.css) */ ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Inter:wght@400;500;600;700&display=swap&subset=cyrillic" rel="stylesheet">

    <link rel="stylesheet" href="theme/css/style.min.css?v=3">
    <link rel="icon" href="theme/img/favicon.svg" type="image/svg+xml">
</head>
<body class="preload">

<!-- Плавное появление страницы при загрузке (класс снимает script.min.js) -->
<div class="page-veil" id="pageVeil" aria-hidden="true"></div>

<!-- Декоративные «плавающие» пятна на фоне всего сайта -->
<div class="bg-blobs" aria-hidden="true"><i></i><i></i><i></i></div>

<!-- Перемычка для клавиатуры -->
<a class="skip-link" href="#main">Перейти к содержимому</a>

<header class="site-header" id="top">
    <div class="container header-inner">

        <a class="logo" href="index.php" aria-label="На главную">
            <svg class="logo-mark" width="40" height="40" viewBox="0 0 32 32" aria-hidden="true">
                <circle cx="16" cy="16" r="15" fill="none" stroke="#D4AF37" stroke-width="1"/>
                <path d="M16 6l3.4 5h-6.8L16 6zm-7 7.5h14l-1.7 3.4H10.7l-1.7-3.4zM12 19h8l-4 6-4-6z" fill="#800020"/>
            </svg>
            <span class="logo-text">
                <strong>Казачья&nbsp;Воля</strong>
                <small>ансамбль казачьей песни</small>
            </span>
        </a>

        <!-- Бургер (виден только на мобильных) -->
        <button class="burger" id="burger" aria-label="Открыть меню"
                aria-expanded="false" aria-controls="nav">
            <span></span><span></span><span></span>
        </button>

        <nav class="site-nav glass" id="nav" aria-label="Основное меню">
            <ul class="nav-list" data-stagger>
                <?php foreach ($menu as $m): ?>
                    <li>
                        <a href="index.php?page=<?= kv_e($m['slug']) ?>"
                           class="is-reveal <?= ($m['slug'] === ($current['slug'] ?? '')) ? 'is-active' : '' ?>">
                            <?= kv_e($m['menu_title'] ?? $m['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn-primary nav-cta magnetic"
               href="<?= kv_e($settings['ticket_url'] ?? '#') ?>"
               target="_blank" rel="noopener">Купить билет</a>
        </nav>

    </div>
</header>

<!-- Затемнение фона при открытом мобильном меню -->
<div class="nav-overlay" id="navOverlay" aria-hidden="true"></div>

<main id="main">
