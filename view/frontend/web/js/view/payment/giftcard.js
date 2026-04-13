define([
    'jquery',
    'ko',
    'uiComponent',
    'Venbhas_GiftCard/js/action/apply-giftcard',
    'Venbhas_GiftCard/js/action/remove-giftcard',
    'Magento_Checkout/js/model/quote',
    'Magento_SalesRule/js/model/payment/discount-messages'
], function ($, ko, Component, applyAction, removeAction, quote, messageContainer) {
    'use strict';

    function readTotalsExtras(totals) {
        if (!totals || typeof totals !== 'object') {
            return {codes: '', detailsRaw: ''};
        }
        var ext = totals.extension_attributes || {};
        return {
            codes: totals.venbhas_giftcard_codes || ext.venbhas_giftcard_codes || '',
            detailsRaw: totals.venbhas_giftcard_balance_details || ext.venbhas_giftcard_balance_details || ''
        };
    }

    function parseDetails(raw) {
        if (!raw || typeof raw !== 'string') {
            return [];
        }
        try {
            var d = JSON.parse(raw);
            return Array.isArray(d) ? d : [];
        } catch (e) {
            return [];
        }
    }

    return Component.extend({
        defaults: {
            template: 'Venbhas_GiftCard/payment/giftcard'
        },

        giftcardCode: ko.observable(''),
        isApplied: ko.observable(false),
        appliedCodes: ko.observableArray([]),
        balanceDetails: ko.observableArray([]),

        initialize: function () {
            this._super();

            var totals = quote.getTotals();
            if (totals && totals()) {
                this._syncFromTotals(totals());
            }

            totals.subscribe(function (t) {
                this._syncFromTotals(t || {});
            }, this);

            return this;
        },

        _syncFromTotals: function (totals) {
            var extra = readTotalsExtras(totals);
            var codes = extra.codes;
            if (typeof codes === 'string' && codes.length) {
                var arr = codes.split(',').map(function (c) { return (c || '').trim(); }).filter(Boolean);
                this.appliedCodes(arr);
                this.isApplied(arr.length > 0);
            } else {
                this.appliedCodes([]);
                this.isApplied(false);
            }
            this.balanceDetails(parseDetails(extra.detailsRaw));
        },

        apply: function () {
            var code = (this.giftcardCode() || '').trim();
            if (!code) {
                messageContainer.addErrorMessage({message: $.mage.__('Please enter a gift card code.')});
                return;
            }
            applyAction(code);
            this.giftcardCode('');
        },

        remove: function (code) {
            removeAction(code);
        }
    });
});
