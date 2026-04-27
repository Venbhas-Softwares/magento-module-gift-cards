define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function ($, modal) {
    'use strict';

    return function (config) {
        var $modalEl = $(config.modalSelector);
        var $btn = $(config.buttonSelector);
        var $input = $(config.inputSelector);

        var modalInstance = modal({
            type: 'popup',
            responsive: true,
            innerScroll: true,
            title: $.mage ? $.mage.__('Add a new gift card') : 'Add a new gift card',
            buttons: [{
                text: $.mage ? $.mage.__('Add') : 'Add',
                class: 'action primary',
                click: function () {
                    var code = ($input.val() || '').trim();
                    if (!code) {
                        alert('Please enter a gift card code.');
                        return;
                    }

                    $.ajax({
                        url: config.postUrl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            form_key: config.formKey,
                            giftcard_code: code
                        }
                    }).done(function (res) {
                        if (res && res.success) {
                            window.location.reload();
                        } else {
                            alert((res && res.message) ? res.message : 'Unable to add gift card.');
                        }
                    }).fail(function () {
                        alert('Unable to add gift card.');
                    });
                }
            }]
        }, $modalEl);

        $btn.on('click', function () {
            $input.val('');
            $modalEl.modal('openModal');
        });

        return modalInstance;
    };
});

