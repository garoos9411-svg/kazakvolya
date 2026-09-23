<?php
/**
 * theme/default/page-default.php — универсальный шаблон страницы.
 * Переменная $current содержит массив страницы из pages.json.
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

require __DIR__ . '/header.php';

foreach ($current['blocks'] ?? [] as $b):
    switch ($b['type'] ?? ''):

        /* ---------- Шапка внутренней страницы ---------- */
        case 'page_header': ?>
            <section class="page-hero">
                <div class="container">
                    <h1 class="display"><?= kv_e($b['title'] ?? $current['title']) ?></h1>
                    <?php if (!empty($b['image'])): ?>
                        <img class="page-hero-img" src="<?= kv_e($b['image']) ?>"
                             alt="" loading="lazy">
                    <?php endif; ?>
                </div>
            </section>
        <?php break;

        /* ---------- Просто текст (абзацы через пустую строку) ---------- */
        case 'text': ?>
            <section class="section">
                <div class="container prose">
                    <?= kv_text_to_html($b['html'] ?? '') ?>
                </div>
            </section>
        <?php break;

        /* ---------- Список (репертуар) ---------- */
        case 'list': ?>
            <section class="section">
                <div class="container">
                    <?php if (!empty($b['title'])): ?>
                        <h2 class="section-title"><?= kv_e($b['title']) ?></h2>
                    <?php endif; ?>
                    <ul class="rep-list">
                        <?php foreach ($b['items'] ?? [] as $i => $item): ?>
                            <li class="rep-item">
                                <span class="rep-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                <div>
                                    <h3 class="rep-name"><?= kv_e($item['name'] ?? '') ?></h3>
                                    <p class="rep-note"><?= kv_e($item['note'] ?? '') ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>
        <?php break;

        /* ---------- Числы («35 лет на сцене» и т.п.) ---------- */
        case 'numbers': ?>
            <section class="section section-tinted">
                <div class="container numbers-grid">
                    <?php foreach ($b['items'] ?? [] as $item): ?>
                        <div class="number-card">
                            <span class="number-value"><?= kv_e($item['value'] ?? '') ?></span>
                            <span class="number-label"><?= kv_e($item['label'] ?? '') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php break;

        /* ---------- Контакты (берутся из settings.json) ---------- */
        case 'contacts': ?>
            <section class="section">
                <div class="container contacts-grid">
                    <div class="prose">
                        <?= kv_text_to_html($b['text'] ?? '') ?>
                        <dl class="contacts-list">
                            <dt>Адрес</dt><dd><?= kv_e($settings['address'] ?? '') ?></dd>
                            <dt>Телефон</dt><dd><a href="tel:<?= kv_e($settings['phone_raw'] ?? '') ?>"><?= kv_e($settings['phone'] ?? '') ?></a></dd>
                            <dt>E-mail</dt><dd><a href="mailto:<?= kv_e($settings['email'] ?? '') ?>"><?= kv_e($settings['email'] ?? '') ?></a></dd>
                            <dt>Часы работы</dt><dd><?= kv_e($settings['hours'] ?? '') ?></dd>
                        </dl>
                        <a class="btn btn-primary" href="tel:<?= kv_e($settings['phone_raw'] ?? '') ?>">Позвонить</a>
                    </div>
                    <div class="map-placeholder" role="img" aria-label="Схема проезда">
                        <span>🗺</span>
                        <p><?= kv_e($settings['address'] ?? '') ?></p>
                        <small>Здесь может быть карта — вставьте iframe Яндекс.Карт в blocks страницы «Контакты»</small>
                    </div>
                </div>
            </section>
        <?php break;

        /* ---------- Типы, обрабатываемые на главной (hero и др.) ---------- */
        default: ?>
            <!-- Блок «<?= kv_e($b['type'] ?? '?') ?>» на этой странице не отображается -->
        <?php
    endswitch;
endforeach;

require __DIR__ . '/footer.php';
