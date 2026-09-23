<?php
/**
 *  includes/export-ics.php — экспорт афиши в календарь (.ics): один VEVENT на событие
 *  Вызывается из afisha.php при ?action=export
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

$dt  = new DateTimeZone('Europe/Moscow');
$fmt = fn(string $iso, string $time) => (new DateTimeImmutable($iso . ' ' . ($time ?: '19:00'), $dt))->format('Ymd\THis');

$lines = [
    'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//KazakVolya//Afisha//RU',
    'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:Афиша — Казачья Воля',
];
foreach ($afisha as $a) {
    if (empty($a['date'])) continue;
    $lines = array_merge($lines, [
        'BEGIN:VEVENT',
        'UID:' . md5(($a['date'] ?? '') . ($a['title'] ?? '')) . '@kazakvolya.ru',
        'DTSTART:' . $fmt($a['date'], $a['time'] ?? ''),
        'SUMMARY:' . str_replace(["\r", "\n"], ' ', preg_replace('/[^\P{C}\n]/u', '', $a['title'] ?? '')),
        'LOCATION:' . str_replace("\n", ' ', $a['venue'] ?? ''),
        'DESCRIPTION:' . str_replace("\n", '\\n', $a['description'] ?? ($a['price'] ?? '')),
        'END:VEVENT',
    ]);
}
$lines[] = 'END:VCALENDAR';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="afisha-kazakvolya.ics"');
echo implode("\r\n", $lines) . "\r\n";
