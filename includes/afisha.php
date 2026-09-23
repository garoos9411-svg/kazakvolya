<?php
/**
 * ============================================================
 *  includes/afisha.php — страница «Афиша» (/afisha)
 *  + /afisha?view=calendar — календарный вид по месяцам
 *  + ?action=export — скачивание всей афиши в .ics
 *  Карточки событий размечены Schema.org MusicEvent.
 * ============================================================
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

// Экспорт в календарь — до вывода HTML
if (($_GET['action'] ?? '') === 'export') {
    require __DIR__ . '/export-ics.php';
    exit;
}

$today  = date('Y-m-d');
$future = array_values(array_filter($afisha, fn($a) => ($a['date'] ?? '') >= $today));
$past   = array_values(array_filter($afisha, fn($a) => ($a['date'] ?? '') <  $today));
usort($future, fn($a, $b) => strcmp($a['date'], $b['date']));
usort($past,   fn($a, $b) => strcmp($b['date'], $a['date']));

$isCalendar = ($_GET['view'] ?? '') === 'calendar';

/* одна строка события для обоих режимов */
$renderEvent = function (array $a, bool $reveal = false): void {
    $d = strtotime($a['date']);
    $icsName = kv_slug($a['title'] ?? 'event');
    ?>
    <article class="event-row<?= $reveal ? ' is-reveal' : '' ?>" itemscope itemtype="https://schema.org/MusicEvent">
        <meta itemprop="name" content="<?= kv_e($a['title']) ?>">
        <time class="event-date display" datetime="<?= kv_e($a['date']) ?>" itemprop="startDate">
            <span class="event-day"><?= date('d', $d) ?></span>
            <span class="event-mon"><?= kv_e(kv_month_ru((int)date('n', $d))) ?></span>
            <span class="event-year small"><?= date('Y', $d) ?></span>
        </time>
        <div class="event-info">
            <h3 class="event-title display" itemprop="name"><?= kv_e($a['title']) ?></h3>
            <p class="event-meta" itemprop="location" itemscope itemtype="https://schema.org/Place">
                <span itemprop="name"><?= kv_e($a['venue'] ?? '') ?></span> · <?= kv_e($a['time'] ?? '') ?> ч
            </p>
            <?php if (!empty($a['description'])): ?><p class="event-desc muted small" itemprop="description"><?= kv_e($a['description']) ?></p><?php endif; ?>
        </div>
        <div class="event-right">
            <span class="event-price"><?= kv_e($a['price'] ?? '') ?></span>
            <div class="event-actions">
                <?php if (!empty($a['ticket_url'])): ?>
                    <a class="btn btn-primary" href="<?= kv_e(kv_data_url((string)($a['ticket_url'] ?? '#'))) ?>"
                       itemprop="offers" itemscope itemtype="https://schema.org/Offer"
                       target="_blank" rel="noopener"><span itemprop="url">Купить билет</span></a>
                <?php else: ?>
                    <span class="badge-free">Вход свободный</span>
                <?php endif; ?>
                <a class="ics-chip" href="<?= kv_e(kv_url('afisha')) ?>?action=export#<?= kv_e($icsName) ?>" download="afisha.ics" title="Добавить все события в календарь (.ics)">📅 .ics</a>
            </div>
        </div>
    </article>
    <?php
};

require $CONFIG['paths']['theme'] . '/' . $CONFIG['site']['theme'] . '/header.php';
?>
<section class="page-hero">
    <div class="container">
        <p class="kicker">Билеты уже в продаже</p>
        <h1 class="display page-hero-title">Афиша</h1>
        <p class="hero-sub">Концертный сезон ансамбля «Казачья Воля» в Волгограде и на гастролях.</p>
        <div class="afisha-toolbar">
            <div class="view-switch" role="tablist" aria-label="Режим отображения афиши">
                <a class="view-btn<?= $isCalendar ? '' : ' is-active' ?>" href="<?= kv_e(kv_url('afisha')) ?>" role="tab" aria-selected="<?= $isCalendar ? 'false' : 'true' ?>">Список</a>
                <a class="view-btn<?= $isCalendar ? ' is-active' : '' ?>" href="<?= kv_e(kv_url('afisha')) ?>?view=calendar" role="tab" aria-selected="<?= $isCalendar ? 'true' : 'false' ?>">Календарь</a>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= kv_e(kv_url('afisha')) ?>?action=export" download="afisha-kazakvolya.ics">
                ⬇ Скачать афишу в календарь (.ics)
            </a>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (empty($future)): ?>
            <p class="muted">Расписание уточняется. Следите за новостями!</p>
        <?php elseif (!$isCalendar): ?>
            <h2 class="section-title display">Ближайшие концерты</h2>
            <div class="event-stack" data-stagger>
                <?php foreach ($future as $a) $renderEvent($a, true); ?>
            </div>
        <?php else: /* ---------- календарный вид: группы по месяцам ---------- */
            $byMonth = [];
            foreach ($future as $a) {
                $byMonth[substr($a['date'], 0, 7)][] = $a;
            }
            $monthNames = [1=>'Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
        ?>
            <div class="cal-groups">
                <?php foreach ($byMonth as $ym => $items): [$y, $m] = explode('-', $ym); ?>
                    <section class="cal-month" aria-label="<?= kv_e($monthNames[(int)$m] . ' ' . $y) ?>">
                        <h2 class="cal-month-title display"><span><?= kv_e($monthNames[(int)$m]) ?></span><i><?= (int)$y ?></i></h2>
                        <div class="event-stack">
                            <?php foreach ($items as $a) $renderEvent($a); ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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
