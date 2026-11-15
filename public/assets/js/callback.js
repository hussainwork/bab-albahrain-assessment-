(function ($) {
    'use strict';

    // When the gateway redirects inside the checkout iframe, the receipt must take over the whole window.
    if (window.top !== window.self) {
        try {
            window.top.location.href = window.location.href;
        } catch (e) {
            window.top.location.replace(window.location.href);
        }
        return;
    }

    $('#print-receipt').on('click', function () { window.print(); });
    $('#reload-status').on('click', function () { window.location.reload(); });
}(jQuery));
