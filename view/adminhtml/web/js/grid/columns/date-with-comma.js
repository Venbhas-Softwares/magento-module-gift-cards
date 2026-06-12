/**
 * Gift card grids: date column with comma after year.
 */
define([
    'Magento_Ui/js/grid/columns/date'
], function (DateColumn) {
    'use strict';

    return DateColumn.extend({
        /**
         * Render label using a Moment format string directly.
         *
         * We intentionally avoid overriding `dateFormat` because the base date column
         * runs it through `mageUtils.normalizeDate()`, and Magento's token map treats
         * `D` as day-of-year (DDD), which breaks dates like "Apr 119".
         */
        getLabel: function (record) {
            return this._super(record, 'MMM D, YYYY, h:mm:ss A');
        }
    });
});

