<?php
/**
 *  includes/sitemap.php — XML-sitemap для поисковых систем
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

$urls = [['loc' => kv_canonical(''), 'prio' => '1.0']];
foreach ($pages as $p) {
    if (!empty($p['visible'])) {
        $urls[] = ['loc' => kv_canonical(kv_slug($p['slug'] ?? '')), 'prio' => '0.8'];
    }
}
$urls[] = ['loc' => kv_canonical('afisha'),  'prio' => '0.9'];
$urls[] = ['loc' => kv_canonical('news'),    'prio' => '0.9'];
$urls[] = ['loc' => kv_canonical('contact'), 'prio' => '0.6'];
foreach (array_slice($news, 0, 50) as $n) {
    $urls[] = ['loc' => kv_canonical('news', (int)($n['id'] ?? 0)), 'prio' => '0.6'];
}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
  <url><loc><?= kv_e($u['loc']) ?></loc><changefreq>weekly</changefreq><priority><?= $u['prio'] ?></priority></url>
<?php endforeach; ?>
</urlset>
