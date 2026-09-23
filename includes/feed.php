<?php
/**
 *  includes/feed.php — RSS 2.0 (новости) + JSON Feed 1.1 (?format=json)
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

$siteName = $settings['site_name'] ?? 'Казачья Воля';
$siteDesc = $settings['seo_description'] ?? ($CONFIG['site']['description'] ?? '');
$items    = array_slice($news, 0, 20);

if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'version' => 'https://jsonfeed.org/version/1.1',
        'title'   => $siteName . ' — Новости',
        'home_page_url'    => kv_canonical('news'),
        'feed_url'         => kv_canonical('feed.xml'),
        'items' => array_map(fn($n) => [
            'id'    => (string)($n['id'] ?? ''),
            'url'   => kv_canonical('news', (int)($n['id'] ?? 0)),
            'title' => $n['title'] ?? '',
            'content_text' => $n['text'] ?? '',
            'date_published' => ($n['date'] ?? '') . 'T09:00:00+03:00',
        ], $items),
    ], JSON_UNESCAPED_UNICODE);
    return;
}

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
  <title><?= kv_e($siteName) ?> — Новости</title>
  <link><?= kv_canonical('news') ?></link>
  <description><?= kv_e($siteDesc) ?></description>
  <language>ru</language>
  <lastBuildDate><?= (new DateTimeImmutable('now'))->format(DateTimeInterface::RSS) ?></lastBuildDate>
  <generator>KazakVolya miniCMS</generator>
<?php foreach ($items as $n): ?>
  <item>
    <title><?= kv_e($n['title'] ?? '') ?></title>
    <link><?= kv_canonical('news', (int)($n['id'] ?? 0)) ?></link>
    <guid isPermaLink="true"><?= kv_canonical('news', (int)($n['id'] ?? 0)) ?></guid>
    <pubDate><?= (new DateTimeImmutable($n['date'] ?? 'now'))->format(DateTimeInterface::RSS) ?></pubDate>
    <description><?= kv_e(mb_strimwidth(strip_tags($n['text'] ?? ''), 0, 280, '…')) ?></description>
  </item>
<?php endforeach; ?>
</channel>
</rss>
