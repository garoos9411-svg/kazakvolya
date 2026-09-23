<?php if (!defined('KV_SITE')) { exit('Access denied'); } ?>
</main>

<footer class="site-footer">
    <div class="container footer-top">
        <div class="footer-brand">
            <p class="footer-logo display">Казачья Воля</p>
            <p class="muted"><?= kv_e($settings['tagline'] ?? '') ?></p>
            <?php if (!empty($settings['director'])): ?>
                <p class="muted small">Директор: <?= kv_e($settings['director']) ?></p>
            <?php endif; ?>
        </div>

        <nav class="footer-col" aria-label="Меню в подвале">
            <h3 class="footer-title">Разделы</h3>
            <ul>
                <?php foreach ($menu as $m): ?>
                    <li><a href="index.php?page=<?= kv_e($m['slug']) ?>"><?= kv_e($m['menu_title'] ?? $m['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="footer-col">
            <h3 class="footer-title">Контакты</h3>
            <address>
                <?= kv_e($settings['address'] ?? '') ?><br>
                <a href="tel:<?= kv_e($settings['phone_raw'] ?? '') ?>"><?= kv_e($settings['phone'] ?? '') ?></a><br>
                <a href="mailto:<?= kv_e($settings['email'] ?? '') ?>"><?= kv_e($settings['email'] ?? '') ?></a>
            </address>
            <p class="muted small"><?= kv_e($settings['hours'] ?? '') ?></p>
        </div>

        <div class="footer-col">
            <h3 class="footer-title">Мы в сети</h3>
            <?php if (!empty($settings['vk_url'])): ?>
                <a class="social-link" href="<?= kv_e($settings['vk_url']) ?>"
                   target="_blank" rel="noopener">ВКонтакте →</a>
            <?php endif; ?>
            <a class="btn btn-primary footer-btn"
               href="<?= kv_e($settings['ticket_url'] ?? '#') ?>"
               target="_blank" rel="noopener">Купить билет</a>
        </div>
    </div>

    <div class="container footer-bottom">
        <small><?= kv_e($settings['copyright'] ?? '© 2026') ?></small>
        <a class="admin-link" href="admin.php">Управление сайтом</a>
    </div>
</footer>

<!-- Кнопка «наверх» (появляется после прокрутки) -->
<button class="to-top glass" id="toTop" aria-label="Наверх">↑</button>

<!-- Плавающая кнопка «Позвонить» — удобно на телефоне -->
<a class="fab-call magnetic" href="tel:<?= kv_e($settings['phone_raw'] ?? '') ?>" aria-label="Позвонить">
    <span class="fab-pulse" aria-hidden="true"></span>
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M6.6 10.8a15.5 15.5 0 006.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.2.4 2.4.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 013 4c0-.6.4-1 1-1h3.4c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1l-2.2 2.2z" fill="#fff"/>
    </svg>
</a>

<script src="theme/js/script.min.js?v=3" defer></script>
</body>
</html>
