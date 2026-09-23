<?php if (!defined('KV_SITE')) { exit('Access denied'); } ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= kv_e($current['title']) ?> — <?= kv_e($settings['site_name'] ?? 'Казачья Воля') ?></title>
    <meta name="description" content="<?= kv_e($settings['seo_description'] ?? 'Ансамбль казачьей песни') ?>">

    <?php /* Google Fonts с локальным фолбэком: если шрифты не загрузятся —
             браузер возьмёт системные Georgia / Arial (см. style.min.css) */ ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Inter:wght@400;500;600&display=swap&subset=cyrillic" rel="stylesheet">

    <link rel="stylesheet" href="theme/css/style.min.css?v=1">
    <link rel="icon" href="theme/img/favicon.svg" type="image/svg+xml">
</head>
<body>

<!-- Перемычка для клавиатуры -->
<a class="skip-link" href="#main">Перейти к содержимому</a>

<header class="site-header" id="top">
    <div class="container header-inner">

        <a class="logo" href="index.php" aria-label="На главную">
            <svg width="34" height="34" viewBox="0 0 32 32" aria-hidden="true">
                <path d="M16 2l4 6h-8l4-6zm-9 9h18l-2 4H9l-2-4zm3 6h12l-7 7-7-7z" fill="#800020"/>
                <circle cx="16" cy="14" r="2" fill="#D4AF37"/>
            </svg>
            <span class="logo-text">
                <strong>Казачья Воля</strong>
                <small>ансамбль казачьей песни</small>
            </span>
        </a>

        <!-- Бургер (виден только на мобильных) -->
        <button class="burger" id="burger" aria-label="Открыть меню"
                aria-expanded="false" aria-controls="nav">
            <span></span><span></span><span></span>
        </button>

        <nav class="site-nav" id="nav" aria-label="Основное меню">
            <ul class="nav-list">
                <?php foreach ($menu as $m): ?>
                    <li>
                        <a href="index.php?page=<?= kv_e($m['slug']) ?>"
                           class="<?= $m['slug'] === $current['slug'] ? 'is-active' : '' ?>">
                            <?= kv_e($m['menu_title'] ?? $m['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="btn btn-primary nav-cta"
               href="<?= kv_e($settings['ticket_url'] ?? '#') ?>"
               target="_blank" rel="noopener">Купить билет</a>
        </nav>

    </div>
</header>

<main id="main">
