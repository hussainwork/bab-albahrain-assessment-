<?php

declare(strict_types=1);

$pageScripts ??= [];
?>
</main>
<footer class="site-footer">
    <div class="container d-flex flex-wrap justify-content-between gap-2">
        <span>&copy; <?= e(date('Y')) ?> <?= e(t('app.name')) ?></span>
        <span><i class="bi bi-lock-fill" aria-hidden="true"></i> <?= e(t('app.secured_by')) ?></span>
    </div>
</footer>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastHost" aria-live="polite" aria-atomic="false"></div>
<script type="application/json" id="app-config"><?= json_encode($clientConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/common.js"></script>
<?php foreach ($pageScripts as $script) : ?>
<script src="/assets/js/<?= e($script) ?>.js"></script>
<?php endforeach; ?>
</body>
</html>
