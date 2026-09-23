<?php
/**
 * ============================================================
 *  includes/news.php — страница «Новости» (?page=news)
 *  Со ссылкой ?page=news&id=N показывает полный текст новости.
 *  Данные берутся из data/news.json (редактор — в админке).
 * ============================================================
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/header.php';

// Показ одной новости по id
$single = null;
if (!empty($_GET['id'])) {
    $wantId = (int)$_GET['id'];
    foreach ($news as $n) {
        if ((int)($n['id'] ?? 0) === $wantId) { $single = $n; break; }
    }
}
?>
<?php if ($single): ?>

    <section class="article-hero">
        <div class="container narrow">
            <p class="kicker"><a href="index.php?page=news" class="back-link">← Все новости</a></p>
            <h1 class="display article-title"><?= kv_e($single['title'] ?? '') ?></h1>
            <time class="news-date" datetime="<?= kv_e($single['date'] ?? '') ?>"><?= kv_date_ru($single['date'] ?? '') ?></time>
        </div>
    </section>

    <?php if (!empty($single['image'])): ?>
    <section class="section-tight">
        <div class="container narrow">
            <div class="media-frame article-cover">
                <img src="<?= kv_e($single['image']) ?>" alt="<?= kv_e($single['image_alt'] ?? '') ?>" fetchpriority="high">
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="section">
        <div class="container narrow prose">
            <?= kv_text_to_html($single['text'] ?? '') ?>
            <p><a class="btn btn-ghost" href="index.php?page=news">← Ко всем новостям</a></p>
        </div>
    </section>

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
                <div class="news-grid news-grid-wide">
                    <?php foreach ($news as $n): ?>
                        <a class="news-card" href="index.php?page=news&amp;id=<?= (int)($n['id'] ?? 0) ?>">
                            <img src="<?= kv_e($n['image'] ?? 'theme/img/placeholder.svg') ?>"
                                 alt="<?= kv_e($n['image_alt'] ?? '') ?>" loading="lazy">
                            <div class="news-body">
                                <time class="news-date" datetime="<?= kv_e($n['date'] ?? '') ?>"><?= kv_date_ru($n['date'] ?? '') ?></time>
                                <h2 class="news-title display"><?= kv_e($n['title'] ?? '') ?></h2>
                                <p class="news-teaser"><?= kv_e(mb_strimwidth(strip_tags($n['text'] ?? ''), 0, 140, '…')) ?></p>
                                <span class="link-arrow">Читать →</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted">Новостей пока нет.</p>
            <?php endif; ?>
        </div>
    </section>

<?php endif; ?>
<?php
require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/footer.php';
