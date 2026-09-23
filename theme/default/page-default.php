<?php
/**
 * ============================================================
 *  theme/default/page-default.php — ЕДИНЫЙ шаблон всех страниц.
 *  Страница целиком собирается из блоков pages.json, поэтому
 *  админка может редактировать ЛЮБУЮ страницу без правки кода.
 *  Доступные переменные: $current, $settings, $menu, $ctx
 * ============================================================
 */
if (!defined('KV_SITE')) { exit('Access denied'); }

require __DIR__ . '/header.php';

foreach ($current['blocks'] ?? [] as $b) {
    kv_render_block($b, $ctx);
}

// Если у страницы нет ни одного блока — аккуратная заглушка
if (empty($current['blocks'])): ?>
    <section class="section">
        <div class="container prose">
            <h1 class="display"><?= kv_e($current['title'] ?? '') ?></h1>
            <p class="muted">Содержимое этой страницы пока не заполнено — загляните в админ-панель.</p>
        </div>
    </section>
<?php endif;

require __DIR__ . '/footer.php';
