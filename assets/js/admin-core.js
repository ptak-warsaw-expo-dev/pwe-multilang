(function ($) {
    function initSuccessToast() {
        const $toast = $('.pwe-toast-success');

        if (!$toast.length) {
            return;
        }

        window.setTimeout(function () {
            $toast.addClass('is-hidden');

            window.setTimeout(function () {
                $toast.remove();
            }, 260);
        }, 2600);
    }

    $(document).ready(function () {
        initSuccessToast();
    });
})(jQuery);

