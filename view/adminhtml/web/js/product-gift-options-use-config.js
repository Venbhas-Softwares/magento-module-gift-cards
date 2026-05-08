define(['jquery'], function ($) {
    'use strict';

    var ATTRS = [
        'venbhas_gc_delivery_type',
        'venbhas_gc_allow_custom_amount',
        'venbhas_gc_amounts_available',
        'venbhas_gc_allow_custom_message'
    ];

    function buildCheckbox(attrCode, checked) {
        var id = attrCode + '_use_config';
        var $wrap = $('<div/>', { class: 'admin__field-use-config' });

        var $checkbox = $('<input/>', {
            type: 'checkbox',
            class: 'admin__control-checkbox',
            id: id
        });

        if (checked) {
            $checkbox.prop('checked', true);
        }

        var $label = $('<label/>', {
            class: 'admin__field-label',
            for: id
        }).append($('<span/>').text($.mage ? $.mage.__('Use Config Settings') : 'Use Config Settings'));

        $wrap.append($checkbox).append(' ').append($label);
        return { $wrap: $wrap, $checkbox: $checkbox };
    }

    function setDisabled($el, disabled) {
        $el.prop('disabled', disabled);
        $el.trigger('change');
    }

    function isUsingConfig($el) {
        if (!$el.length) {
            return false;
        }
        var v = $el.val();
        return v === null || String(v) === '';
    }

    function ensureEmptyOption($select) {
        if (!$select.is('select')) {
            return;
        }
        var $empty = $select.find('option[value=""]');
        if (!$empty.length) {
            $select.prepend($('<option/>', { value: '' }).text(''));
        }
    }

    function initForAttr(attrCode) {
        var $field = $('#' + attrCode);
        if (!$field.length) {
            // Fallback: name-based lookup (some admin renderers omit id)
            $field = $('[name="product[' + attrCode + ']"]');
        }
        if (!$field.length) {
            return;
        }

        var $adminField = $field.closest('.admin__field');
        if (!$adminField.length) {
            return;
        }

        var $control = $adminField.find('.admin__field-control').first();
        if (!$control.length) {
            $control = $field.parent();
        }

        // Avoid double init.
        if ($adminField.data('venbhasUseConfigInit')) {
            return;
        }
        $adminField.data('venbhasUseConfigInit', true);

        var usingConfig = isUsingConfig($field);
        ensureEmptyOption($field);

        var built = buildCheckbox(attrCode, usingConfig);
        $control.append(built.$wrap);

        // Preserve previous manual value to restore when unchecking.
        if (!usingConfig) {
            $field.data('venbhasPrevValue', $field.val());
        }

        if (usingConfig) {
            setDisabled($field, true);
        }

        built.$checkbox.on('change', function () {
            var checked = $(this).is(':checked');

            if (checked) {
                // Store current value (if any) and force empty to use config.
                var current = $field.val();
                if (current !== null && String(current) !== '') {
                    $field.data('venbhasPrevValue', current);
                }
                ensureEmptyOption($field);
                $field.val('');
                setDisabled($field, true);
            } else {
                setDisabled($field, false);

                var prev = $field.data('venbhasPrevValue');
                if (prev !== undefined && prev !== null && String(prev) !== '') {
                    $field.val(prev);
                    $field.trigger('change');
                    return;
                }

                // Reasonable default for selects: first non-empty option.
                if ($field.is('select')) {
                    var $opt = $field.find('option').filter(function () {
                        return String($(this).attr('value') || '') !== '';
                    }).first();
                    if ($opt.length) {
                        $field.val($opt.attr('value'));
                        $field.trigger('change');
                    }
                }
            }
        });
    }

    return function () {
        $(function () {
            ATTRS.forEach(initForAttr);
        });
    };
});

