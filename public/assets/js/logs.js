(function ($, App) {
    'use strict';

    const query = { page: 1, action: '', direction: '', result: '' };
    const $body = $('#logs-body');
    let actionsLoaded = false;

    function pretty(raw) {
        if (raw === null || raw === undefined) {
            return '-';
        }
        try {
            return JSON.stringify(typeof raw === 'string' ? JSON.parse(raw) : raw, null, 2);
        } catch (e) {
            return String(raw);
        }
    }

    function detailRow(log) {
        const $pre = function (label, raw) {
            return $('<div class="log-pane">').append($('<h3 class="log-pane-title">').text(label), $('<pre class="json-view" dir="ltr">').text(pretty(raw)));
        };

        return $('<tr class="log-detail d-none">').append(
            $('<td colspan="6">').append($('<div class="log-panes">').append($pre(App.t('logs.request'), log.request_body), $pre(App.t('logs.response'), log.response_body)))
        );
    }

    function buildRows(log) {
        const ok = log.http_code >= 200 && log.http_code < 300;
        const detailId = 'log-detail-' + log.id;
        const $toggle = $('<button type="button" class="btn btn-sm btn-outline-secondary log-toggle" aria-expanded="false">')
            .attr('aria-controls', detailId)
            .append($('<i class="bi bi-chevron-down" aria-hidden="true">'), document.createTextNode(' ' + App.t('logs.details')));

        const $main = $('<tr class="log-row">').append(
            $('<td class="date-cell" dir="ltr">').text(App.formatDate(log.created_at)),
            $('<td>').append($('<span class="dir-pill">').addClass('dir-' + log.direction).append(
                $('<i class="bi">').addClass(log.direction === 'inbound' ? 'bi-box-arrow-in-down' : 'bi-box-arrow-up').attr('aria-hidden', 'true'),
                document.createTextNode(' ' + App.t('logs.' + log.direction))
            )),
            $('<td>').append($('<code>').text(log.action)),
            $('<td>').append($('<span class="http-pill">').addClass(ok ? 'ok' : 'bad').text(log.http_code === 0 ? App.t('logs.no_response') : log.http_code)),
            $('<td class="text-end mono" dir="ltr">').text(log.duration_ms + ' ms'),
            $('<td class="text-end">').append($toggle)
        );
        const $detail = detailRow(log).attr('id', detailId);

        $toggle.on('click', function () {
            const open = $detail.hasClass('d-none');
            $detail.toggleClass('d-none', !open);
            $toggle.attr('aria-expanded', open ? 'true' : 'false').find('i').toggleClass('bi-chevron-down', !open).toggleClass('bi-chevron-up', open);
        });

        return [$main, $detail];
    }

    function load() {
        $body.empty();
        for (let i = 0; i < 5; i += 1) {
            const $tr = $('<tr class="skeleton-row" aria-hidden="true">');
            for (let c = 0; c < 6; c += 1) { $tr.append($('<td>').append($('<span class="sk sk-line">'))); }
            $body.append($tr);
        }
        $('#logs-empty').addClass('d-none');

        App.get('/api/logs-list.php', query).done(function (response) {
            const data = response.data;
            $body.empty();
            $('#logs-empty').toggleClass('d-none', data.rows.length > 0);
            data.rows.forEach(function (log) { $body.append(buildRows(log)); });

            if (!actionsLoaded) {
                data.actions.forEach(function (action) { $('#l-action').append($('<option>').val(action).text(action)); });
                actionsLoaded = true;
            }

            $('#log-info').text(App.t('logs.total', { total: data.total }));
            App.renderPagination($('#log-pagination'), data.page, data.pages, function (page) { query.page = page; load(); });
        }).fail(function (xhr) {
            $body.empty();
            App.handleError(xhr);
        });
    }

    $('#l-action, #l-direction, #l-result').on('change', function () {
        query.action = $('#l-action').val();
        query.direction = $('#l-direction').val();
        query.result = $('#l-result').val();
        query.page = 1;
        load();
    });
    $('#log-filters').on('submit', function (event) { event.preventDefault(); });

    load();
}(jQuery, window.App));
