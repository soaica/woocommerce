'use strict';
jQuery(document).ready(function($) {
    var style_bd_input = $('.style_bd input').attr('value');
    $('#' + style_bd_input).addClass('current');
    var woosbTimeout = null;

    woosb_active_settings();

    $('#product-type').on('change', function() {
        woosb_active_settings();
        woosb_change_regular_price();
    });

    // hide search result box by default
    $('#woosb_results').hide();
    $('#woosb_loading').hide();

    // total price
    if ($('#product-type').val() == 'woosb') {
        woosb_change_regular_price();
    }

    // set regular price
    $('#woosb_set_regular_price').on('click', function() {
        if ($('#woosb_disable_auto_price').is(':checked')) {
            $('li.general_tab a').trigger('click');
            $('#_regular_price').prop('readonly', false);
            $('#_regular_price').focus();
        } else {
            $('#_regular_price').prop('readonly', true);
            alert('You must disable auto calculate regular price first!');
        }
    });

    // set optional
    $('#woosb_optional_products').on('click', function() {
        if ($(this).is(':checked')) {
            $('.woosb_tr_show_if_optional_products').show();
        } else {
            $('.woosb_tr_show_if_optional_products').hide();
        }
    });

    // set sale price
    $('#woosb_set_sale_price').on('click', function() {
        if ($('#woosb_price_percent').val() == '') {
            $('li.general_tab a').trigger('click');
            if ($('#woosb_disable_auto_price').is(':checked')) {
                $('#_regular_price').prop('readonly', false);
            } else {
                $('#_regular_price').prop('readonly', true);
            }
            $('#_sale_price').prop('readonly', false);
            $('#_sale_price').focus();
        } else {
            $('#_sale_price').prop('readonly', true);
            alert('You must leave blank for percentage field first!');
        }
    });

    // change regular price
    $('#_regular_price').on('keyup change', function() {
        if ($('#product-type').val() == 'woosb') {
            woosb_change_sale_price();
        }
    });

    // change price percent
    $('#woosb_price_percent').on('keyup change', function() {
        woosb_change_sale_price();
    });

    // checkbox
    $('#woosb_disable_auto_price').on('change', function() {
        if ($('#product-type').val() == 'woosb') {
            woosb_change_regular_price();
        }
    });

    // search input
    $('#woosb_keyword').keyup(function() {
        if ($('#woosb_keyword').val() != '') {
            $('#woosb_loading').show();
            if (woosbTimeout != null) {
                clearTimeout(woosbTimeout);
            }
            woosbTimeout = setTimeout(woosb_ajax_get_data, 300);
            return false;
        }
    });

    // actions on search result items
    $('#woosb_results').on('click', 'li', function() {
        $(this).children('span.qty').html('<input type="number" value="1" min="0"/>');
        $(this).children('span.remove').html('×');
        $('#woosb_selected ul').append($(this));
        $('#woosb_results').hide();
        $('#woosb_keyword').val('');
        woosb_get_ids();
        woosb_change_regular_price();
        woosb_arrange();
        get_woosb_ajax_img();
        ajax_error_layout();
        return false;
    });

    // change qty of each item
    $('#woosb_selected').on('keyup change', '.qty input', function() {
        var num = $(this).val();
        var cid = $(this).parent().parent().data('id');
        woosb_get_ids();
        woosb_change_regular_price();
        return false;
    });

    // change shipping fee
    $('#woosb_shipping_fee').on('change', function() {
        if ($(this).val() == 'whole') {
            $('#woosb_set_shipping_class').show();
        } else {
            $('#woosb_set_shipping_class').hide();
        }
    });
    $(document).on('click', '#woosb_set_shipping_class', function(e) {
        $('li.shipping_options a').trigger('click');
        e.preventDefault();
    });

    // actions on selected items
    $('#woosb_selected').on('click', 'span.remove', function() {
        $(this).parent().remove();
        woosb_get_ids();
        woosb_change_regular_price();
        get_woosb_ajax_img();
        ajax_error_layout();
        return false;
    });

    // hide search result box if click outside
    $(document).on('click', function(e) {
        if ($(e.target).closest($('#woosb_results')).length == 0) {
            $('#woosb_results').hide();
        }
    });

    // arrange
    woosb_arrange();

    $(document).on('woosbDragEndEvent', function() {
        woosb_get_ids();
        get_woosb_ajax_img();
        ajax_error_layout();
    });

    // hide updated
    setTimeout(function() {
        $('.woosb_updated_price').slideUp();
    }, 3000);

    // ajax update price
    $('.woosb-update-price-btn').on('click', function(e) {
        var this_btn = $(this);
        if (!this_btn.hasClass('disabled')) {
            this_btn.addClass('disabled');
            var count = 0;
            (
                function woosb_update_price() {
                    var data = {
                        action: 'woosb_update_price',
                        woosb_nonce: woosb_vars.woosb_nonce
                    };
                    setTimeout(function() {
                        jQuery.post(ajaxurl, data, function(response) {
                            var response_num = Number(response);
                            if (response_num != 0) {
                                count += response_num;
                                woosb_update_price();
                                $('.woosb_updated_price_ajax').html('Updating... ' + count);
                            } else {
                                $('.woosb_updated_price_ajax').html('Finished! ' + count + ' updated.');
                                this_btn.removeClass('disabled');
                            }
                        });
                    }, 1000);
                }
            )();
        }
        e.preventDefault();
    });

    // metabox
    $('#woosb_meta_box_update_price').on('click', function(e) {
        var btn = $(this);
        if (!btn.hasClass('disabled')) {
            var btn_text = btn.val();
            var product_id = btn.attr('data-id');
            btn.val(btn_text + '...').addClass('disabled');
            $('#woosb_meta_box_update_price_result').html('').prepend('<li>Start!</li>');
            var count = 0;
            (
                function woosb_metabox_update_price() {
                    var data = {
                        action: 'woosb_metabox_update_price',
                        product_id: product_id,
                        count: count,
                        woosb_nonce: woosb_vars.woosb_nonce
                    };
                    setTimeout(function() {
                        jQuery.post(ajaxurl, data, function(response) {
                            if (response != 0) {
                                $('#woosb_meta_box_update_price_result').prepend(response);
                                count++;
                                woosb_metabox_update_price();
                            } else {
                                $('#woosb_meta_box_update_price_result').prepend('<li>Finished!</li>');
                                btn.val(btn_text).removeClass('disabled');
                            }
                        });
                    }, 100);
                }
            )();
        }
    });
    //click chose img layout
    $(document).on('click', '.style_bd img', function() {
        if ($('.style_bd img').hasClass('current')) {
            $('.style_bd img').removeClass('current');
            $(this).addClass('current');
        } else {
            $(this).addClass('current');
        }
        var a = $(this).attr('id');
        var b = document.querySelector("#input_layout_contain");
        b.setAttribute("value", a);
        ajax_error_layout();
    });

    function woosb_arrange() {
        $('#woosb_selected li').arrangeable({
            dragEndEvent: 'woosbDragEndEvent',
            dragSelector: '.move',
        });
    }

    function woosb_get_ids() {
        var listId = new Array();
        $('#woosb_selected li').each(function() {
            listId.push($(this).data('id') + '/' + $(this).find('input').val());
        });
        if (listId.length > 0) {
            $('#woosb_ids').val(listId.join(','));
        } else {
            $('#woosb_ids').val('');
        }
    }

    function woosb_active_settings() {
        if ($('#product-type').val() == 'woosb') {
            $('#general_product_data .pricing').addClass('show_if_woosb');
            $('#_downloadable').closest('label').addClass('show_if_woosb').removeClass('show_if_simple');
            $('#_virtual').closest('label').addClass('show_if_woosb').removeClass('show_if_simple');
            $('.show_if_external').hide();
            $('.show_if_simple').show();
            $('.show_if_woosb').show();
            $('.product_data_tabs li').removeClass('active');
            $('.woosb_tab').addClass('active');
            $('.panel-wrap .panel').hide();
            $('#woosb_settings').show();
            $('#_regular_price').prop('readonly', true);
            if ($('#woosb_optional_products').is(':checked')) {
                $('.woosb_tr_show_if_optional_products').show();
            } else {
                $('.woosb_tr_show_if_optional_products').hide();
            }
            if ($('#woosb_shipping_fee').val() == 'whole') {
                $('#woosb_set_shipping_class').show();
            } else {
                $('#woosb_set_shipping_class').hide();
            }
        } else {
            $('#general_product_data .pricing').removeClass('show_if_woosb');
            $('#_downloadable').closest('label').removeClass('show_if_woosb').addClass('show_if_simple');
            $('#_virtual').closest('label').removeClass('show_if_woosb').addClass('show_if_simple');
            $('#_regular_price').prop('readonly', false);
            $('.show_if_woosb').hide();
        }
    }

    function woosb_change_regular_price() {
        var total = 0;
        var total_max = 0;
        $('#woosb_selected li').each(function() {
            total += $(this).data('price') * $(this).find('input').val();
            total_max += $(this).data('price-max') * $(this).find('input').val();
        });
        total = accounting.formatMoney(total, '', woosb_vars.price_decimals, woosb_vars.price_thousand_separator, woosb_vars.price_decimal_separator);
        total_max = accounting.formatMoney(total_max, '', woosb_vars.price_decimals, woosb_vars.price_thousand_separator, woosb_vars.price_decimal_separator);
        if (total == total_max) {
            $('#woosb_regular_price').html(total);
        } else {
            $('#woosb_regular_price').html(total + ' - ' + total_max);
        }
        if (!$('#woosb_disable_auto_price').is(':checked')) {
            $('#_regular_price').val(total).trigger('change');
        }
    }

    function woosb_change_sale_price() {
        if ($('#woosb_price_percent').val() != '') {
            $('#_sale_price').prop('readonly', true);
            if ($('#_regular_price').val() != '') {
                var regular_price = accounting.unformat($('#_regular_price').val(), woosb_vars.price_decimal_separator);
                var sale_price = regular_price * $('#woosb_price_percent').val() * 0.01;
                $('#_sale_price').val(accounting.formatMoney(sale_price, '', woosb_vars.price_decimals, '', woosb_vars.price_decimal_separator));
            } else {
                $('#_sale_price').val('');
            }
        } else {
            $('#_sale_price').prop('readonly', false);
        }
    }

    function woosb_ajax_get_data() {
        // ajax search product
        woosbTimeout = null;
        var data = {
            action: 'woosb_get_search_results',
            woosb_keyword: $('#woosb_keyword').val(),
            woosb_ids: $('#woosb_ids').val(),
            woosb_nonce: woosb_vars.woosb_nonce
        };
        jQuery.post(ajaxurl, data, function(response) {
            $('#woosb_results').show();
            $('#woosb_results').html(response);
            $('#woosb_loading').hide();
        });
    }

    function get_woosb_ajax_img() {
        woosbTimeout = null;
        var data = {
            action: 'get_woosb_img',
            get_woosb_img_ids: $('#woosb_ids').val(),
        };
        jQuery.post(ajaxurl, data, function(response) {
            $('#img_woosb').html(response);
        });
    }

    function ajax_error_layout() {
        woosbTimeout = null;
        var layout_id = $('#input_layout_contain').val();
        var select_id = $('#woosb_ids').val();

        var data = {
            action: 'woosb_error_layout',
            error: [layout_id, select_id]
        };
        jQuery.post(ajaxurl, data, function(response) {
            /** Use for un wpbakery*/
            // if(response == '') {//enable
            // 	$("#publish").prop('disabled', false);
            // 	$('.layout_error').html('');
            // }else{//disable
            // 	$("#publish").prop('disabled',true);
            // 	$('.layout_error').html(response);
            // }
            /** Use for wpbakery*/
            if (response == 1) { //enable
                // $("#publish").prop('disabled', false);
                // $('.layout_error').html('');
            } else { //disable
                // $("#publish").prop('disabled',true);
                // $('.layout_error').html(response);
            }
        });
    }

});