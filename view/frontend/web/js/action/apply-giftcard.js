define([
    'jquery',
    'mage/translate',
    'Magento_Checkout/js/action/get-payment-information',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_SalesRule/js/model/payment/discount-messages'
], function ($, $t, getPaymentInformationAction, totals, fullScreenLoader, messageContainer) {
    'use strict';

    /** Magento validates POST requests with form_key (CSRF). Checkout exposes it as checkoutConfig.formKey */
    function getFormKey() {
        if (window.checkoutConfig && window.checkoutConfig.formKey) {
            return window.checkoutConfig.formKey;
        }
        if (typeof window.FORM_KEY !== 'undefined' && window.FORM_KEY) {
            return window.FORM_KEY;
        }
        try {
            var m = document.cookie.match(/(?:^|; )form_key=([^;]*)/);
            return m ? decodeURIComponent(m[1]) : '';
        } catch (e) {
            return '';
        }
    }

    return function (amount) {
        fullScreenLoader.startLoader();
        totals.isLoading(true);

        return $.ajax({
            url: (window.BASE_URL || '/') + 'venbhas_giftcard/checkout/apply',
            type: 'POST',
            dataType: 'json',
            data: {
                amount: amount,
                form_key: getFormKey()
            }
        }).done(function (response) {
            if (response && response.success) {
                messageContainer.addSuccessMessage({message: response.message || $t('Gift amount applied.')});

                var deferred = $.Deferred();
                getPaymentInformationAction(deferred, messageContainer);
                $.when(deferred).always(function () {
                    fullScreenLoader.stopLoader();
                    totals.isLoading(false);
                });
                return;
            }

            fullScreenLoader.stopLoader();
            totals.isLoading(false);
            messageContainer.addErrorMessage({
                message: (response && response.message) || $t('Could not apply gift amount.')
            });
        }).fail(function () {
            fullScreenLoader.stopLoader();
            totals.isLoading(false);
            messageContainer.addErrorMessage({message: $t('Could not apply gift amount.')});
        });
    };
});

