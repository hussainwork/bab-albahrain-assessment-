<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Gateway\PaymentStatus;

$active = 'payments';
$pageTitle = t('payments.title');
$pageScripts = ['payments'];
require __DIR__ . '/../views/partials/header.php';

$stats = [
    ['total', 'bi-collection', 'payments.stat_total'],
    ['paid', 'bi-check-circle', 'payments.stat_paid'],
    ['failed', 'bi-x-circle', 'payments.stat_failed'],
    ['success_rate', 'bi-graph-up-arrow', 'payments.stat_rate'],
    ['revenue', 'bi-cash-stack', 'payments.stat_revenue'],
];
?>
<div class="container">
    <div class="page-head">
        <div>
            <h1 class="page-title"><?= e(t('payments.title')) ?></h1>
            <p class="page-sub"><?= e(t('payments.subtitle')) ?></p>
        </div>
        <a class="btn btn-primary" href="/"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(t('payments.new_order')) ?></a>
    </div>

    <section class="stat-grid" aria-label="<?= e(t('payments.stats')) ?>" id="stat-grid">
        <?php foreach ($stats as [$key, $icon, $label]) : ?>
            <div class="stat-card stat-<?= e($key) ?>">
                <span class="stat-icon" aria-hidden="true"><i class="bi <?= e($icon) ?>"></i></span>
                <div>
                    <div class="stat-label"><?= e(t($label)) ?></div>
                    <div class="stat-value is-loading" data-stat="<?= e($key) ?>">&nbsp;</div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="card table-card" aria-label="<?= e(t('payments.list')) ?>">
        <form class="filters" id="filters" role="search">
            <div class="filter-search">
                <label class="visually-hidden" for="f-q"><?= e(t('payments.search')) ?></label>
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" class="form-control" id="f-q" placeholder="<?= e(t('payments.search_placeholder')) ?>" autocomplete="off">
            </div>
            <div>
                <label class="visually-hidden" for="f-status"><?= e(t('payments.status')) ?></label>
                <select class="form-select" id="f-status">
                    <option value=""><?= e(t('payments.all_statuses')) ?></option>
                    <?php foreach (PaymentStatus::ALL as $status) : ?>
                        <option value="<?= e($status) ?>"><?= e(t('status.' . $status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-date">
                <label class="visually-hidden" for="f-from"><?= e(t('payments.from')) ?></label>
                <input type="date" class="form-control" id="f-from" dir="ltr" title="<?= e(t('payments.from')) ?>">
                <span aria-hidden="true">&ndash;</span>
                <label class="visually-hidden" for="f-to"><?= e(t('payments.to')) ?></label>
                <input type="date" class="form-control" id="f-to" dir="ltr" title="<?= e(t('payments.to')) ?>">
            </div>
            <button type="button" class="btn btn-light" id="f-reset"><i class="bi bi-x-lg" aria-hidden="true"></i> <?= e(t('payments.reset')) ?></button>
        </form>

        <div class="table-responsive">
            <table class="table data-table align-middle mb-0" id="payments-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(t('payments.col_reference')) ?></th>
                        <th scope="col"><?= e(t('payments.col_customer')) ?></th>
                        <th scope="col"><?= e(t('payments.col_product')) ?></th>
                        <th scope="col" class="text-end"><?= e(t('payments.col_amount')) ?></th>
                        <th scope="col"><?= e(t('payments.col_status')) ?></th>
                        <th scope="col"><?= e(t('payments.col_date')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(t('payments.col_actions')) ?></span></th>
                    </tr>
                </thead>
                <tbody id="payments-body" aria-live="polite"></tbody>
            </table>
        </div>

        <div class="table-state d-none" id="state-empty">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            <strong><?= e(t('payments.empty_title')) ?></strong>
            <span><?= e(t('payments.empty_body')) ?></span>
        </div>
        <div class="table-state d-none" id="state-error" role="alert">
            <i class="bi bi-wifi-off" aria-hidden="true"></i>
            <strong><?= e(t('payments.load_error')) ?></strong>
            <button type="button" class="btn btn-sm btn-outline-primary" id="retry-load"><?= e(t('payments.retry')) ?></button>
        </div>

        <nav class="table-foot" aria-label="<?= e(t('payments.pagination')) ?>">
            <span class="muted" id="page-info"></span>
            <ul class="pagination mb-0" id="pagination"></ul>
        </nav>
    </section>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5" id="detailsTitle"><?= e(t('modal.title')) ?></h2>
                    <div class="modal-sub"><code id="md-reference"></code> <span id="md-status"></span></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div id="md-loading" class="modal-loading" aria-hidden="true">
                    <span class="sk sk-line w-50"></span><span class="sk sk-block"></span><span class="sk sk-line"></span><span class="sk sk-line w-75"></span>
                </div>
                <div id="md-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="md-content" class="d-none">
                    <div class="detail-grid" id="md-summary"></div>

                    <h3 class="subhead"><?= e(t('modal.comparison')) ?></h3>
                    <div id="md-live-error" class="alert alert-warning d-none" role="alert"></div>
                    <div class="table-responsive">
                        <table class="table compare-table align-middle" id="md-compare">
                            <thead>
                                <tr>
                                    <th scope="col"><?= e(t('modal.field')) ?></th>
                                    <th scope="col"><i class="bi bi-database" aria-hidden="true"></i> <?= e(t('modal.database')) ?></th>
                                    <th scope="col"><i class="bi bi-cloud-check" aria-hidden="true"></i> <?= e(t('modal.live')) ?></th>
                                    <th scope="col" class="text-center"><?= e(t('modal.match')) ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <h3 class="subhead"><?= e(t('modal.timeline')) ?></h3>
                    <ol class="timeline" id="md-timeline"></ol>

                    <h3 class="subhead"><?= e(t('modal.raw')) ?></h3>
                    <div class="accordion" id="md-raw">
                        <div class="accordion-item">
                            <h4 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#raw-live" aria-expanded="false" aria-controls="raw-live"><?= e(t('modal.raw_live')) ?></button></h4>
                            <div id="raw-live" class="accordion-collapse collapse" data-bs-parent="#md-raw"><div class="accordion-body"><pre class="json-view" id="raw-live-pre" dir="ltr"></pre></div></div>
                        </div>
                        <div class="accordion-item">
                            <h4 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#raw-db" aria-expanded="false" aria-controls="raw-db"><?= e(t('modal.raw_db')) ?></button></h4>
                            <div id="raw-db" class="accordion-collapse collapse" data-bs-parent="#md-raw"><div class="accordion-body"><pre class="json-view" id="raw-db-pre" dir="ltr"></pre></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" id="md-refresh" disabled>
                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                    <i class="bi bi-arrow-clockwise btn-icon-lock" aria-hidden="true"></i>
                    <?= e(t('payments.refresh')) ?>
                </button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(t('common.close')) ?></button>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../views/partials/footer.php'; ?>
