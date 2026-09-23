<?php
/**
 * theme/default/page-home.php — шаблон Главной страницы.
 * Доступные переменные из index.php:
 *   $current, $settings, $menu, $news, $afisha, $upcoming, $latestNews
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

require __DIR__ . '/header.php';

/* ---------- 1. HERO: главный экран с акцентом на фото/видео ---------- */
$hero = null;
foreach ($current['blocks'] ?? [] as $b) {
    if (($b['type'] ?? '') === 'hero') { $hero = $b; break; }
}
?>
<section class="hero">
    <div class="container hero-inner">
        <div class="hero-content">
            <p class="hero-kicker"><?= kv_e($settings['tagline'] ?? 'Волгоград · с 1991 года') ?></p>
            <h1 class="hero-title display"><?= kv_e($hero['title'] ?? 'Казачья Воля') ?></h1>
            <p class="hero-sub"><?= kv_e($hero['subtitle'] ?? '') ?></p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg"
                   href="<?= kv_e($hero['cta']['url'] ?? $settings['ticket_url'] ?? '#') ?>"
                   target="_blank" rel="noopener">🎟 Купить билет</a>
                <a class="btn btn-outline btn-lg"
                   href="tel:<?= kv_e($settings['phone_raw'] ?? '') ?>">Позвонить</a>
            </div>
        </div>
        <div class="hero-media">
            <img src="<?= kv_e($hero['image'] ?? 'theme/img/hero.svg') ?>"
                 alt="Ансамбль «Казачья Воля» на сцене"
                 width="640" height="480" fetchpriority="high">
        </div>
    </div>
</section>

<?php
/* ---------- 2–4. Остальные блоки главной, в порядке pages.json ---------- */
foreach ($current['blocks'] ?? [] as $b):
    switch ($b['type'] ?? ''):

        /* Тизер «О коллективе» */
        case 'about_teaser': ?>
            <section class="section">
                <div class="container split">
                    <div class="split-media">
                        <img src="theme/img/company.svg" alt="Артисты ансамбля" loading="lazy" width="560" height="420">
                    </div>
                    <div class="prose">
                        <h2 class="section-title"><?= kv_e($b['title'] ?? 'О коллективе') ?></h2>
                        <?= kv_text_to_html($b['text'] ?? '') ?>
                        <a class="link-arrow" href="index.php?page=<?= kv_e($b['link_page'] ?? 'o-kollektive') ?>">
                            Подробнее о коллективе →
                        </a>
                    </div>
                </div>
            </section>
        <?php break;

        /* Афиша: ближайшие мероприятия (данные — из afisha.json) */
        case 'afisha': ?>
            <section class="section section-tinted">
                <div class="container">
                    <div class="section-head">
                        <h2 class="section-title"><?= kv_e($b['title'] ?? 'Афиша') ?></h2>
                        <a class="link-arrow" href="index.php?page=afisha">Вся афиша →</a>
                    </div>
                    <div class="afisha-grid">
                        <?php foreach ($upcoming as $a): ?>
                            <article class="event-card">
                                <div class="event-date">
                                    <span class="event-day"><?= kv_e(date('d', strtotime($a['date']))) ?></span>
                                    <span class="event-month"><?= kv_e(kv_date_ru($a['date'])) ?></span>
                                </div>
                                <h3 class="event-title"><?= kv_e($a['title']) ?></h3>
                                <p class="event-venue">📍 <?= kv_e($a['venue'] ?? '') ?></p>
                                <p class="event-meta">🕐 <?= kv_e($a['time'] ?? '') ?> · <?= kv_e($a['price'] ?? '') ?></p>
                                <?php if (!empty($a['ticket_url'])): ?>
                                    <a class="btn btn-primary btn-block"
                                       href="<?= kv_e($a['ticket_url']) ?>"
                                       target="_blank" rel="noopener">Купить билет</a>
                                <?php else: ?>
                                    <span class="badge-free">Вход свободный</span>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php if (empty($upcoming)): ?>
                            <p class="muted">Ближайшие мероприятия уточняются.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php break;

        /* Новости: последние три (данные — из news.json) */
        case 'news': ?>
            <section class="section">
                <div class="container">
                    <div class="section-head">
                        <h2 class="section-title"><?= kv_e($b['title'] ?? 'Новости') ?></h2>
                        <a class="link-arrow" href="index.php?page=news">Все новости →</a>
                    </div>
                    <div class="news-grid">
                        <?php foreach ($latestNews as $n): ?>
                            <article class="news-card">
                                <img src="<?= kv_e($n['image'] ?? 'theme/img/news-placeholder.svg') ?>"
                                     alt="<?= kv_e($n['image_alt'] ?? '') ?>"
                                     loading="lazy" width="480" height="300">
                                <div class="news-body">
                                    <time class="news-date" datetime="<?= kv_e($n['date']) ?>">
                                        <?= kv_date_ru($n['date'] ?? '') ?>
                                    </time>
                                    <h3 class="news-title"><?= kv_e($n['title']) ?></h3>
                                    <p><?= kv_e(mb_strimwidth(strip_tags($n['text'] ?? ''), 0, 140, '…')) ?></p>
                                    <a class="link-arrow" href="index.php?page=news&amp;id=<?= (int)($n['id'] ?? 0) ?>">Читать →</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php break;

        default: /* hero уже выведен выше */
    endswitch;
endforeach;

require __DIR__ . '/footer.php';
