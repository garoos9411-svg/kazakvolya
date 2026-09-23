<?php
/**
 * ============================================================
 *  includes/afisha.php — страница «Афиша» (?page=afisha)
 *  Данные берутся из data/afisha.json (редактор — в админке).
 * ============================================================
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/header.php';

// Разбиваем афишу на предстоящие и архивные
$today  = date('Y-m-d');
$future = array_values(array_filter($afisha, fn($a) => ($a['date'] ?? '') >= $today));
$past   = array_values(array_filter($afisha, fn($a) => ($a['date'] ?? '') <  $today));
usort($future, fn($a, $b) => strcmp($a['date'], $b['date']));
usort($past,   fn($a, $b) => strcmp($b['date'], $a['date']));
?>
<section class="page-hero">
    <div class="container">
        <p class="kicker">Билеты уже в продаже</p>
        <h1 class="display page-hero-title">Афиша</h1>
        <p class="hero-sub">Концертный сезон ансамбля «Казачья Воля» в Волгограде и на гастролях.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title display">Ближайшие концерты</h2>
        <?php if (empty($future)): ?>
            <p class="muted">Расписание уточняется. Следите за новостями!</p>
        <?php endif; ?>
        <div class="event-stack">
            <?php foreach ($future as $a): $d = strtotime($a['date']); ?>
                <article class="event-row">
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
        </div>

        <?php if ($past): ?>
            <h2 class="section-title display" style="margin-top:56px">Прошедшие события</h2>
            <ul class="archive-list">
                <?php foreach ($past as $a): ?>
                    <li>
                        <time class="archive-date"><?= kv_date_ru($a['date']) ?></time>
                        <span class="archive-title"><?= kv_e($a['title']) ?></span>
                        <span class="muted"><?= kv_e($a['venue'] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
<?php
require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/footer.php';
