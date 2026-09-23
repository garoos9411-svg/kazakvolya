<?php
/**
 * ============================================================
 *  includes/afisha.php — страница «Афиша» (внешний файл-хранилище)
 *  Подключается из index.php, когда просят ?page=afisha.
 *  Доступны: $settings, $menu, $afisha, $current и т.д.
 * ============================================================
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

$current = [
    'slug'  => 'afisha',
    'title' => 'Афиша',
];

require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/header.php';

// Разбиваем афишу на предстоящие и архивные
$today    = date('Y-m-d');
$future   = array_values(array_filter($afisha, fn($a) => ($a['date'] ?? '') >= $today));
$past     = array_values(array_filter($afisha, fn($a) => ($a['date'] ?? '') <  $today));
usort($future, fn($a, $b) => strcmp($a['date'], $b['date']));
usort($past,   fn($a, $b) => strcmp($b['date'], $a['date']));
?>
<section class="page-hero">
    <div class="container"><h1 class="display">Афиша</h1></div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title">Ближайшие концерты</h2>
        <?php if (empty($future)): ?>
            <p class="muted">Расписание уточняется. Следите за новостями!</p>
        <?php endif; ?>
        <div class="afisha-grid">
            <?php foreach ($future as $a): ?>
                <article class="event-card">
                    <div class="event-date">
                        <span class="event-day"><?= kv_e(date('d', strtotime($a['date']))) ?></span>
                        <span class="event-month"><?= kv_date_ru($a['date']) ?></span>
                    </div>
                    <h3 class="event-title"><?= kv_e($a['title']) ?></h3>
                    <p class="event-venue">📍 <?= kv_e($a['venue'] ?? '') ?></p>
                    <p class="event-meta">🕐 <?= kv_e($a['time'] ?? '') ?> · <?= kv_e($a['price'] ?? '') ?></p>
                    <?php if (!empty($a['ticket_url'])): ?>
                        <a class="btn btn-primary btn-block" href="<?= kv_e($a['ticket_url']) ?>"
                           target="_blank" rel="noopener">Купить билет</a>
                    <?php else: ?>
                        <span class="badge-free">Вход свободный</span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($past): ?>
            <h2 class="section-title" style="margin-top:48px">Прошедшие события</h2>
            <ul class="archive-list">
                <?php foreach ($past as $a): ?>
                    <li><time><?= kv_date_ru($a['date']) ?></time> — <?= kv_e($a['title']) ?>
                        <span class="muted">(<?= kv_e($a['venue'] ?? '') ?>)</span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?php
require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/footer.php';
