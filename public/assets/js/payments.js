(function ($, App) {
    'use strict';

    const query = { page: 1, q: '', status: '', from: '', to: '' };
    const isArabic = App.config.lang === 'ar';
    const $body = $('#payments-body');
    const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    let currentId = null;
    let loadToken = 0;

    function skeletonRows() {
        $body.empty();
        for (let i = 0; i < 5; i += 1) {
            const $tr = $('<tr class="skeleton-row" aria-hidden="true">');
            for (let c = 0; c < 7; c += 1) {
                $tr.append($('<td>').append($('<span class="sk sk-line">')));
            }
            $body.append($tr);
        }
    }

    function buildRow(p) {
        const $tr = $('<tr>');
        $tr.append($('<td>').append($('<span class="ref" dir="ltr">').text(p.order_reference), App.copyButton(p.order_reference)));
        $tr.append($('<td>').append($('<div class="cell-main">').text(p.customer_name), $('<div class="cell-sub" dir="ltr">').text(p.customer_email)));
        $tr.append($('<td>').text(isArabic ? p.product_name_ar : p.product_name));
        $tr.append($('<td class="text-end amount-cell" dir="ltr">').text(App.money(p.amount, p.currency)));
        $tr.append($('<td>').append(App.statusPill(p.status)));
        $tr.append($('<td class="date-cell" dir="ltr">').text(App.formatDate(p.created_at)));
        $tr.append($('<td class="text-end">').append(
            $('<button type="button" class="btn btn-sm btn-outline-primary btn-view">')
                .attr('data-id', p.id)
                .append($('<i class="bi bi-eye" aria-hidden="true">'), document.createTextNode(' ' + App.t('payments.view')))
        ));

        return $tr;
    }

    function loadList() {
        const token = ++loadToken;
        skeletonRows();
        $('#state-empty, #state-error').addClass('d-none');
        $('#payments-table').removeClass('d-none');

        App.get('/api/payments-list.php', query).done(function (response) {
            if (token !== loadToken) { return; }
            const data = response.data;
            $body.empty();

            if (!data.rows.length) {
                $('#payments-table').addClass('d-none');
                $('#state-empty').removeClass('d-none');
            } else {
                data.rows.forEach(function (row) { $body.append(buildRow(row)); });
            }

            const from = data.total === 0 ? 0 : (data.page - 1) * data.per_page + 1;
            const to = Math.min(data.total, data.page * data.per_page);
            $('#page-info').text(App.t('payments.showing', { from: from, to: to, total: data.total }));
            App.renderPagination($('#pagination'), data.page, data.pages, function (page) {
                query.page = page;
                loadList();
            });
        }).fail(function (xhr) {
            if (token !== loadToken) { return; }
            App.handleError(xhr, { silent: true });
            $body.empty();
            $('#payments-table').addClass('d-none');
            $('#state-error').removeClass('d-none');
        });
    }

    function loadStats() {
        App.get('/api/stats.php').done(function (response) {
            const s = response.data;
            const values = {
                total: s.total,
                paid: s.paid,
                failed: s.failed,
                success_rate: s.success_rate + '%',
                revenue: App.money(s.revenue)
            };
            $('[data-stat]').each(function () {
                $(this).removeClass('is-loading').text(values[$(this).data('stat')]);
            });
        }).fail(function (xhr) {
            App.handleError(xhr, { silent: true });
            $('[data-stat]').removeClass('is-loading').text('-');
        });
    }

    function summaryItem(label, value, mono) {
        return $('<div class="detail-item">').append(
            $('<div class="detail-label">').text(label),
            $('<div class="detail-value">').toggleClass('mono', !!mono).attr('dir', mono ? 'ltr' : null).text(value || '-')
        );
    }

    function matchBadge(row) {
        if (row.live === null) {
            return $('<span class="match-badge none">').text('-');
        }
        const ok = row.match;

        return $('<span class="match-badge">').addClass(ok ? 'ok' : 'bad').append(
            $('<i class="bi">').addClass(ok ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill').attr('aria-hidden', 'true'),
            $('<span class="visually-hidden">').text(App.t(ok ? 'modal.matches' : 'modal.mismatch'))
        );
    }

    function renderDetails(d) {
        const p = d.payment;

        $('#md-reference').text(p.order_reference);
        $('#md-status').empty().append(App.statusPill(p.status));

        $('#md-summary').empty().append(
            summaryItem(App.t('modal.product'), (isArabic ? p.product_name_ar : p.product_name) + ' x ' + p.quantity),
            summaryItem(App.t('callback.amount'), App.money(p.amount, p.currency), true),
            summaryItem(App.t('checkout.subtotal'), App.money(p.subtotal, p.currency), true),
            summaryItem(App.t('modal.vat'), App.money(p.vat_amount, p.currency), true),
            summaryItem(App.t('modal.customer'), p.customer_name),
            summaryItem(App.t('checkout.email'), p.customer_email, true),
            summaryItem(App.t('checkout.mobile'), p.customer_mobile, true),
            summaryItem(App.t('callback.gateway_id'), p.gateway_payment_id, true),
            summaryItem(App.t('modal.created'), App.formatDate(p.created_at), true),
            summaryItem(App.t('modal.paid_at'), App.formatDate(p.paid_at), true)
        );

        const $compare = $('#md-compare tbody').empty();
        d.comparison.forEach(function (row) {
            const translate = function (value) {
                return row.field === 'status' && value ? App.t('status.' + value) : value;
            };
            $compare.append($('<tr>').toggleClass('row-mismatch', row.live !== null && !row.match).append(
                $('<th scope="row">').text(App.t('modal.field.' + row.field)),
                $('<td dir="ltr" class="mono">').text(translate(row.db) || '-'),
                $('<td dir="ltr" class="mono">').text(translate(row.live) || '-'),
                $('<td class="text-center">').append(matchBadge(row))
            ));
        });

        const $liveError = $('#md-live-error');
        if (d.live_error || !d.live) {
            $liveError.text(d.live_error ? App.t('modal.live_unavailable') + ' (' + d.live_error + ')' : App.t('modal.no_gateway_ref')).removeClass('d-none');
        } else {
            $liveError.addClass('d-none');
        }

        const $timeline = $('#md-timeline').empty();
        d.timeline.forEach(function (event) {
            $timeline.append($('<li class="timeline-item">').addClass('tl-' + event.type).append(
                $('<span class="tl-dot" aria-hidden="true">'),
                $('<div class="tl-body">').append(
                    $('<div class="tl-title">').text(App.t('timeline.' + event.type)),
                    event.detail ? $('<div class="tl-detail">').text(event.detail) : null,
                    $('<time class="tl-time" dir="ltr">').text(App.formatDate(event.at))
                )
            ));
        });

        $('#raw-live-pre').text(d.live ? JSON.stringify(d.live, null, 2) : '-');
        $('#raw-db-pre').text(JSON.stringify({ payment: d.payment, transactions: d.transactions }, null, 2));

        $('#md-loading').addClass('d-none');
        $('#md-error').addClass('d-none');
        $('#md-content').removeClass('d-none');
        $('#md-refresh').prop('disabled', !p.gateway_payment_id);
    }

    function openDetails(id) {
        currentId = id;
        $('#md-content, #md-error').addClass('d-none');
        $('#md-loading').removeClass('d-none');
        $('#md-reference, #md-status').empty();
        $('#md-refresh').prop('disabled', true);
        detailsModal.show();

        App.get('/api/payment-details.php', { id: id }).done(function (response) {
            renderDetails(response.data);
        }).fail(function (xhr) {
            const error = App.handleError(xhr, { silent: true });
            $('#md-loading').addClass('d-none');
            $('#md-error').text(error.message).removeClass('d-none');
        });
    }

    $body.on('click', '.btn-view', function () { openDetails($(this).data('id')); });

    $('#md-refresh').on('click', function () {
        const $button = $(this).prop('disabled', true);
        $button.find('.spinner-border').removeClass('d-none');
        $button.find('.btn-icon-lock').addClass('d-none');

        App.post('/api/refresh-status.php', { id: currentId }).done(function (response) {
            renderDetails(response.data);
            App.toast('success', response.message);
            loadList();
            loadStats();
        }).fail(function (xhr) {
            App.handleError(xhr);
        }).always(function () {
            $button.prop('disabled', false);
            $button.find('.spinner-border').addClass('d-none');
            $button.find('.btn-icon-lock').removeClass('d-none');
        });
    });

    const reload = function () { query.page = 1; loadList(); };
    $('#f-q').on('input', App.debounce(function () { query.q = $.trim($('#f-q').val()); reload(); }, 300));
    $('#f-status').on('change', function () { query.status = $(this).val(); reload(); });
    $('#f-from, #f-to').on('change', function () { query.from = $('#f-from').val(); query.to = $('#f-to').val(); reload(); });
    $('#f-reset').on('click', function () {
        $('#filters')[0].reset();
        Object.assign(query, { q: '', status: '', from: '', to: '' });
        reload();
    });
    $('#filters').on('submit', function (event) { event.preventDefault(); });
    $('#retry-load').on('click', loadList);

    loadStats();
    loadList();
}(jQuery, window.App));
