<?php

declare(strict_types=1);

$active = '';
$pageTitle = t('error.server_title');
require __DIR__ . '/partials/header.php';
?>
<div class="container">
    <div class="card status-card mx-auto text-center">
        <div class="card-body p-5">
            <i class="bi bi-exclamation-triangle display-4 text-warning" aria-hidden="true"></i>
            <h1 class="h3 mt-3"><?= e(t('error.server_title')) ?></h1>
            <p class="text-muted"><?= e(t('error.server')) ?></p>
            <a class="btn btn-primary" href="/"><?= e(t('callback.back_to_shop')) ?></a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
