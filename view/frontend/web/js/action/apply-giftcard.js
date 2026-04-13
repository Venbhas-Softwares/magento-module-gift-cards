define([
    'jquery',
    'mage/storage',
    'mage/translate',
    'Magento_Checkout/js/action/get-payment-information',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_SalesRule/js/model/payment/discount-messages'
], function ($, storage, $t, getPaymentInformationAction, totals, fullScreenLoader, messageContainer) {
    'use strict';

    return function (code) {
        fullScreenLoader.startLoader();
        totals.isLoading(true);

        return storage.post(
            'venbhas_giftcard/checkout/apply',
            JSON.stringify({giftcard_code: code}),
            false
        ).done(function (response) {
            var deferred = $.Deferred();
            getPaymentInformationAction(deferred, messageContainer);
            $.when(deferred).done(function () {
                fullScreenLoader.stopLoader();
                totals.isLoading(false);
            });
            if (response && response.success) {
                messageContainer.addSuccessMessage({message: response.message || $t('Gift card applied.')});
            } else {
                messageContainer.addErrorMessage({message: (response && response.message) || $t('Could not apply gift card.')});
            }
        }).fail(function () {
            fullScreenLoader.stopLoader();
            totals.isLoading(false);
            messageContainer.addErrorMessage({message: $t('Could not apply gift card.')});
        });
    };
});

