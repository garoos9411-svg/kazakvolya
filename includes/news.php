<?php
/**
 * ============================================================
 *  includes/news.php — страница «Новости» (/news или ?page=news)
 *  /news/12 (или ?page=news&id=12) — детальная страница новости
 *  с разметкой Schema.org NewsArticle.
 *  Данные берутся из data/news.json (редактор — в админке).
 * ============================================================
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

// Показ одной новости по id (id уже разобран роутером в $itemId)
$single = null;
if ($itemId > 0) {
    foreach ($news as $n) {
        if ((int)($n['id'] ?? 0) === $itemId) { $single = $n; break; }
    }
    if ($single === null) {          // несуществующий id → на список новостей
        header('Location: ' . kv_url('news'), true, 302);
        exit;
    }
}

require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/header.php';
?>
<?php if ($single): ?>

    <article itemscope itemtype="https://schema.org/NewsArticle">
    <meta itemprop="datePublished" content="<?= kv_e($single['date'] ?? '') ?>">
    <meta itemprop="author" content="<?= kv_e($settings['site_name'] ?? 'Казачья Воля') ?>">
    <meta itemprop="publisher" content="<?= kv_e($settings['site_name'] ?? 'Казачья Воля') ?>">

    <section class="article-hero">
        <div class="container narrow">
            <p class="kicker"><a href="<?= kv_e(kv_url('news')) ?>" class="back-link">← Все новости</a></p>
            <h1 class="display article-title" itemprop="headline"><?= kv_e($single['title'] ?? '') ?></h1>
            <time class="news-date" datetime="<?= kv_e($single['date'] ?? '') ?>" itemprop="datePublished"><?= kv_date_ru($single['date'] ?? '') ?></time>
        </div>
    </section>

    <?php if (!empty($single['image'])): ?>
    <section class="section-tight">
        <div class="container narrow">
            <div class="media-frame article-cover">
                <img src="<?= kv_e($single['image']) ?>" alt="<?= kv_e($single['image_alt'] ?? '') ?>"
                     width="1200" height="675" fetchpriority="high" itemprop="image">
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="section">
        <div class="container narrow prose" itemprop="articleBody">
            <?= kv_text_to_html($single['text'] ?? '') ?>
        </div>
        <div class="container narrow share-row">
            <span class="share-label muted small">Поделиться:</span>
            <?php $shareUrl = rawurlencode(kv_canonical('news', (int)$single['id'])); $shareTxt = rawurlencode($single['title'] ?? ''); ?>
            <a class="share-btn vk" target="_blank" rel="noopener" aria-label="Поделиться ВКонтакте"
               href="https://vk.com/share.php?url=<?= $shareUrl ?>&title=<?= $shareTxt ?>">VK</a>
            <a class="share-btn tg" target="_blank" rel="noopener" aria-label="Поделиться в Telegram"
               href="https://t.me/share/url?url=<?= $shareUrl ?>&text=<?= $shareTxt ?>">TG</a>
            <button class="share-btn copy" data-copy="<?= kv_e(kv_canonical('news', (int)$single['id'])) ?>" aria-label="Скопировать ссылку">Копировать ссылку</button>
        </div>
        <div class="container narrow">
            <p><a class="btn btn-ghost" href="<?= kv_e(kv_url('news')) ?>">← Ко всем новостям</a></p>
        </div>
    </section>
    </article>

<?php else: ?>

    <section class="page-hero">
        <div class="container">
            <p class="kicker">Хроника коллектива</p>
            <h1 class="display page-hero-title">Новости</h1>
            <p class="hero-sub">Концерты, гастроли, премьеры и жизнь ансамбля «Казачья Воля».</p>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <?php if ($news): ?>
                <div class="news-filter" role="search">
                    <input type="search" id="newsFilter" class="filter-input" placeholder="Фильтр по названию…" aria-label="Фильтр новостей">
                    <a class="rss-chip" href="<?= kv_e(kv_url('feed.xml')) ?>">RSS ⟶</a>
                </div>
                <div class="news-grid news-grid-wide" id="newsList">
                    <?php foreach ($news as $n): ?>
                        <a class="news-card" data-title="<?= kv_e(mb_strtolower($n['title'] ?? '')) ?>"
                           href="<?= kv_e(kv_url('news', (int)($n['id'] ?? 0))) ?>">
                            <img src="<?= kv_e($n['image'] ?? 'theme/img/placeholder.svg') ?>"
                                 alt="<?= kv_e($n['image_alt'] ?? '') ?>" loading="lazy" width="800" height="450">
                            <div class="news-body">
                                <time class="news-date" datetime="<?= kv_e($n['date'] ?? '') ?>"><?= kv_date_ru($n['date'] ?? '') ?></time>
                                <h2 class="news-title display"><?= kv_e($n['title'] ?? '') ?></h2>
                                <p class="news-teaser"><?= kv_e(mb_strimwidth(strip_tags($n['text'] ?? ''), 0, 140, '…')) ?></p>
                                <span class="link-arrow">Читать →</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <p class="muted news-empty" id="newsEmpty" hidden>Ничего не найдено. Попробуйте другое слово.</p>
            <?php else: ?>
                <p class="muted">Новостей пока нет.</p>
            <?php endif; ?>
        </div>
    </section>

<?php endif; ?>
<?php
require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/footer.php';
