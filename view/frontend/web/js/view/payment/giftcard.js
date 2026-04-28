define([
    'jquery',
    'ko',
    'uiComponent',
    'Venbhas_GiftCard/js/action/apply-giftcard',
    'Venbhas_GiftCard/js/action/remove-giftcard',
    'Magento_Checkout/js/model/quote',
    'Magento_SalesRule/js/model/payment/discount-messages',
    'Magento_Customer/js/customer-data',
    'Magento_Ui/js/modal/modal',
    'Magento_Checkout/js/action/get-payment-information',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/model/full-screen-loader'
], function ($, ko, Component, applyAction, removeAction, quote, messageContainer, customerData, modal, getPaymentInformationAction, totals, fullScreenLoader) {
    'use strict';

    function readAppliedAmount(totals) {
        if (!totals || typeof totals !== 'object') {
            return 0;
        }
        return Number(totals.base_venbhas_giftcard_amount || totals.venbhas_giftcard_amount || 0) || 0;
    }

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

    return Component.extend({
        defaults: {
            template: 'Venbhas_GiftCard/payment/giftcard'
        },

        walletBalance: ko.observable(0),
        applyAmount: ko.observable(''),
        isApplied: ko.observable(false),
        appliedAmount: ko.observable(0),
        showAddButton: ko.observable(false),
        /** @type {boolean} Modal widget created (DOM for payment block may not exist at first init). */
        _addGiftcardModalReady: false,

        initialize: function () {
            this._super();

            var totals = quote.getTotals();
            if (totals && totals()) {
                this._syncFromTotals(totals());
            }

            totals.subscribe(function (t) {
                this._syncFromTotals(t || {});
            }, this);

            this._syncLoginState();
            this._loadWallet();

            return this;
        },

        _syncFromTotals: function (totals) {
            var applied = readAppliedAmount(totals);
            this.appliedAmount(applied);
            this.isApplied(applied > 0.0001);
        },

        apply: function () {
            var amt = Number((this.applyAmount() || '').toString().replace(/[^0-9.]/g, '')) || 0;
            if (!amt || amt <= 0.0001) {
                messageContainer.addErrorMessage({message: $.mage.__('Please enter a gift amount.')});
                return;
            }
            var wallet = Number(this.walletBalance()) || 0;
            if (amt > wallet + 0.009) {
                messageContainer.addErrorMessage({
                    message: $.mage.__('The amount cannot exceed your available gift balance (%1).')
                        .replace('%1', wallet.toFixed(2))
                });
                return;
            }
            applyAction(amt);
        },

        remove: function () {
            removeAction();
            this.applyAmount('');
        },

        _syncLoginState: function () {
            var customer = customerData.get('customer');
            var c = customer && customer();
            this.showAddButton(!!(c && c.firstname));
        },

        /**
         * Build modal widget once DOM exists (checkout renders payment UI after uiComponent init).
         *
         * @returns {boolean} True if modal is ready to use
         */
        _ensureAddGiftcardModal: function () {
            if (this._addGiftcardModalReady) {
                return true;
            }
            var $modalEl = $('#venbhas-add-giftcard-modal-checkout');
            if (!$modalEl.length) {
                return false;
            }
            var $input = $('#venbhas-giftcard-code-checkout');
            var $msg = $modalEl.find('.venbhas-add-giftcard-msg');
            function showMsg(text) {
                if (!$msg.length) {
                    return;
                }
                $msg.text(text || '').show();
            }
            function clearMsg() {
                if ($msg.length) {
                    $msg.text('').hide();
                }
            }

            modal({
                type: 'popup',
                responsive: true,
                innerScroll: true,
                title: $.mage ? $.mage.__('Add a new gift card') : 'Add a new gift card',
                buttons: [{
                    text: $.mage ? $.mage.__('Add') : 'Add',
                    class: 'action primary',
                    click: function () {
                        clearMsg();
                        var code = ($input.val() || '').trim();
                        if (!code) {
                            showMsg($.mage.__('Please enter a gift card code.'));
                            return;
                        }

                        fullScreenLoader.startLoader();
                        totals.isLoading(true);

                        $.ajax({
                            url: (window.BASE_URL || '/') + 'venbhas_giftcard/account/addGiftcard',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                form_key: getFormKey(),
                                giftcard_code: code,
                                source: 'checkout'
                            }
                        }).done(function (res) {
                            if (res && res.success) {
                                messageContainer.addSuccessMessage({message: res.message || $.mage.__('Gift card added.')});
                                $modalEl.modal('closeModal');

                                // Refresh wallet + totals (no page reload).
                                this._loadWallet();
                                var deferred = $.Deferred();
                                getPaymentInformationAction(deferred, messageContainer);
                                $.when(deferred).always(function () {
                                    fullScreenLoader.stopLoader();
                                    totals.isLoading(false);
                                });
                            } else {
                                fullScreenLoader.stopLoader();
                                totals.isLoading(false);
                                showMsg((res && res.message) ? res.message : $.mage.__('Unable to add gift card.'));
                            }
                        }.bind(this)).fail(function () {
                            fullScreenLoader.stopLoader();
                            totals.isLoading(false);
                            showMsg($.mage.__('Unable to add gift card.'));
                        });
                    }.bind(this)
                }]
            }, $modalEl);

            this._addGiftcardModalReady = true;
            return true;
        },

        openAddGiftcard: function () {
            if (!this.showAddButton()) {
                return;
            }
            var self = this;
            var attempts = 0;
            var maxAttempts = 40;

            function tryOpen() {
                attempts += 1;
                if (!self._ensureAddGiftcardModal()) {
                    if (attempts < maxAttempts) {
                        window.setTimeout(tryOpen, 50);
                    }
                    return;
                }
                $('#venbhas-giftcard-code-checkout').val('');
                $('#venbhas-add-giftcard-modal-checkout').find('.venbhas-add-giftcard-msg').text('').hide();
                $('#venbhas-add-giftcard-modal-checkout').modal('openModal');
            }

            tryOpen();
        },

        _loadWallet: function () {
            $.getJSON((window.BASE_URL || '/') + 'venbhas_giftcard/checkout/wallet')
                .done(function (resp) {
                    if (resp && resp.success) {
                        this.walletBalance(Number(resp.balance || 0) || 0);
                    } else {
                        this.walletBalance(0);
                    }
                }.bind(this))
                .fail(function () {
                    this.walletBalance(0);
                }.bind(this));
        }
    });
});
