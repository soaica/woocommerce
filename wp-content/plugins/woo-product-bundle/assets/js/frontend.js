'use strict';

jQuery( document ).ready( function( $ ){
	$('.woosb_wrap.woosb-wrap').show();
	$('#bundle_layout').show();
	if ( ! $( '.woosb-wrap' ).length ) {
		return;
	}

	var $woosb_wrap = $( '.woosb-wrap' ).eq( 0 );
	var $woosb_products = $( '.woosb-products' ).eq( 0 );
	var $woosb_ids = $( '.woosb-ids' ).eq( 0 );
	var $woosb_total = $( '.woosb-total' ).eq( 0 );
	var $woosb_btn = $woosb_wrap.closest( '.summary' ).find( '.single_add_to_cart_button' );
	if ( ! $woosb_btn.length ) {
		$woosb_btn = $woosb_wrap.closest( '.product' ).find( '.single_add_to_cart_button' );
	}
	if ( ! $woosb_btn.length ) {
		console.log( 'Have an issue with your template, so might WPC Product Bundles doesn\'t work completely. Please contact us via email contact@wpclever.net to get the help.' );
	}

	woosb_check_ready();

	$( document ).on( 'found_variation', function( e, t ) {
		var $woosb_product = $( e['target'] ).closest( '.woosb-product' );
		if ( t['image']['url'] && t['image']['srcset'] ) {
			// change image
			$woosb_product.find( '.woosb-thumb-ori' ).hide();
			$woosb_product.find( '.woosb-thumb-new' ).html( '<img src="' + t['image']['url'] + '" srcset="' + t['image']['srcset'] + '"/>' ).show();
		}
		if ( t['price_html'] ) {
			// change price
			$woosb_product.find( '.woosb-price-ori' ).hide();
			$woosb_product.find( '.woosb-price-new' ).html( t['price_html'] ).show();
		}
		if ( t['is_purchasable'] ) {
			// change stock notice
			if ( t['is_in_stock'] ) {
				$woosb_products.next( 'p.stock' ).show();
				$woosb_product.attr( 'data-id', t['variation_id'] );
				$woosb_product.attr( 'data-price', t['display_price'] );
			} else {
				$woosb_products.next( 'p.stock' ).hide();
				$woosb_product.attr( 'data-id', 0 );
				$woosb_product.attr( 'data-price', 0 );
			}

			// change availability text
			$( e['target'] ).closest( '.variations_form' ).find( 'p.stock' ).remove();
			if ( t['availability_html'] != '' ) {
				$( e['target'] ).closest( '.variations_form' ).append( t['availability_html'] );
			}
		}
		if ( t['variation_description'] != '' ) {
			$woosb_product.find( '.woosb-variation-description' ).html( t['variation_description'] ).show();
		} else {
			$woosb_product.find( '.woosb-variation-description' ).html( '' ).hide();
		}

		if ( woosb_vars.change_image == 'no' ) {
			// prevent changing the main image
			$( e['target'] ).closest( '.variations_form' ).trigger( 'reset_image' );
		}

		woosb_check_ready();
	} );

	$( document ).on( 'reset_data', function( e ) {
		var $woosb_product = $( e['target'] ).closest( '.woosb-product' );

		// reset thumb
		$woosb_product.find( '.woosb-thumb-new' ).hide();
		$woosb_product.find( '.woosb-thumb-ori' ).show();

		// reset price
		$woosb_product.find( '.woosb-price-new' ).hide();
		$woosb_product.find( '.woosb-price-ori' ).show();

		// reset stock
		$( e['target'] ).closest( '.variations_form' ).find( 'p.stock' ).remove();

		// reset desc
		$woosb_product.find( '.woosb-variation-description' ).html( '' ).hide();

		// reset id
		$woosb_product.attr( 'data-id', 0 );
		$woosb_product.attr( 'data-price', 0 );
		woosb_check_ready();
	} );

	$woosb_btn.on( 'click touch', function( e ) {
		if ( $( this ).hasClass( 'woosb-disabled' ) ) {
			if ( $( this ).hasClass( 'woosb-selection' ) ) {
				alert( woosb_vars.alert_selection );
			} else if ( $( this ).hasClass( 'woosb-empty' ) ) {
				alert( woosb_vars.alert_empty );
			} else if ( $( this ).hasClass( 'woosb-min' ) ) {
				alert( woosb_vars.alert_min.replace( '[min]', $woosb_products.attr( 'data-min' ) ) );
			} else if ( $( this ).hasClass( 'woosb-max' ) ) {
				alert( woosb_vars.alert_max.replace( '[max]', $woosb_products.attr( 'data-max' ) ) );
			}
			e.preventDefault();
		} else {
			jQuery.ajax( {
				url: woosb_vars.ajax_url,
				type: "POST",
				data: {
					action: 'woosb_custom_data',
					woosb_ids: $woosb_ids.val(),
					woosb_nonce: woosb_vars.woosb_nonce
				},
				async: false
			} );
		}
	} );

	$woosb_products.on( 'keyup change', '.woosb-qty input', function() {
		var qty = parseInt( $( this ).val() );
		var min_qty = parseInt( $( this ).attr( 'min' ) );
		var max_qty = parseInt( $( this ).attr( 'max' ) );
		if ( ! isNaN( min_qty ) && (
			qty < min_qty
		) ) {
			qty = min_qty;
		}
		if ( ! isNaN( max_qty ) && (
			qty > max_qty
		) ) {
			qty = max_qty;
		}
		$( this ).val( qty );
		$( this ).closest( '.woosb-product' ).attr( 'data-qty', qty );
		woosb_check_ready();
	} );

	function woosb_check_ready() {
		var total = 0;
		var is_selection = false;
		var is_empty = true;
		var is_min = false;
		var is_max = false;

		$woosb_products.find( '.woosb-product' ).each( function() {
			if ( (
				     $( this ).attr( 'data-qty' ) > 0
			     ) && (
				     $( this ).attr( 'data-id' ) == 0
			     ) ) {
				is_selection = true;
			}
			if ( $( this ).attr( 'data-qty' ) > 0 ) {
				is_empty = false;
				total += parseInt( $( this ).attr( 'data-qty' ) );
			}
		} );

		// check min
		if ( $woosb_products.attr( 'data-min' ) && (
			total < parseInt( $woosb_products.attr( 'data-min' ) )
		) ) {
			is_min = true;
		}

		// check max
		if ( $woosb_products.attr( 'data-max' ) && (
			total > parseInt( $woosb_products.attr( 'data-max' ) )
		) ) {
			is_max = true;
		}

		if ( is_selection || is_empty || is_min || is_max ) {
			$woosb_btn.addClass( 'woosb-disabled' );
			if ( is_selection ) {
				$woosb_btn.addClass( 'woosb-selection' );
			} else {
				$woosb_btn.removeClass( 'woosb-selection' );
			}
			if ( is_empty ) {
				$woosb_btn.addClass( 'woosb-empty' );
			} else {
				$woosb_btn.removeClass( 'woosb-empty' );
			}
			if ( is_min ) {
				$woosb_btn.addClass( 'woosb-min' );
			} else {
				$woosb_btn.removeClass( 'woosb-min' );
			}
			if ( is_max ) {
				$woosb_btn.addClass( 'woosb-max' );
			} else {
				$woosb_btn.removeClass( 'woosb-max' );
			}
		} else {
			$woosb_btn.removeClass( 'woosb-disabled woosb-selection woosb-empty woosb-min woosb-max' );
		}
		woosb_calc_price();
		woosb_save_ids();
	}

	function woosb_calc_price() {
		var total = 0;
		var total_html = '';
		$woosb_products.find( '.woosb-product' ).each( function() {
			if ( $( this ).attr( 'data-price' ) > 0 ) {
				total += $( this ).attr( 'data-price' ) * $( this ).attr( 'data-qty' );
			}
		} );
		if ( (
			     $woosb_products.attr( 'data-percent' ) > 0
		     ) && (
			     $woosb_products.attr( 'data-percent' ) < 100
		     ) ) {
			total = (
				        total * $woosb_products.attr( 'data-percent' )
			        ) / 100;
		} else if ( $woosb_products.attr( 'data-sale' ) > 0 ) {
			total = $woosb_products.attr( 'data-sale' );
		}
		var total_formatted = woosb_format_money( total, woosb_vars.price_decimals, '', woosb_vars.price_thousand_separator, woosb_vars.price_decimal_separator );
		switch ( woosb_vars.price_format ) {
			case '%1$s%2$s':
				//left
				total_html += woosb_vars.currency_symbol + '' + total_formatted;
				break;
			case '%1$s %2$s':
				//left with space
				total_html += woosb_vars.currency_symbol + ' ' + total_formatted;
				break;
			case '%2$s%1$s':
				//right
				total_html += total_formatted + '' + woosb_vars.currency_symbol;
				break;
			case '%2$s %1$s':
				//right with space
				total_html += total_formatted + ' ' + woosb_vars.currency_symbol;
				break;
			default:
				//default
				total_html += woosb_vars.currency_symbol + '' + total_formatted;
		}
		if ( (
			     parseFloat( $woosb_products.attr( 'data-percent' ) ) > 0
		     ) && (
			     parseFloat( $woosb_products.attr( 'data-percent' ) ) < 100
		     ) ) {
			var saved = woosb_round( parseFloat( 100 - $woosb_products.attr( 'data-percent' ) ) );
			total_html += ' (' + woosb_vars.price_saved + ' ' + saved + '%)';
		}
		$woosb_total.html( woosb_vars.bundle_price_text + ' ' + total_html ).slideDown();
		$( document ).trigger( 'woosb_calc_price', [total, total_formatted, total_html] );
	}

	function woosb_save_ids() {
		var woosb_ids = Array();
		$woosb_products.find( '.woosb-product' ).each( function() {
			if ( (
				     $( this ).attr( 'data-id' ) > 0
			     ) && (
				     $( this ).attr( 'data-qty' ) > 0
			     ) ) {
				woosb_ids.push( $( this ).attr( 'data-id' ) + '/' + $( this ).attr( 'data-qty' ) );
			}
		} );
		$woosb_ids.val( woosb_ids.join( ',' ) );
	}

	function woosb_round( num ) {
		return + (
			Math.round( num + "e+2" ) + "e-2"
		);
	}

	function woosb_format_money( number, places, symbol, thousand, decimal ) {
		number = number || 0;
		places = ! isNaN( places = Math.abs( places ) ) ? places : 2;
		symbol = symbol !== undefined ? symbol : "$";
		thousand = thousand || ",";
		decimal = decimal || ".";
		var negative = number < 0 ? "-" : "",
			i = parseInt( number = Math.abs( + number || 0 ).toFixed( places ), 10 ) + "",
			j = 0;
		if ( i.length > 3 ) {
			j = i.length % 3;
		}
		return symbol + negative + (
			j ? i.substr( 0, j ) + thousand : ""
		) + i.substr( j ).replace( /(\d{3})(?=\d)/g, "$1" + thousand ) + (
			       places ? decimal + Math.abs( number - i ).toFixed( places ).slice( 2 ) : ""
		       );
	}
} );