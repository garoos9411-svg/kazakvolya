<?php
/**
 * ============================================================
 *  includes/blocks.php — рендеринг контентных блоков страниц
 * ============================================================
 *  Каждый блок из data/pages.json имеет поле "type". Функция
 *  kv_render_block() превращает такой блок в готовый HTML.
 *
 *  ВАЖНО про безопасность: все текстовые поля выводятся через
 *  kv_e() (htmlspecialchars). Поле "html" у текстовых блоков
 *  заполняет только администратор сайта (доверенный контент) и
 *  выводится как есть — это стандартная практика мини-CMS.
 *
 *  Доступные типы блоков (все редактируются в админке):
 *   hero, text, rich_html, numbers, gallery, team, list,
 *   quote, cta, contacts, afisha, news, media, page_header
 * ============================================================
 */

if (!defined('KV_SITE')) { exit('Access denied'); }

/**
 * Общая заголовочная часть секции: «надзаголовок + title + gold rule».
 */
function kv_section_head(array $b, string $cls = ''): void
{
    ?>
    <div class="container">
        <?php if (!empty($b['kicker'])): ?><p class="kicker"><?= kv_e($b['kicker']) ?></p><?php endif; ?>
        <?php if (!empty($b['title'])): ?><h2 class="section-title display"><?= kv_e($b['title']) ?></h2><?php endif; ?>
        <?php if (!empty($b['text'])): ?><div class="lead"><?= kv_text_to_html($b['text']) ?></div><?php endif; ?>
    </div>
    <?php
}

/**
 * Рендер одного блока страницы.
 */
function kv_render_block(array $b, array $ctx = []): void
{
    static $i = 0; // чередование светлых/тёмных секций
    $type  = $b['type'] ?? '';
    $shade = ($b['shade'] ?? '') === 'dark' ? ' section-dark' : '';
    $pad   = ($i++ % 2 === 1 && $type !== 'hero') ? ' is-alt' : '';

    switch ($type) {

        /* ---------- HERO: главный экран (видеоплей + параллакс-пятна) ---------- */
        case 'hero': ?>
        <section class="hero hero-video-mode">
            <?php $video = kv_clean_url($ctx['settings']['hero_video_url'] ?? ''); ?>
            <?php if ($video !== ''): ?>
            <!-- Фоновое видео: без звука, в цикле; при prefers-reduced-motion JS его остановит -->
            <video class="hero-bg" autoplay muted loop playsinline preload="metadata"
                   poster="<?= kv_e($ctx['settings']['hero_poster'] ?? 'theme/img/hero-bg.svg') ?>" tabindex="-1" aria-hidden="true">
                <source src="<?= kv_e($video) ?>" type="video/mp4">
            </video>
            <?php else: ?>
            <div class="hero-bg hero-bg-static" style="background-image:url('<?= kv_e($ctx['settings']['hero_poster'] ?? 'theme/img/hero-bg.svg') ?>')" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="hero-shade" aria-hidden="true"></div>
            <i class="orb orb-gold" data-parallax="0.05" aria-hidden="true"></i>
            <i class="orb orb-wine"  data-parallax="-0.03" aria-hidden="true"></i>
            <div class="container hero-inner">
                <div class="hero-content">
                    <?php if (!empty($b['kicker'])): ?><p class="hero-kicker is-reveal"><span class="pulse-dot"></span><?= kv_e($b['kicker']) ?></p><?php endif; ?>
                    <h1 class="hero-title display split-lines"><span><?= kv_e($b['title'] ?? '') ?></span></h1>
                    <?php if (!empty($b['subtitle'])): ?><p class="hero-sub is-reveal"><?= kv_e($b['subtitle']) ?></p><?php endif; ?>
                    <div class="hero-actions is-reveal">
                        <?php if (!empty($b['cta']['text'])): ?>
                            <a class="btn btn-primary btn-lg magnetic shine" href="<?= kv_e($b['cta']['url'] ?? '#') ?>"
                               target="_blank" rel="noopener"><?= kv_e($b['cta']['text']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($b['cta2']['text'])): ?>
                            <a class="btn btn-ghost btn-lg glass" href="<?= kv_e($b['cta2']['url'] ?? '#') ?>"><?= kv_e($b['cta2']['text']) ?></a>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($b['facts'])): ?>
                        <ul class="hero-facts" data-stagger>
                            <?php foreach ($b['facts'] as $f): ?><li class="is-reveal"><?= kv_e($f) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="hero-media">
                    <div class="media-frame floaty tilt" data-tilt="6">
                        <img src="<?= kv_e($b['image'] ?? 'theme/img/hero.svg') ?>"
                             alt="<?= kv_e($b['image_alt'] ?? 'Ансамбль «Казачья Воля»') ?>"
                             width="900" height="1100" fetchpriority="high">
                    </div>
                    <div class="hero-badge glass" aria-hidden="true"><span>с 1991 · Волгоград</span></div>
                </div>
            </div>
            <a class="scroll-hint" href="#main" aria-label="Прокрутить вниз"><span class="mouse"><i></i></span></a>
            <div class="marquee" aria-hidden="true">
                <div class="marquee-track"><span><?= kv_e($b['marquee'] ?? 'Донская слава ✦ Казачий разъезд ✦ Святой Дон ✦ Колядки Дона ✦ ') ?></span><span><?= kv_e($b['marquee'] ?? 'Донская слава ✦ Казачий разъезд ✦ Святой Дон ✦ Колядки Дона ✦ ') ?></span></div>
            </div>
        </section>
        <?php break;

        /* ---------- PAGE HEADER: шапка внутренней страницы ---------- */
        case 'page_header': ?>
        <section class="page-hero">
            <i class="ph-pattern" aria-hidden="true"></i>
            <i class="orb orb-gold ph-orb" data-parallax="0.04" aria-hidden="true"></i>
            <div class="container">
                <p class="kicker is-reveal"><?= kv_e($b['kicker'] ?? 'ГБУК ГАПП «Казачья Воля»') ?></p>
                <h1 class="display page-hero-title split-lines"><span><?= kv_e($b['title'] ?? '') ?></span></h1>
                <?php if (!empty($b['subtitle'])): ?><p class="hero-sub is-reveal"><?= kv_e($b['subtitle']) ?></p><?php endif; ?>
            </div>
        </section>
        <?php break;

        /* ---------- TEXT: обычный текст (каждая строка = абзац) ---------- */
        case 'text': ?>
        <section class="section<?= $pad ?><?= $shade ?>">
            <?php if (!empty($b['title']) || !empty($b['kicker'])): kv_section_head($b); endif; ?>
            <div class="container prose">
                <?= kv_text_to_html($b['html'] ?? '') ?>
            </div>
        </section>
        <?php break;

        /* ---------- RICH_HTML: текст с разрешённой разметкой ---------- */
        case 'rich_html': ?>
        <section class="section<?= $pad ?><?= $shade ?>">
            <?php if (!empty($b['title']) || !empty($b['kicker'])): kv_section_head($b); endif; ?>
            <div class="container prose">
                <?= kv_sanitize_html($b['html'] ?? '') ?>
            </div>
        </section>
        <?php break;

        /* ---------- NUMBERS: цифры и достижения ---------- */
        case 'numbers': ?>
        <section class="section<?= $pad ?><?= $shade ?>">
            <?php if (!empty($b['title']) || !empty($b['kicker'])): kv_section_head($b); endif; ?>
            <div class="container">
                <div class="numbers-grid" data-stagger>
                    <?php foreach ($b['items'] ?? [] as $item): ?>
                        <div class="number-card is-reveal">
                            <span class="number-value display" data-countup><?= kv_e($item['value'] ?? '') ?></span>
                            <span class="number-label"><?= kv_e($item['label'] ?? '') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php break;

        /* ---------- GALLERY: сетка фотографий ---------- */
        case 'gallery': ?>
        <section class="section<?= $pad ?><?= $shade ?>">
            <?php if (!empty($b['title']) || !empty($b['kicker'])): kv_section_head($b); endif; ?>
            <div class="container">
                <div class="gallery-grid" data-stagger>
                    <?php foreach ($b['items'] ?? [] as $gi => $g): ?>
                        <figure class="gallery-item is-reveal<?= ($gi % 3 === 1) ? ' gallery-tall' : '' ?>">
                            <img src="<?= kv_e($g['image'] ?? 'theme/img/placeholder.svg') ?>"
                                 alt="<?= kv_e($g['alt'] ?? '') ?>" loading="lazy">
                            <?php if (!empty($g['caption'])): ?><figcaption><span><?= kv_e($g['caption']) ?></span></figcaption><?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php break;

        /* ---------- TEAM: состав коллектива (карточки с фото) ---------- */
        case 'team': ?>
        <section class="section<?= $pad ?><?= $shade ?>">
            <?php if (!empty($b['title']) || !empty($b['kicker'])): kv_section_head($b); endif; ?>
            <div class="container">
                <div class="team-grid" data-stagger>
                    <?php foreach ($b['items'] ?? [] as $t): ?>
                        <article class="team-card is-reveal tilt" data-tilt="5">
                            <div class="team-photo">
                                <img src="<?= kv_e($t['image'] ?? 'theme/img/portrait.svg') ?>"
                                     alt="<?= kv_e($t['name'] ?? '') ?>" loading="lazy">
                            </div>
                            <h3 class="team-name display"><?= kv_e($t['name'] ?? '') ?></h3>
                            <p class="team-role"><?= kv_e($t['role'] ?? '') ?></p>
                            <?php if (!empty($t['note'])): ?><p class="team-note"><?= kv_e($t['note']) ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php break;

        /* ---------- LIST: нумерованный список (репертуар, награды…) ---------- */
        case 'list': ?>
        <section class="section<?= $pad ?><?= $shade ?>">
            <?php if (!empty($b['title']) || !empty($b['kicker'])): kv_section_head($b); endif; ?>
            <div class="container">
                <ol class="rep-list" data-stagger>
                    <?php foreach ($b['items'] ?? [] as $i2 => $item): ?>
                        <li class="rep-item is-reveal">
                            <span class="rep-num display"><?= str_pad((string)($i2 + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <div class="rep-body">
                                <h3 class="rep-name display"><?= kv_e($item['name'] ?? '') ?></h3>
                                <?php if (!empty($item['note'])): ?><p class="rep-note"><?= kv_e($item['note']) ?></p><?php endif; ?>
                            </div>
                            <?php if (!empty($item['meta'])): ?><span class="rep-meta"><?= kv_e($item['meta']) ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </section>
        <?php break;

        /* ---------- QUOTE: большая цитата на тёмной подложке ---------- */
        case 'quote': ?>
        <section class="section quote-section<?= $pad ?><?= $shade ?: ' section-dark' ?>">
            <div class="container">
                <figure class="pull-quote is-reveal">
                    <blockquote class="display">«<?= kv_e($b['text'] ?? '') ?>»</blockquote>
                    <?php if (!empty($b['author'])): ?><figcaption><?= kv_e($b['author']) ?></figcaption><?php endif; ?>
                </figure>
            </div>
        </section>
        <?php break;

        /* ---------- CTA: призыв к действию ---------- */
        case 'cta': ?>
        <section class="section cta-section<?= $pad ?><?= $shade ?>">
            <div class="container cta-panel is-reveal">
                <div>
                    <?php if (!empty($b['kicker'])): ?><p class="kicker"><?= kv_e($b['kicker']) ?></p><?php endif; ?>
                    <h2 class="display"><?= kv_e($b['title'] ?? '') ?></h2>
                    <?php if (!empty($b['text'])): ?><p class="muted"><?= kv_e($b['text']) ?></p><?php endif; ?>
                </div>
                <div class="cta-actions">
                    <?php if (!empty($b['cta']['text'])): ?>
                        <a class="btn btn-primary btn-lg" href="<?= kv_e($b['cta']['url'] ?? '#') ?>"
                           target="_blank" rel="noopener"><?= kv_e($b['cta']['text']) ?></a>
                    <?php endif; ?>
                    <?php if (!empty($b['cta2']['text'])): ?>
                        <a class="btn btn-ghost btn-lg" href="<?= kv_e($b['cta2']['url'] ?? '#') ?>"><?= kv_e($b['cta2']['text']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php break;

        /* ---------- CONTACTS: контакты + карта ---------- */
        case 'contacts': ?>
        <section class="section<?= $pad ?><?= $shade ?>">
            <?php if (!empty($b['title']) || !empty($b['kicker'])): kv_section_head($b); endif; ?>
            <div class="container contacts-grid">
                <div class="prose">
                    <?= kv_text_to_html($b['text'] ?? '') ?>
                    <dl class="contacts-list">
                        <dt>Адрес</dt><dd><?= kv_e($ctx['settings']['address'] ?? '') ?></dd>
                        <dt>Телефон</dt><dd><a href="tel:<?= kv_e($ctx['settings']['phone_raw'] ?? '') ?>"><?= kv_e($ctx['settings']['phone'] ?? '') ?></a></dd>
                        <dt>E-mail</dt><dd><a href="mailto:<?= kv_e($ctx['settings']['email'] ?? '') ?>"><?= kv_e($ctx['settings']['email'] ?? '') ?></a></dd>
                        <dt>Часы работы</dt><dd><?= kv_e($ctx['settings']['hours'] ?? '') ?></dd>
                    </dl>
                    <a class="btn btn-primary" href="tel:<?= kv_e($ctx['settings']['phone_raw'] ?? '') ?>">Позвонить</a>
                    <a class="btn btn-ghost" href="<?= kv_e($ctx['settings']['vk_url'] ?? '#') ?>" target="_blank" rel="noopener">ВКонтакте</a>
                </div>
                <div class="map-frame">
                    <?php if (!empty($b['map_html'])): ?>
                        <?= $b['map_html'] /* iframe карты от администратора */ ?>
                    <?php else: ?>
                        <div class="map-placeholder" role="img" aria-label="Схема проезда">
                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="#800020" stroke-width="1.6" aria-hidden="true"><path d="M12 21s-7-5.4-7-11a7 7 0 1114 0c0 5.6-7 11-7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>
                            <p><?= kv_e($ctx['settings']['address'] ?? '') ?></p>
                            <small>Администратор может вставить сюда iframe карты (блок «Контакты» в админке)</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php break;

        /* ---------- AFISHA: ближайшие концерты (данные из afisha.json) ---------- */
        case 'afisha': ?>
        <section class="section<?= $pad ?><?= $shade ?>">
            <div class="container">
                <div class="section-head">
                    <div>
                        <?php if (!empty($b['kicker'])): ?><p class="kicker"><?= kv_e($b['kicker']) ?></p><?php endif; ?>
                        <h2 class="section-title display"><?= kv_e($b['title'] ?? 'Афиша') ?></h2>
                    </div>
                    <a class="link-arrow" href="index.php?page=afisha">Вся афиша →</a>
                </div>
                <div class="event-stack" data-stagger>
                    <?php foreach ($ctx['upcoming'] as $a): $d = strtotime($a['date']); ?>
                        <article class="event-row is-reveal">
                            <time class="event-date display" datetime="<?= kv_e($a['date']) ?>">
                                <span class="event-day"><?= date('d', $d) ?></span>
                                <span class="event-mon"><?= kv_e(kv_month_ru((int)date('n', $d))) ?></span>
                            </time>
                            <div class="event-info">
                                <h3 class="event-title display"><?= kv_e($a['title']) ?></h3>
                                <p class="event-meta"><?= kv_e($a['venue'] ?? '') ?> · <?= kv_e($a['time'] ?? '') ?> ч</p>
                            </div>
                            <div class="event-right">
                                <span class="event-price"><?= kv_e($a['price'] ?? '') ?></span>
                                <?php if (!empty($a['ticket_url'])): ?>
                                    <a class="btn btn-primary" href="<?= kv_e($a['ticket_url']) ?>"
                                       target="_blank" rel="noopener">Купить билет</a>
                                <?php else: ?>
                                    <span class="badge-free">Вход свободный</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($ctx['upcoming'])): ?>
                        <p class="muted">Ближайшие мероприятия уточняются.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php break;

        /* ---------- NEWS: последние новости (данные из news.json) ---------- */
        case 'news': ?>
        <section class="section<?= $pad ?><?= $shade ?>">
            <div class="container">
                <div class="section-head">
                    <div>
                        <?php if (!empty($b['kicker'])): ?><p class="kicker"><?= kv_e($b['kicker']) ?></p><?php endif; ?>
                        <h2 class="section-title display"><?= kv_e($b['title'] ?? 'Новости') ?></h2>
                    </div>
                    <a class="link-arrow" href="index.php?page=news">Все новости →</a>
                </div>
                <div class="news-grid" data-stagger>
                    <?php foreach ($ctx['latestNews'] as $n): ?>
                        <a class="news-card is-reveal" href="index.php?page=news&amp;id=<?= (int)($n['id'] ?? 0) ?>">
                            <img src="<?= kv_e($n['image'] ?? 'theme/img/placeholder.svg') ?>"
                                 alt="<?= kv_e($n['image_alt'] ?? '') ?>" loading="lazy">
                            <div class="news-body">
                                <time class="news-date" datetime="<?= kv_e($n['date'] ?? '') ?>"><?= kv_date_ru($n['date'] ?? '') ?></time>
                                <h3 class="news-title display"><?= kv_e($n['title'] ?? '') ?></h3>
                                <p class="news-teaser"><?= kv_e(mb_strimwidth(strip_tags($n['text'] ?? ''), 0, 120, '…')) ?></p>
                                <span class="link-arrow">Читать →</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php break;

        /* ---------- MEDIA: фото слева / текст справа ---------- */
        case 'media': ?>
        <section class="section<?= $pad ?><?= $shade ?>">
            <div class="container split">
                <div class="split-media media-frame is-reveal">
                    <img src="<?= kv_e($b['image'] ?? 'theme/img/placeholder.svg') ?>"
                         alt="<?= kv_e($b['image_alt'] ?? '') ?>" loading="lazy">
                </div>
                <div class="prose is-reveal">
                    <?php if (!empty($b['kicker'])): ?><p class="kicker"><?= kv_e($b['kicker']) ?></p><?php endif; ?>
                    <?php if (!empty($b['title'])): ?><h2 class="section-title display"><?= kv_e($b['title']) ?></h2><?php endif; ?>
                    <?= kv_text_to_html($b['html'] ?? '') ?>
                    <?php if (!empty($b['cta']['text'])): ?>
                        <a class="link-arrow" href="<?= kv_e($b['cta']['url'] ?? '#') ?>"><?= kv_e($b['cta']['text']) ?> →</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php break;

        default:
            /* Неизвестный тип не ломает страницу — просто молча пропускаем */
    }
}

/**
 * Простой санитайзер HTML для блока «rich_html»:
 * оставляем только безопасные теги форматирования, всё остальное вырезается.
 */
function kv_sanitize_html(string $html): string
{
    $allowed = '<p><br><strong><b><em><i><u><s><a><ul><ol><li><h2><h3><h4><blockquote><hr>';
    $html = strip_tags($html, $allowed);
    // ссылки — только http/https/mailto, без javascript:
    $html = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>/i', function ($m) {
        $href = trim($m[1]);
        $ok = preg_match('#^(https?://|mailto:|tel:|/|index\.php)#i', $href);
        return $ok ? '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" target="_blank" rel="noopener">' : '';
    }, $html);
    return $html;
}
