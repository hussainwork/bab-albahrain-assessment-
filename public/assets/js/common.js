(function (window, $) {
    'use strict';

    const config = JSON.parse(document.getElementById('app-config').textContent);
    const App = { config: config };

    App.t = function (key, params) {
        let text = Object.prototype.hasOwnProperty.call(config.strings, key) ? config.strings[key] : key;
        if (params) {
            Object.keys(params).forEach(function (name) {
                text = text.split('{' + name + '}').join(params[name]);
            });
        }
        return text;
    };

    App.money = function (amount, currency) {
        const value = Number(amount).toLocaleString('en-US', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
        return App.t('currency.' + (currency || 'BHD')) + ' ' + value;
    };

    App.formatDate = function (value) {
        const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2}:\d{2})/.exec(value || '');
        return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] : '-';
    };

    App.debounce = function (fn, wait) {
        let timer;
        return function () {
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    };

    App.toast = function (type, message) {
        const icons = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-octagon-fill', info: 'bi-info-circle-fill', warning: 'bi-exclamation-triangle-fill' };
        const $toast = $('<div class="toast app-toast border-0" aria-atomic="true">')
            .addClass('toast-' + type)
            .attr('role', type === 'danger' ? 'alert' : 'status');
        const $row = $('<div class="d-flex align-items-center">');
        $row.append(
            $('<div class="toast-body">').append($('<i class="bi">').addClass(icons[type] || icons.info).attr('aria-hidden', 'true'), document.createTextNode(' ' + message)),
            $('<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast">').attr('aria-label', App.t('common.close'))
        );
        $toast.append($row).appendTo('#toastHost');
        $toast.on('hidden.bs.toast', function () { $toast.remove(); });
        new bootstrap.Toast($toast[0], { delay: type === 'danger' ? 7000 : 4000 }).show();
    };

    App.request = function (method, url, data) {
        const isGet = method === 'GET';

        return $.ajax({
            url: url,
            method: method,
            data: isGet ? data : JSON.stringify(data || {}),
            contentType: isGet ? undefined : 'application/json; charset=utf-8',
            dataType: 'json',
            headers: { 'X-CSRF-Token': config.csrf }
        });
    };

    App.get = function (url, data) { return App.request('GET', url, data); };
    App.post = function (url, data) { return App.request('POST', url, data); };

    // One place that turns any failed request into a message and a field map.
    App.parseError = function (xhr) {
        const body = xhr.responseJSON || {};

        return {
            status: xhr.status,
            message: body.message || (xhr.status === 0 ? App.t('error.network') : App.t('error.server')),
            errors: body.errors || {}
        };
    };

    App.handleError = function (xhr, options) {
        const error = App.parseError(xhr);
        if (!options || !options.silent) {
            App.toast('danger', error.message);
        }

        return error;
    };

    App.statusPill = function (status) {
        const icons = { paid: 'bi-check-circle-fill', failed: 'bi-x-circle-fill', pending: 'bi-hourglass-split', initiated: 'bi-circle-half', cancelled: 'bi-slash-circle-fill' };

        return $('<span class="status-pill">')
            .addClass('status-' + status)
            .append($('<i class="bi">').addClass(icons[status] || 'bi-circle').attr('aria-hidden', 'true'), document.createTextNode(' ' + App.t('status.' + status)));
    };

    App.copyButton = function (value) {
        return $('<button type="button" class="btn-copy">')
            .attr({ 'data-copy': value, 'aria-label': App.t('common.copy') })
            .append($('<i class="bi bi-clipboard" aria-hidden="true">'));
    };

    App.renderPagination = function ($list, page, pages, onGo) {
        $list.empty();
        if (pages <= 1) {
            return;
        }

        const add = function (label, target, options) {
            const opts = options || {};
            const $li = $('<li class="page-item">').toggleClass('disabled', !!opts.disabled).toggleClass('active', !!opts.active);
            const $a = $('<button type="button" class="page-link">').attr('aria-label', opts.aria || null).append(label);
            if (opts.active) { $a.attr('aria-current', 'page'); }
            if (opts.disabled) { $a.prop('disabled', true); }
            $a.on('click', function () { onGo(target); });
            $list.append($li.append($a));
        };

        const rtl = config.dir === 'rtl';
        add($('<i class="bi">').addClass(rtl ? 'bi-chevron-right' : 'bi-chevron-left').attr('aria-hidden', 'true'), page - 1, { disabled: page === 1, aria: App.t('common.previous') });

        const start = Math.max(1, Math.min(page - 2, pages - 4));
        const end = Math.min(pages, start + 4);
        for (let i = start; i <= end; i += 1) {
            add(String(i), i, { active: i === page });
        }

        add($('<i class="bi">').addClass(rtl ? 'bi-chevron-left' : 'bi-chevron-right').attr('aria-hidden', 'true'), page + 1, { disabled: page === pages, aria: App.t('common.next') });
    };

    $(document).on('click', '.btn-copy', function () {
        const $button = $(this);
        const value = String($button.attr('data-copy'));

        const done = function () {
            $button.find('i').attr('class', 'bi bi-clipboard-check');
            App.toast('success', App.t('common.copied'));
            setTimeout(function () { $button.find('i').attr('class', 'bi bi-clipboard'); }, 1500);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(done);
            return;
        }

        const $tmp = $('<textarea readonly>').val(value).css({ position: 'fixed', opacity: 0 }).appendTo('body');
        $tmp[0].select();
        document.execCommand('copy');
        $tmp.remove();
        done();
    });

    window.App = App;
}(window, jQuery));
