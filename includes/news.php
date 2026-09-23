<?php
/**
 * ============================================================
 *  includes/news.php — страница «Новости» (?page=news)
 *  Со ссылкой ?page=news&id=N показывает полный текст новости.
 * ============================================================
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

$current = ['slug' => 'news', 'title' => 'Новости'];
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
<section class="page-hero">
    <div class="container"><h1 class="display">Новости</h1></div>
</section>

<section class="section">
    <div class="container prose">
        <?php if ($single): ?>
            <article>
                <p class="news-date"><?= kv_date_ru($single['date'] ?? '') ?></p>
                <h2><?= kv_e($single['title'] ?? '') ?></h2>
                <?php if (!empty($single['image'])): ?>
                    <figure>
                        <img src="<?= kv_e($single['image']) ?>"
                             alt="<?= kv_e($single['image_alt'] ?? '') ?>" loading="lazy">
                    </figure>
                <?php endif; ?>
                <?= kv_text_to_html($single['text'] ?? '') ?>
                <p><a class="link-arrow" href="index.php?page=news">← Ко всем новостям</a></p>
            </article>
        <?php elseif ($news): ?>
            <?php foreach ($news as $n): ?>
                <article class="news-row">
                    <img class="news-row-img"
                         src="<?= kv_e($n['image'] ?? 'theme/img/news-placeholder.svg') ?>"
                         alt="<?= kv_e($n['image_alt'] ?? '') ?>" loading="lazy" width="200" height="130">
                    <div>
                        <p class="news-date"><?= kv_date_ru($n['date'] ?? '') ?></p>
                        <h2><a href="index.php?page=news&amp;id=<?= (int)($n['id'] ?? 0) ?>">
                            <?= kv_e($n['title'] ?? '') ?></a></h2>
                        <p><?= kv_e(mb_strimwidth(strip_tags($n['text'] ?? ''), 0, 200, '…')) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="muted">Новостей пока нет.</p>
        <?php endif; ?>
    </div>
</section>
<?php
require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/footer.php';
