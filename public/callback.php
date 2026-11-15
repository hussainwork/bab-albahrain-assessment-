<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Container;
use App\Core\Logger;
use App\Gateway\GateEException;
use App\Gateway\PaymentStatus;

$gatewayId = (string) ($_GET['payment_id'] ?? '');
$code = (string) ($_GET['payment_code'] ?? '');

$payment = null;
if ($gatewayId !== '' && $code !== '') {
    $payment = Container::payments()->findByGatewayReference($gatewayId, $code);
}

$verified = true;
if ($payment !== null) {
    try {
        // Query params are only a pointer; the status comes from payment/get.
        $payment = Container::sync()->syncFromGateway((int) $payment['id'])['payment'];
    } catch (GateEException | RuntimeException $e) {
        $verified = false;
        Logger::warning('Callback could not verify the payment', ['payment_id' => $payment['id'], 'error' => $e->getMessage()]);
    }
}

$transaction = $payment !== null ? Container::transactions()->latestForPayment((int) $payment['id']) : null;

$state = match ($payment['status'] ?? null) {
    PaymentStatus::PAID => 'success',
    PaymentStatus::FAILED => 'failed',
    PaymentStatus::CANCELLED => 'cancelled',
    null => 'unknown',
    default => 'pending',
};

$dash = '-';
$when = $payment['paid_at'] ?? ($transaction['created_at'] ?? ($payment['updated_at'] ?? null));
$rows = $payment === null ? [] : [
    ['callback.amount', money($payment['amount'], $payment['currency']), false],
    ['callback.order_ref', $payment['order_reference'], true],
    ['callback.gateway_id', $payment['gateway_payment_id'] ?? $dash, true],
    ['callback.transaction_id', $transaction['gateway_transaction_id'] ?? $dash, true],
    ['callback.card', $transaction['card_masked'] ?? $dash, false],
    ['callback.auth_code', $transaction['auth_code'] ?? $dash, false],
    ['callback.date', format_date($when) . ' (' . t('callback.tz') . ')', false],
];

$active = 'shop';
$pageTitle = t('callback.title_' . $state);
$pageScripts = ['callback'];
require __DIR__ . '/../views/partials/header.php';
?>
<div class="container">
    <ol class="stepper stepper-narrow mx-auto" aria-label="<?= e(t('checkout.steps')) ?>">
        <li class="is-done" data-step="1"><span class="step-dot"><i class="bi bi-check-lg" aria-hidden="true"></i></span><span class="step-label"><?= e(t('checkout.step_details')) ?></span></li>
        <li class="is-done" data-step="2"><span class="step-dot"><i class="bi bi-check-lg" aria-hidden="true"></i></span><span class="step-label"><?= e(t('checkout.step_payment')) ?></span></li>
        <li class="<?= $state === 'success' ? 'is-done' : 'is-current' ?>" data-step="3"<?= $state === 'success' ? '' : ' aria-current="step"' ?>><span class="step-dot"><?= $state === 'success' ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : '3' ?></span><span class="step-label"><?= e(t('checkout.step_confirmation')) ?></span></li>
    </ol>

    <article class="card receipt receipt-<?= e($state) ?> mx-auto" aria-labelledby="receipt-title">
        <div class="receipt-top">
            <?php if ($state === 'success') : ?>
                <svg class="result-icon" viewBox="0 0 52 52" aria-hidden="true"><circle class="ri-circle" cx="26" cy="26" r="24" fill="none"/><path class="ri-mark" fill="none" d="M14 27l8 8 16-17"/></svg>
            <?php elseif ($state === 'failed') : ?>
                <svg class="result-icon" viewBox="0 0 52 52" aria-hidden="true"><circle class="ri-circle" cx="26" cy="26" r="24" fill="none"/><path class="ri-mark" fill="none" d="M18 18l16 16M34 18L18 34"/></svg>
            <?php else : ?>
                <svg class="result-icon" viewBox="0 0 52 52" aria-hidden="true"><circle class="ri-circle" cx="26" cy="26" r="24" fill="none"/><path class="ri-mark" fill="none" d="M26 14v13l8 5"/></svg>
            <?php endif; ?>
            <h1 class="receipt-title" id="receipt-title"><?= e(t('callback.title_' . $state)) ?></h1>
            <p class="receipt-lead"><?= e(t('callback.lead_' . $state)) ?></p>
            <?php if ($payment !== null) : ?>
                <span class="status-pill status-<?= e($payment['status']) ?>"><?= e(t('status.' . $payment['status'])) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!$verified) : ?>
            <div class="alert alert-warning mx-4 d-flex gap-2" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <span><?= e(t('callback.unverified')) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($rows !== []) : ?>
            <dl class="receipt-rows">
                <?php foreach ($rows as [$label, $value, $copyable]) : ?>
                    <div>
                        <dt><?= e(t($label)) ?></dt>
                        <dd>
                            <span class="<?= $label === 'callback.amount' ? 'receipt-amount' : '' ?>" dir="ltr"><?= e($value) ?></span>
                            <?php if ($copyable && $value !== $dash) : ?>
                                <button type="button" class="btn-copy" data-copy="<?= e($value) ?>" aria-label="<?= e(t('common.copy')) ?>"><i class="bi bi-clipboard" aria-hidden="true"></i></button>
                            <?php endif; ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>

        <?php if ($state === 'failed' && !empty($payment['status_message'])) : ?>
            <p class="receipt-note"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e($payment['status_message']) ?></p>
        <?php endif; ?>

        <div class="receipt-actions">
            <?php if ($state === 'success' || $state === 'pending') : ?>
                <button type="button" class="btn btn-outline-primary" id="print-receipt"><i class="bi bi-printer" aria-hidden="true"></i> <?= e(t('callback.print')) ?></button>
            <?php endif; ?>
            <?php if ($state === 'pending') : ?>
                <button type="button" class="btn btn-outline-primary" id="reload-status"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> <?= e(t('payments.refresh')) ?></button>
            <?php endif; ?>
            <?php if ($payment !== null && in_array($state, ['failed', 'cancelled'], true)) : ?>
                <a class="btn btn-primary" href="<?= e(Container::checkout()->retryLink($payment['order_reference'])) ?>"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> <?= e(t('callback.try_again')) ?></a>
            <?php endif; ?>
            <a class="btn btn-light" href="/"><i class="bi bi-arrow-left-short flip-rtl" aria-hidden="true"></i> <?= e(t('callback.back_to_shop')) ?></a>
        </div>
    </article>
</div>
<?php require __DIR__ . '/../views/partials/footer.php'; ?>
