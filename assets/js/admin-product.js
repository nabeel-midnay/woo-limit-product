jQuery(function ($) {

    const wooLimitStatus = $('#_woo_limit_status');
    const wooLimitStartValueField = $('._woo_limit_start_value_field');
    const wooLimitEndValueField = $('._woo_limit_end_value_field');

    let wooLimitErrorMessage = $('._woo_limit_error_message');
    if (!wooLimitErrorMessage.length) {
        wooLimitErrorMessage = $('<p class="_woo_limit_error_message" style="color: #ff0000; display:none;"></p>');
        wooLimitEndValueField.after(wooLimitErrorMessage);
    }
    wooLimitErrorMessage.text('End value must be greater than start value');

    toggleWooLimitFields();

    $(document).on('change', '#_woo_limit_status', function () {
        toggleWooLimitFields();
    });

    wooLimitEndValueField.find('input').on('blur', function () {
        validateWooLimitFields(false);
    });

    $('#publish').on('click', function (e) {
        if (!validateWooLimitFields(true)) {
            e.preventDefault();
        }
    });


    function toggleWooLimitFields() {
        const enabled = wooLimitStatus.is(':checked');

        if (enabled) {
            wooLimitStartValueField.show();
            wooLimitEndValueField.show();
        } else {
            wooLimitStartValueField.hide();
            wooLimitEndValueField.hide();
            wooLimitStartValueField.find('input').val('');
            wooLimitEndValueField.find('input').val('');
            wooLimitErrorMessage.hide();
        }
    }

    function validateWooLimitFields(focus = false) {
        const startValue = parseFloat(wooLimitStartValueField.find('input').val());
        const endValue = parseFloat(wooLimitEndValueField.find('input').val());

        if (isNaN(startValue) || isNaN(endValue)) {
            wooLimitErrorMessage.hide();
            return true;
        }

        if (endValue <= startValue) {
            if (focus) {
                wooLimitEndValueField.find('input').focus();
            }
            wooLimitErrorMessage.show();
            return false;
        }

        wooLimitErrorMessage.hide();
        return true;
    }

});
