define(['Venbhas_GiftCard/js/view/checkout/summary/totals'], function (Component) {
    'use strict';

    return Component.extend({
        isDisplayed: function () {
            return this.getPureValue() !== 0;
        }
    });
});
