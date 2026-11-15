<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$active = 'logs';
$pageTitle = t('logs.title');
$pageScripts = ['logs'];
require __DIR__ . '/../views/partials/header.php';
?>
<div class="container">
    <div class="page-head">
        <div>
            <h1 class="page-title"><?= e(t('logs.title')) ?></h1>
            <p class="page-sub"><?= e(t('logs.subtitle')) ?></p>
        </div>
    </div>

    <section class="card table-card" aria-label="<?= e(t('logs.title')) ?>">
        <form class="filters" id="log-filters">
            <div>
                <label class="visually-hidden" for="l-action"><?= e(t('logs.action')) ?></label>
                <select class="form-select" id="l-action"><option value=""><?= e(t('logs.all_actions')) ?></option></select>
            </div>
            <div>
                <label class="visually-hidden" for="l-direction"><?= e(t('logs.direction')) ?></label>
                <select class="form-select" id="l-direction">
                    <option value=""><?= e(t('logs.all_directions')) ?></option>
                    <option value="outbound"><?= e(t('logs.outbound')) ?></option>
                    <option value="inbound"><?= e(t('logs.inbound')) ?></option>
                </select>
            </div>
            <div>
                <label class="visually-hidden" for="l-result"><?= e(t('logs.result')) ?></label>
                <select class="form-select" id="l-result">
                    <option value=""><?= e(t('logs.all_results')) ?></option>
                    <option value="ok"><?= e(t('logs.ok')) ?></option>
                    <option value="error"><?= e(t('logs.error')) ?></option>
                </select>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table data-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col"><?= e(t('logs.col_time')) ?></th>
                        <th scope="col"><?= e(t('logs.col_direction')) ?></th>
                        <th scope="col"><?= e(t('logs.col_action')) ?></th>
                        <th scope="col"><?= e(t('logs.col_http')) ?></th>
                        <th scope="col" class="text-end"><?= e(t('logs.col_duration')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('logs.col_details')) ?></span></th>
                    </tr>
                </thead>
                <tbody id="logs-body" aria-live="polite"></tbody>
            </table>
        </div>

        <div class="table-state d-none" id="logs-empty">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            <strong><?= e(t('logs.empty')) ?></strong>
        </div>

        <nav class="table-foot" aria-label="<?= e(t('payments.pagination')) ?>">
            <span class="muted" id="log-info"></span>
            <ul class="pagination mb-0" id="log-pagination"></ul>
        </nav>
    </section>
</div>
<?php require __DIR__ . '/../views/partials/footer.php'; ?>
