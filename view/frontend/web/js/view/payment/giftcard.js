define([
    'jquery',
    'ko',
    'uiComponent',
    'Venbhas_GiftCard/js/action/apply-giftcard',
    'Venbhas_GiftCard/js/action/remove-giftcard',
    'Magento_Checkout/js/model/quote',
    'Magento_SalesRule/js/model/payment/discount-messages',
    'Magento_Customer/js/customer-data'
], function ($, ko, Component, applyAction, removeAction, quote, messageContainer, customerData) {
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
        myCodes: ko.observableArray([]),

        initialize: function () {
            this._super();

            var totals = quote.getTotals();
            if (totals && totals()) {
                this._syncFromTotals(totals());
            }

            totals.subscribe(function (t) {
                this._syncFromTotals(t || {});
            }, this);

            this._loadMyCodes();

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
        },

        useMyCode: function (row) {
            if (!row || !row.code) {
                return;
            }
            this.giftcardCode(row.code);
            this.apply();
        },

        _loadMyCodes: function () {
            var customer = customerData.get('customer');
            var c = customer && customer();
            if (!c || !c.firstname) {
                this.myCodes([]);
                return;
            }

            $.getJSON((window.BASE_URL || '/') + 'venbhas_giftcard/checkout/mycodes')
                .done(function (resp) {
                    if (resp && resp.success && Array.isArray(resp.codes)) {
                        this.myCodes(resp.codes);
                    } else {
                        this.myCodes([]);
                    }
                }.bind(this))
                .fail(function () {
                    this.myCodes([]);
                }.bind(this));
        }
    });
});
