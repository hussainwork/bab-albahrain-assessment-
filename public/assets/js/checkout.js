(function ($, App) {
    'use strict';

    const $root = $('#checkout-root');
    const vatRate = parseFloat($root.data('vat-rate'));
    const maxQuantity = parseInt($root.data('max-quantity'), 10);

    const $form = $('#checkout-form');
    const $button = $('#pay-button');
    const $alert = $('#form-alert');
    const $frame = $('#pay-frame');
    const $skeleton = $('#frame-skeleton');
    const state = { reference: null, frameLoads: 0 };

    const fields = ['name', 'email', 'mobile'];

    function formatFils(fils) {
        return App.money(fils / 1000);
    }

    function quantity() {
        const value = parseInt($('#quantity').val(), 10);
        return Number.isNaN(value) ? 1 : Math.min(maxQuantity, Math.max(1, value));
    }

    function renderSummary() {
        const price = parseFloat($('.product-radio:checked').data('price'));
        const subtotal = Math.round(price * 1000) * quantity();
        const vat = Math.round(subtotal * vatRate);

        $('#sum-subtotal').text(formatFils(subtotal));
        $('#sum-vat').text(formatFils(vat));
        $('#sum-total').text(formatFils(subtotal + vat));
        $('#pay-total').text(formatFils(subtotal + vat));
        $('.product-option').each(function () {
            $(this).toggleClass('is-selected', $(this).find('input').is(':checked'));
        });
    }

    function normaliseMobile(value) {
        const digits = value.replace(/\D+/g, '');

        // Local numbers start with 1, 3 or 6, so a leading 973 can only be the country code.
        return (digits.indexOf('973') === 0 ? digits.slice(3) : digits).slice(0, 8);
    }

    function validate() {
        const errors = {};
        const name = $.trim($('#name').val());
        const email = $.trim($('#email').val());
        const mobile = normaliseMobile($('#mobile').val());

        if (name.length < 2) { errors.name = App.t(name === '' ? 'validation.required' : 'validation.too_short', { min: 2 }); }
        if (email === '') {
            errors.email = App.t('validation.required');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.email = App.t('validation.email');
        }
        if (mobile === '') {
            errors.mobile = App.t('validation.required');
        } else if (!/^[136]\d{7}$/.test(mobile)) {
            errors.mobile = App.t('validation.mobile');
        }

        return errors;
    }

    function showErrors(errors) {
        fields.forEach(function (field) {
            const message = errors[field];
            $('#' + field).toggleClass('is-invalid', !!message).attr('aria-invalid', message ? 'true' : null);
            $('#' + field + '-error').text(message || '');
        });

        const other = Object.keys(errors).filter(function (key) { return fields.indexOf(key) === -1; });
        if (other.length) {
            $alert.text(other.map(function (key) { return errors[key]; }).join(' ')).removeClass('d-none');
        }

        const first = fields.filter(function (field) { return errors[field]; })[0];
        if (first) {
            $('#' + first).trigger('focus');
        }
    }

    function setBusy(busy) {
        $button.prop('disabled', busy).attr('aria-busy', busy ? 'true' : 'false');
        $button.find('.spinner-border').toggleClass('d-none', !busy);
        $button.find('.btn-icon-lock, .btn-total').toggleClass('d-none', busy);
        $button.find('.btn-label').text(App.t(busy ? 'checkout.creating' : 'checkout.pay_now'));
    }

    function setStep(step) {
        $('.stepper li').each(function () {
            const n = parseInt($(this).data('step'), 10);
            $(this).toggleClass('is-done', n < step).toggleClass('is-current', n === step);
            $(this).attr('aria-current', n === step ? 'step' : null);
            $(this).find('.step-dot').empty().append(n < step ? $('<i class="bi bi-check-lg" aria-hidden="true">') : document.createTextNode(String(n)));
        });
    }

    function showPanel(name) {
        const $show = $(name === 'pay' ? '#panel-pay' : '#panel-form');
        $('.panel').not($show).addClass('is-hidden');
        $show.removeClass('is-hidden').addClass('panel-enter');
        setTimeout(function () { $show.removeClass('panel-enter'); }, 400);
        $('.checkout-card')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function openGateway(payment) {
        state.reference = payment.order_reference;
        state.frameLoads = 0;

        $('#pay-reference').text(payment.order_reference);
        $('#frame-fallback').attr('href', payment.payment_url).removeClass('d-none');
        $skeleton.removeClass('is-done');

        $frame.off('load').on('load', function () {
            state.frameLoads += 1;
            $skeleton.addClass('is-done');
            // The second load is the gateway redirecting back to our callback page.
            if (state.frameLoads > 1) {
                setStep(3);
            }
        });
        $frame.attr('src', payment.payment_url);

        setStep(2);
        showPanel('pay');
    }

    function closeGateway() {
        $frame.off('load').attr('src', 'about:blank');
        state.reference = null;
        setStep(1);
        showPanel('form');
    }

    $('.product-radio').on('change', renderSummary);
    $('#quantity').on('input change', renderSummary);
    $('#qty-minus').on('click', function () { $('#quantity').val(Math.max(1, quantity() - 1)); renderSummary(); });
    $('#qty-plus').on('click', function () { $('#quantity').val(Math.min(maxQuantity, quantity() + 1)); renderSummary(); });

    $('#mobile').on('input', function () {
        const cleaned = normaliseMobile($(this).val());
        if ($(this).val() !== cleaned) { $(this).val(cleaned); }
    });

    $form.on('input change', '.form-control', function () {
        $(this).removeClass('is-invalid');
        $alert.addClass('d-none');
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        $alert.addClass('d-none');

        const errors = validate();
        showErrors(errors);
        if (Object.keys(errors).length) {
            return;
        }

        setBusy(true);
        App.post('/api/create-payment.php', {
            product_id: $('.product-radio:checked').val(),
            quantity: quantity(),
            name: $.trim($('#name').val()),
            email: $.trim($('#email').val()),
            mobile: normaliseMobile($('#mobile').val())
        }).done(function (response) {
            openGateway(response.data);
            App.toast('success', response.message);
        }).fail(function (xhr) {
            const error = App.handleError(xhr, { silent: true });
            if (error.status === 422) {
                showErrors(error.errors);
                return;
            }
            $alert.text(error.message).removeClass('d-none');
            App.toast('danger', error.message);
        }).always(function () {
            setBusy(false);
        });
    });

    const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
    $('#cancel-open').on('click', function () { cancelModal.show(); });
    $('#cancel-confirm').on('click', function () {
        const $confirm = $(this).prop('disabled', true);

        App.post('/api/cancel-payment.php', { order_reference: state.reference })
            .done(function (response) {
                cancelModal.hide();
                closeGateway();
                App.toast('info', response.message);
            })
            .fail(function (xhr) {
                cancelModal.hide();
                App.handleError(xhr);
            })
            .always(function () { $confirm.prop('disabled', false); });
    });

    renderSummary();
}(jQuery, window.App));
