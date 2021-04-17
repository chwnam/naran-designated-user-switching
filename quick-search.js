/* globals jQuery, ndusQuickSearch */
(function ($) {
    var opts = $.extend({
            ajaxUrl: '',
            nonce: {
                search: '',
                switch: ''
            }
        }, ndusQuickSearch),
        search = $('#ndus-quick-search'),
        apply = $('#ndus-switch');

    search.autocomplete({
        minLength: 3,
        delay: 800,
        source: function (request, callback) {
            $.ajax(opts.ajaxUrl, {
                method: 'get',
                data: {
                    action: 'ndus_request_user_search',
                    nonce: opts.nonce.search,
                    keyword: request.term
                },
                beforeSend: function () {
                    apply.prop('disabled', 'disabled');
                },
                success: function (response) {
                    var users = [];
                    if (response.success) {
                        users = $.map(response.data, function (elem) {
                            return {
                                label: elem.label,
                                value: elem.value,
                            }
                        });
                        callback(users);
                    }
                }
            });
        },
        select: function (event, ui) {
            if (ui.item.value.length > 0) {
                this.value = ui.item.value;
                apply.focus();
                apply.removeAttr('disabled');
            }
        }
    }).autocomplete('widget').addClass('ndus');

    search.focus(function () {
        search.closest('div.ab-sub-wrapper').css('display', 'block');
        search.closest('li').addClass('hover');
    }).focusout(function () {
        if (!search.val().trim().length) {
            search.closest('div.ab-sub-wrapper').removeAttr('style');
            search.closest('li').removeClass('hover');
        }
    });

    apply.on('click', function () {
        $.ajax(opts.ajaxUrl, {
            method: 'get',
            data: {
                action: 'ndus_request_user_switch',
                nonce: opts.nonce.switch,
                user_login: search.val(),
            },
            beforeSend: function () {
                apply.prop('disabled', 'disabled');
            },
            success: function (response) {
                if (response.success && response.data.url && response.data.url.length > 0) {
                    window.location.href = response.data.url.replace(/&amp;/g, '&');
                }
            }
        });
    });
})(jQuery);
