<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Container;
use App\Core\Lang;
use App\Core\Signer;

$isArabic = Lang::current() === 'ar';
$products = Container::products()->active();

$retry = null;
$retryRef = (string) ($_GET['retry'] ?? '');
if ($retryRef !== '' && Signer::check($retryRef, (string) ($_GET['sig'] ?? ''))) {
    $retry = Container::payments()->findByReference($retryRef);
}

$selectedId = (int) ($_GET['product'] ?? ($retry['product_id'] ?? $products[0]['id']));
if (!in_array($selectedId, array_map('intval', array_column($products, 'id')), true)) {
    $selectedId = (int) $products[0]['id'];
}

$quantity = (int) ($retry['quantity'] ?? 1);
$old = [
    'name' => $retry['customer_name'] ?? '',
    'email' => $retry['customer_email'] ?? '',
    'mobile' => $retry['customer_mobile'] ?? '',
];

$active = 'shop';
$pageTitle = t('checkout.page_title');
$pageScripts = ['checkout'];
require __DIR__ . '/../views/partials/header.php';
?>
<div class="container" id="checkout-root"
     data-vat-rate="<?= e((string) config('app.vat_rate')) ?>"
     data-max-quantity="<?= e((string) config('app.max_quantity')) ?>"
     data-currency="<?= e(t('currency.BHD')) ?>">
    <div class="row g-4 g-xl-5">
        <aside class="col-lg-5 order-lg-1">
            <section class="card summary-card" aria-labelledby="summary-title">
                <div class="card-body">
                    <h1 class="section-title" id="summary-title"><?= e(t('checkout.order_summary')) ?></h1>

                    <fieldset class="product-list">
                        <legend class="visually-hidden"><?= e(t('checkout.choose_product')) ?></legend>
                        <?php foreach ($products as $product) : ?>
                            <?php $name = $isArabic ? $product['name_ar'] : $product['name']; ?>
                            <label class="product-option">
                                <input class="product-radio" type="radio" name="product_id" form="checkout-form"
                                       value="<?= e($product['id']) ?>" data-price="<?= e($product['price']) ?>"
                                       data-name="<?= e($name) ?>"<?= (int) $product['id'] === $selectedId ? ' checked' : '' ?>>
                                <span class="product-icon" aria-hidden="true"><i class="bi <?= e($product['image']) ?>"></i></span>
                                <span class="product-info">
                                    <strong><?= e($name) ?></strong>
                                    <small><?= e($isArabic ? $product['description_ar'] : $product['description']) ?></small>
                                </span>
                                <span class="product-price"><?= e(money($product['price'], $product['currency'])) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <div class="quantity-row">
                        <label for="quantity" class="form-label mb-0"><?= e(t('checkout.quantity')) ?></label>
                        <div class="quantity-stepper" dir="ltr">
                            <button type="button" class="btn btn-icon" id="qty-minus" aria-label="<?= e(t('checkout.qty_decrease')) ?>">
                                <i class="bi bi-dash-lg" aria-hidden="true"></i>
                            </button>
                            <input type="number" id="quantity" name="quantity" form="checkout-form" class="form-control"
                                   min="1" max="<?= e((string) config('app.max_quantity')) ?>" value="<?= e((string) $quantity) ?>" inputmode="numeric">
                            <button type="button" class="btn btn-icon" id="qty-plus" aria-label="<?= e(t('checkout.qty_increase')) ?>">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <dl class="summary-lines" aria-live="polite">
                        <div><dt><?= e(t('checkout.subtotal')) ?></dt><dd id="sum-subtotal">-</dd></div>
                        <div><dt><?= e(t('checkout.vat', ['rate' => (int) round((float) config('app.vat_rate') * 100)])) ?></dt><dd id="sum-vat">-</dd></div>
                        <div class="total"><dt><?= e(t('checkout.total')) ?></dt><dd id="sum-total">-</dd></div>
                    </dl>
                </div>
            </section>
        </aside>

        <section class="col-lg-7 order-lg-2" aria-labelledby="checkout-title">
            <div class="card checkout-card">
                <div class="card-body">
                    <ol class="stepper" aria-label="<?= e(t('checkout.steps')) ?>">
                        <li class="is-current" data-step="1" aria-current="step"><span class="step-dot">1</span><span class="step-label"><?= e(t('checkout.step_details')) ?></span></li>
                        <li data-step="2"><span class="step-dot">2</span><span class="step-label"><?= e(t('checkout.step_payment')) ?></span></li>
                        <li data-step="3"><span class="step-dot">3</span><span class="step-label"><?= e(t('checkout.step_confirmation')) ?></span></li>
                    </ol>

                    <div id="panel-form" class="panel">
                        <h2 class="section-title" id="checkout-title"><?= e(t('checkout.your_details')) ?></h2>
                        <?php if ($retry !== null) : ?>
                            <div class="alert alert-info d-flex gap-2" role="status">
                                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                                <span><?= e(t('checkout.retry_notice')) ?></span>
                            </div>
                        <?php endif; ?>
                        <div id="form-alert" class="alert alert-danger d-none" role="alert"></div>

                        <form id="checkout-form" novalidate autocomplete="on">
                            <div class="mb-3">
                                <label for="name" class="form-label"><?= e(t('checkout.name')) ?></label>
                                <input type="text" class="form-control" id="name" name="name" autocomplete="name"
                                       maxlength="120" required value="<?= e($old['name']) ?>" aria-describedby="name-error">
                                <div class="invalid-feedback" id="name-error"></div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label"><?= e(t('checkout.email')) ?></label>
                                <input type="email" class="form-control" id="email" name="email" autocomplete="email"
                                       maxlength="190" required value="<?= e($old['email']) ?>" dir="ltr" aria-describedby="email-error">
                                <div class="invalid-feedback" id="email-error"></div>
                            </div>
                            <div class="mb-4">
                                <label for="mobile" class="form-label"><?= e(t('checkout.mobile')) ?></label>
                                <div class="input-group" dir="ltr">
                                    <span class="input-group-text"><i class="bi bi-telephone" aria-hidden="true"></i>&nbsp;+973</span>
                                    <input type="tel" class="form-control" id="mobile" name="mobile" autocomplete="tel-national"
                                           inputmode="numeric" placeholder="3XXXXXXX" required
                                           value="<?= e($old['mobile']) ?>" aria-describedby="mobile-help mobile-error">
                                    <div class="invalid-feedback" id="mobile-error"></div>
                                </div>
                                <div class="form-text" id="mobile-help"><?= e(t('checkout.mobile_help')) ?></div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100" id="pay-button">
                                <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                                <i class="bi bi-lock-fill btn-icon-lock" aria-hidden="true"></i>
                                <span class="btn-label"><?= e(t('checkout.pay_now')) ?></span>
                                <span class="btn-total" id="pay-total"></span>
                            </button>
                            <p class="form-note"><i class="bi bi-shield-check" aria-hidden="true"></i> <?= e(t('checkout.secure_note')) ?></p>
                        </form>
                    </div>

                    <div id="panel-pay" class="panel is-hidden" aria-live="polite">
                        <div class="pay-head">
                            <h2 class="section-title mb-0"><?= e(t('checkout.complete_payment')) ?></h2>
                            <span class="secure-badge"><i class="bi bi-lock-fill" aria-hidden="true"></i> <?= e(t('checkout.secured_by_gatee')) ?></span>
                        </div>
                        <p class="pay-reference">
                            <?= e(t('checkout.order_ref')) ?>: <code id="pay-reference">-</code>
                        </p>
                        <div class="frame-wrap">
                            <div class="frame-skeleton" id="frame-skeleton" aria-hidden="true">
                                <span class="sk sk-line w-50"></span>
                                <span class="sk sk-block"></span>
                                <span class="sk sk-line"></span>
                                <span class="sk sk-line w-75"></span>
                                <span class="sk sk-block short"></span>
                            </div>
                            <iframe id="pay-frame" title="<?= e(t('checkout.frame_title')) ?>" src="about:blank"
                                    referrerpolicy="origin" allow="payment"></iframe>
                        </div>
                        <div class="pay-foot">
                            <a class="link-muted d-none" id="frame-fallback" href="#" target="_blank" rel="noopener">
                                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> <?= e(t('checkout.open_separately')) ?>
                            </a>
                            <button type="button" class="btn btn-link link-danger ms-auto" id="cancel-open">
                                <i class="bi bi-x-circle" aria-hidden="true"></i> <?= e(t('checkout.cancel_payment')) ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="cancelModalTitle"><?= e(t('checkout.cancel_title')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.close')) ?>"></button>
            </div>
            <div class="modal-body"><?= e(t('checkout.cancel_body')) ?></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(t('checkout.keep_paying')) ?></button>
                <button type="button" class="btn btn-danger" id="cancel-confirm"><?= e(t('checkout.cancel_confirm')) ?></button>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../views/partials/footer.php'; ?>
