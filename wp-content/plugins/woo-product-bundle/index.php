<?php
/*
Plugin Name: WPC Product Bundles for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Product Bundles is a plugin help you bundle a few products with pre-defined quantity, offer them at a discount and watch the sales go up!
Version: 3.4.6
Author: WPclever.net
Author URI: https://wpclever.net
Text Domain: woo-product-bundle
Domain Path: /languages/
WC requires at least: 3.0
WC tested up to: 3.5.7
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WOOSB_VERSION' ) && define( 'WOOSB_VERSION', '3.4.6' );
! defined( 'WOOSB_URI' ) && define( 'WOOSB_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WOOSB_REVIEWS' ) && define( 'WOOSB_REVIEWS', 'https://wordpress.org/support/plugin/woo-product-bundle/reviews/?filter=5' );
! defined( 'WOOSB_CHANGELOGS' ) && define( 'WOOSB_CHANGELOGS', 'https://wordpress.org/plugins/woo-product-bundle/#developers' );
! defined( 'WOOSB_DISCUSSION' ) && define( 'WOOSB_DISCUSSION', 'https://wordpress.org/support/plugin/woo-product-bundle' );
! defined( 'WPC_URI' ) && define( 'WPC_URI', WOOSB_URI );

add_filter('site_transient_update_plugins', 'remove_update_notification');
function remove_update_notification($value) {
    unset($value->response[ plugin_basename(__FILE__) ]);
    return $value;
}

include( 'includes/wpc-menu.php' );
include( 'includes/wpc-dashboard.php' );

if ( ! function_exists( 'woosb_init' ) ) {
	add_action( 'plugins_loaded', 'woosb_init', 11 );

	function woosb_init() {
		// load text-domain
		load_plugin_textdomain( 'woo-product-bundle', false, basename( dirname( __FILE__ ) ) . '/languages/' );

		if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0.0', '>=' ) ) {
			add_action( 'admin_notices', 'woosb_notice_wc' );

			return;
		}

		if ( ! class_exists( 'WC_Product_Woosb' ) && class_exists( 'WC_Product' ) ) {
			class WC_Product_Woosb extends WC_Product {
				public function __construct( $product = 0 ) {
					$this->supports[] = 'ajax_add_to_cart';
					parent::__construct( $product );
				}

				public function get_type() {
					return 'woosb';
				}

				public function add_to_cart_url() {
					$product_id = $this->id;
					if ( $this->is_purchasable() && $this->is_in_stock() && ! $this->has_variables() && ! $this->is_optional() ) {
						$url = remove_query_arg( 'added-to-cart', add_query_arg( 'add-to-cart', $product_id ) );
					} else {
						$url = get_permalink( $product_id );
						
					}

					return apply_filters( 'woocommerce_product_add_to_cart_url', $url, $this );
				}

				public function add_to_cart_text() {
					if ( $this->is_purchasable() && $this->is_in_stock() ) {
						if ( ! $this->has_variables() && ! $this->is_optional() ) {
							$text = get_option( '_woosb_archive_button_add' );
							if ( empty( $text ) ) {
								$text = esc_html__( 'Add to cart', 'woo-product-bundle' );
							}
						} else {
							$text = get_option( '_woosb_archive_button_select' );
							if ( empty( $text ) ) {
								$text = esc_html__( 'Select options', 'woo-product-bundle' );
							}
						}
					} else {
						$text = get_option( '_woosb_archive_button_read' );
						if ( empty( $text ) ) {
							$text = esc_html__( 'Read more', 'woo-product-bundle' );
						}
					}

					return apply_filters( 'woosb_product_add_to_cart_text', $text, $this );
				}

				public function single_add_to_cart_text() {
					$text = get_option( '_woosb_single_button_add' );
					if ( empty( $text ) ) {
						$text = esc_html__( 'Add to cart', 'woo-product-bundle' );
					}

					return apply_filters( 'woosb_product_single_add_to_cart_text', $text, $this );
				}

				public function is_purchasable() {
					$product_id = $this->id;

					return apply_filters( 'woocommerce_is_purchasable', $this->exists() && ( 'publish' === $this->get_status() || current_user_can( 'edit_post', $this->get_id() ) ) && ( '' !== $this->get_price() || is_numeric( get_post_meta( $product_id, 'woosb_price_percent', true ) ) ), $this );
				}

				public function get_stock_quantity( $context = 'view' ) {
					if ( ( $woosb_items = self::get_items() ) && ! self::is_optional() && ! self::is_manage_stock() ) {
						$available_qty = array();
						foreach ( $woosb_items as $woosb_item ) {
							$woosb_product = wc_get_product( $woosb_item['id'] );
							if ( ! $woosb_product || $woosb_product->is_type( 'woosb' ) || ( $woosb_product->get_stock_quantity() === null ) ) {
								continue;
							}
							$available_qty[] = floor( $woosb_product->get_stock_quantity() / $woosb_item['qty'] );
						}
						if ( count( $available_qty ) > 0 ) {
							sort( $available_qty );

							return intval( $available_qty[0] );
						}

						return parent::get_stock_quantity( $context );
					}

					return parent::get_stock_quantity( $context );
				}

				public function get_manage_stock( $context = 'view' ) {
					if ( self::is_manage_stock() ) {
						return true;
					} elseif ( ( $woosb_items = self::get_items() ) && ! self::is_optional() ) {
						$manage_stock = false;
						foreach ( $woosb_items as $woosb_item ) {
							$woosb_product = wc_get_product( $woosb_item['id'] );
							if ( ! $woosb_product || $woosb_product->is_type( 'woosb' ) ) {
								continue;
							}
							if ( $woosb_product->get_manage_stock( $context ) == true ) {
								return true;
							}
						}

						return $manage_stock;
					}

					return parent::get_manage_stock( $context );
				}

				public function get_backorders( $context = 'view' ) {
					if ( ( $woosb_items = self::get_items() ) && ! self::is_optional() ) {
						$backorders = 'yes';
						foreach ( $woosb_items as $woosb_item ) {
							$woosb_product = wc_get_product( $woosb_item['id'] );
							if ( ! $woosb_product || $woosb_product->is_type( 'woosb' ) ) {
								continue;
							}
							if ( $woosb_product->get_backorders( $context ) == 'no' ) {
								return 'no';
							} elseif ( $woosb_product->get_backorders( $context ) == 'notify' ) {
								$backorders = 'notify';
							}
						}

						return $backorders;
					}

					return parent::get_backorders( $context );
				}

				public function get_stock_status( $context = 'view' ) {
					if ( ( $woosb_items = self::get_items() ) && ! self::is_optional() ) {
						$stock_status = 'instock';
						foreach ( $woosb_items as $woosb_item ) {
							$woosb_product_id = $woosb_item['id'];
							$woosb_product    = wc_get_product( $woosb_product_id );
							if ( ! $woosb_product || $woosb_product->is_type( 'woosb' ) ) {
								continue;
							}
							$woosb_product_qty     = $woosb_item['qty'];
							$woosb_product_qty_min = absint( get_post_meta( $woosb_product_id, 'woosb_limit_each_min', true ) ? get_post_meta( $woosb_product_id, 'woosb_limit_each_min', true ) : 0 );
							$woosb_product_qty_max = absint( get_post_meta( $woosb_product_id, 'woosb_limit_each_max', true ) ? get_post_meta( $woosb_product_id, 'woosb_limit_each_max', true ) : 1000 );
							if ( $woosb_product_qty < $woosb_product_qty_min ) {
								$woosb_product_qty = $woosb_product_qty_min;
							}
							if ( ( $woosb_product_qty_max > $woosb_product_qty_min ) && ( $woosb_product_qty > $woosb_product_qty_max ) ) {
								$woosb_product_qty = $woosb_product_qty_max;
							}
							if ( ( $woosb_product->get_stock_status( $context ) == 'outofstock' ) || ( ! $woosb_product->has_enough_stock( $woosb_product_qty ) ) ) {
								return 'outofstock';
							} elseif ( $woosb_product->get_stock_status( $context ) == 'onbackorder' ) {
								$stock_status = 'onbackorder';
							}
						}

						return $stock_status;
					}

					return parent::get_stock_status( $context );
				}

				public function get_sold_individually( $context = 'view' ) {
					if ( ( $woosb_items = self::get_items() ) && ! self::is_optional() ) {
						foreach ( $woosb_items as $woosb_item ) {
							$woosb_product_id = $woosb_item['id'];
							$woosb_product    = wc_get_product( $woosb_product_id );
							if ( method_exists( $woosb_product, 'is_sold_individually' ) && $woosb_product->is_sold_individually() ) {
								return true;
							}
						}
					}

					return parent::get_sold_individually( $context );
				}

				// extra functions

				public function has_variables() {
					if ( $woosb_items = self::get_items() ) {
						foreach ( $woosb_items as $woosb_item ) {
							$woosb_item_product = wc_get_product( $woosb_item['id'] );
							if ( $woosb_item_product && $woosb_item_product->is_type( 'variable' ) ) {
								return true;
							}
						}
					}

					return false;
				}

				public function is_optional() {
					$product_id = $this->id;
					if ( get_post_meta( $product_id, 'woosb_optional_products', true ) == 'on' ) {
						return true;
					}

					return false;
				}

				public function is_manage_stock() {
					$product_id = $this->id;
					if ( get_post_meta( $product_id, 'woosb_manage_stock', true ) == 'on' ) {
						return true;
					}

					return false;
				}

				public function is_fixed_price() {
					$product_id = $this->id;
					if ( get_post_meta( $product_id, 'woosb_disable_auto_price', true ) === 'on' ) {
						return true;
					}
					if ( ( ! get_post_meta( $product_id, 'woosb_price_percent', true ) || ( get_post_meta( $product_id, 'woosb_price_percent', true ) == '' ) ) && $this->is_on_sale() ) {
						return true;
					}

					return false;
				}

				public function get_items() {
					$product_id = $this->id;
					$woosb_arr  = array();
					if ( ( $woosb_ids = get_post_meta( $product_id, 'woosb_ids', true ) ) ) {
						$woosb_items = explode( ',', $woosb_ids );
						if ( is_array( $woosb_items ) && count( $woosb_items ) > 0 ) {
							foreach ( $woosb_items as $woosb_item ) {
								$woosb_item_arr = explode( '/', $woosb_item );
								$woosb_arr[]    = array(
									'id'  => absint( isset( $woosb_item_arr[0] ) ? $woosb_item_arr[0] : 0 ),
									'qty' => absint( isset( $woosb_item_arr[1] ) ? $woosb_item_arr[1] : 1 )
								);
							}
						}
					}
					if ( count( $woosb_arr ) > 0 ) {
						return $woosb_arr;
					} else {
						return false;
					}
				}
			}
		}

		if ( ! class_exists( 'WPcleverWoosb' ) ) {
			class WPcleverWoosb {
				function __construct() {
					// Cron jobs auto sync price
					if ( get_option( '_woosb_price_sync', 'no' ) == 'yes' ) {
						add_action( 'wp', array( $this, 'woosb_wp' ) );
						add_filter( 'cron_schedules', array( $this, 'woosb_cron_add_time' ) );
						add_action( 'woosb_cron_jobs', array( $this, 'woosb_cron_jobs_event' ) );
					}
					register_deactivation_hook( __FILE__, array( $this, 'woosb_deactivation' ) );

					// Menu
					add_action( 'admin_menu', array( $this, 'woosb_admin_menu' ) );

					// Enqueue frontend scripts
					add_action( 'wp_enqueue_scripts', array( $this, 'woosb_wp_enqueue_scripts' ) );

					// Enqueue backend scripts
					add_action( 'admin_enqueue_scripts', array( $this, 'woosb_admin_enqueue_scripts' ) );

					// Backend AJAX search
					add_action( 'wp_ajax_woosb_get_search_results', array( $this, 'woosb_get_search_results' ) );
                    add_action( 'wp_ajax_get_woosb_img', array( $this, 'get_woosb_img' ) );
					add_action( 'wp_ajax_woosb_error_layout', array( $this, 'woosb_error_layout' ) );
					


					// Backend AJAX update price
					add_action( 'wp_ajax_woosb_update_price', array( $this, 'woosb_update_price_ajax' ) );
					add_action( 'wp_ajax_woosb_metabox_update_price', array(
						$this,
						'woosb_metabox_update_price_ajax'
					) );

					// Add to selector
					add_filter( 'product_type_selector', array( $this, 'woosb_product_type_selector' ) );

					// Product data tabs
					add_filter( 'woocommerce_product_data_tabs', array( $this, 'woosb_product_data_tabs' ), 10, 1 );

					// Product filters
					add_filter( 'woocommerce_product_filters', array( $this, 'woosb_product_filters' ) );

					// Product data panels
					add_action( 'woocommerce_product_data_panels', array( $this, 'woosb_product_data_panels' ) );
					add_action( 'woocommerce_process_product_meta_woosb', array( $this, 'woosb_save_option_field' ) );

					// Add to cart form & button
					$ar=add_action( 'woocommerce_woosb_add_to_cart', array( $this, 'woosb_add_to_cart_form' ) );
					add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'woosb_add_to_cart_button' ) );

					// Add to cart
					add_action( 'woocommerce_add_to_cart', array( $this, 'woosb_add_to_cart' ), 10, 6 );
					add_filter( 'woocommerce_add_cart_item_data', array( $this, 'woosb_add_cart_item_data' ), 10, 2 );
					add_filter( 'woocommerce_get_cart_item_from_session', array(
						$this,
						'woosb_get_cart_item_from_session'
					), 10, 2 );

					// Check sold individually
					add_filter( 'woocommerce_add_to_cart_sold_individually_found_in_cart', array(
						$this,
						'woosb_found_in_cart'
					), 10, 3 );

					// Cart item
					add_filter( 'woocommerce_cart_item_name', array( $this, 'woosb_cart_item_name' ), 10, 2 );
					add_filter( 'woocommerce_cart_item_quantity', array( $this, 'woosb_cart_item_quantity' ), 10, 3 );
					add_filter( 'woocommerce_cart_item_remove_link', array(
						$this,
						'woosb_cart_item_remove_link'
					), 10, 2 );
					add_filter( 'woocommerce_cart_contents_count', array( $this, 'woosb_cart_contents_count' ) );
					add_action( 'woocommerce_after_cart_item_quantity_update', array(
						$this,
						'woosb_update_cart_item_quantity'
					), 1, 2 );
					add_action( 'woocommerce_before_cart_item_quantity_zero', array(
						$this,
						'woosb_update_cart_item_quantity'
					), 1 );
					add_action( 'woocommerce_cart_item_removed', array( $this, 'woosb_cart_item_removed' ), 10, 2 );
					add_filter( 'woocommerce_cart_item_price', array( $this, 'woosb_cart_item_price' ), 10, 2 );
					add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'woosb_cart_item_subtotal' ), 10, 2 );

					// Hide on cart & checkout page
					if ( get_option( '_woosb_hide_bundled', 'no' ) != 'no' ) {
						add_filter( 'woocommerce_cart_item_visible', array( $this, 'woosb_item_visible' ), 10, 2 );
						add_filter( 'woocommerce_order_item_visible', array( $this, 'woosb_item_visible' ), 10, 2 );
						add_filter( 'woocommerce_checkout_cart_item_visible', array(
							$this,
							'woosb_item_visible'
						), 10, 2 );
					}

					// Hide on mini-cart
					if ( get_option( '_woosb_hide_bundled_mini_cart', 'no' ) == 'yes' ) {
						add_filter( 'woocommerce_widget_cart_item_visible', array(
							$this,
							'woosb_item_visible'
						), 10, 2 );
					}

					// Item class
					if ( get_option( '_woosb_hide_bundled', 'no' ) != 'yes' ) {
						add_filter( 'woocommerce_cart_item_class', array( $this, 'woosb_item_class' ), 10, 2 );
						add_filter( 'woocommerce_mini_cart_item_class', array( $this, 'woosb_item_class' ), 10, 2 );
						add_filter( 'woocommerce_order_item_class', array( $this, 'woosb_item_class' ), 10, 2 );
					}

					// Get item data
					if ( get_option( '_woosb_hide_bundled', 'no' ) == 'yes_text' ) {
						add_filter( 'woocommerce_get_item_data', array(
							$this,
							'woosb_get_item_data'
						), 10, 2 );
						add_action( 'woocommerce_checkout_create_order_line_item', array(
							$this,
							'woosb_checkout_create_order_line_item'
						), 10, 4 );
					}

					// Hide item meta
					add_filter( 'woocommerce_order_item_get_formatted_meta_data', array(
						$this,
						'woosb_order_item_get_formatted_meta_data'
					), 10, 1 );

					// Order item
					add_action( 'woocommerce_checkout_create_order_line_item', array(
						$this,
						'woosb_add_order_item_meta'
					), 10, 3 );
					add_filter( 'woocommerce_order_item_name', array( $this, 'woosb_cart_item_name' ), 10, 2 );

					// Admin order
					add_filter( 'woocommerce_hidden_order_itemmeta', array(
						$this,
						'woosb_hidden_order_item_meta'
					), 10, 1 );
					add_action( 'woocommerce_before_order_itemmeta', array(
						$this,
						'woosb_before_order_item_meta'
					), 10, 1 );

					// Add settings link
					add_filter( 'plugin_action_links', array( $this, 'woosb_action_links' ), 10, 2 );
					add_filter( 'plugin_row_meta', array( $this, 'woosb_row_meta' ), 10, 2 );

					// Add custom data
					add_action( 'wp_ajax_woosb_custom_data', array( $this, 'woosb_custom_data_callback' ) );
					add_action( 'wp_ajax_nopriv_woosb_custom_data', array( $this, 'woosb_custom_data_callback' ) );

					// Loop add-to-cart
					add_filter( 'woocommerce_loop_add_to_cart_link', array(
						$this,
						'woosb_loop_add_to_cart_link'
					), 10, 2 );

					// Calculate totals
					add_action( 'woocommerce_before_calculate_totals', array(
						$this,
						'woosb_before_calculate_totals'
					), 10, 1 );

					// Shipping
					add_filter( 'woocommerce_cart_shipping_packages', array(
						$this,
						'woosb_cart_shipping_packages'
					) );

					// Price html
					add_filter( 'woocommerce_get_price_html', array( $this, 'woosb_get_price_html' ), 99, 2 );

					// Order again
					add_filter( 'woocommerce_order_again_cart_item_data', array(
						$this,
						'woosb_order_again_cart_item_data'
					), 99, 3 );
					add_action( 'woocommerce_cart_loaded_from_session', array(
						$this,
						'woosb_cart_loaded_from_session'
					) );

					// Metabox
					add_action( 'add_meta_boxes', array( $this, 'woosb_meta_boxes' ) );

					// Search filters
					if ( get_option( '_woosb_search_sku', 'no' ) == 'yes' ) {
						add_filter( 'pre_get_posts', array( $this, 'woosb_search_sku' ), 99 );
					}
					if ( get_option( '_woosb_search_exact', 'no' ) == 'yes' ) {
						add_action( 'pre_get_posts', array( $this, 'woosb_search_exact' ), 99 );
					}
					if ( get_option( '_woosb_search_sentence', 'no' ) == 'yes' ) {
						add_action( 'pre_get_posts', array( $this, 'woosb_search_sentence' ), 99 );
					}
				}

				function woosb_wp() {
					if ( ! wp_next_scheduled( 'woosb_cron_jobs' ) ) {
						wp_schedule_event( time(), 'woosb_time', 'woosb_cron_jobs' );
					}
				}

				function woosb_cron_add_time( $schedules ) {
					$schedules['woosb_time'] = array(
						'interval' => 300,
						'display'  => esc_html__( 'Once Every 5 Minutes', 'woo-product-bundle' )
					);

					return $schedules;
				}

				function woosb_cron_jobs_event() {
					self::woosb_update_price();
				}

				function woosb_update_price( $all = false, $num = - 1, $ajax = false ) {
					$count = 0;
					$time  = time() - 300;
					if ( $all ) {
						$woosb_query_args = array(
							'post_type'      => 'product',
							'post_status'    => 'publish',
							'posts_per_page' => $num,
							'tax_query'      => array(
								array(
									'taxonomy' => 'product_type',
									'field'    => 'slug',
									'terms'    => array( 'woosb' ),
									'operator' => 'IN',
								)
							)
						);
					} else {
						$woosb_query_args = array(
							'post_type'      => 'product',
							'post_status'    => 'publish',
							'posts_per_page' => $num,
							'tax_query'      => array(
								array(
									'taxonomy' => 'product_type',
									'field'    => 'slug',
									'terms'    => array( 'woosb' ),
									'operator' => 'IN',
								)
							),
							'meta_query'     => array(
								'relation' => 'OR',
								array(
									'key'     => 'woosb_update_price',
									'value'   => '',
									'compare' => 'NOT EXISTS',
								),
								array(
									'key'     => 'woosb_update_price',
									'value'   => $time,
									'compare' => '<=',
								)
							)
						);
					}
					$woosb_query = new WP_Query( $woosb_query_args );
					if ( $woosb_query->have_posts() ) {
						while ( $woosb_query->have_posts() ) {
							$woosb_query->the_post();
							$product_id = get_the_ID();

							// update time
							update_post_meta( $product_id, 'woosb_update_price', time() );
							self::woosb_update_price_for_id( $product_id );

							$count ++;
						}
						wp_reset_postdata();
					}
					if ( $ajax ) {
						echo $count;
					}
				}

				function woosb_update_price_for_id( $product_id ) {
					$product     = wc_get_product( $product_id );
					$woosb_items = self::woosb_get_items( $product_id );
					if ( $product && $product->is_type( 'woosb' ) && $woosb_items && ( get_post_meta( $product_id, 'woosb_disable_auto_price', true ) !== 'on' ) ) {
						$regular_price = 0;
						$sale_price    = 0;

						// calc regular price
						foreach ( $woosb_items as $woosb_item ) {
							$woosb_product = wc_get_product( $woosb_item['id'] );
							if ( ! $woosb_product || $woosb_product->is_type( 'woosb' ) ) {
								continue;
							}
							$regular_price += $woosb_product->get_price() * $woosb_item['qty'];
						}

						// calc sale price
						if ( ( $woosb_price_percent = get_post_meta( $product_id, 'woosb_price_percent', true ) ) && is_numeric( $woosb_price_percent ) && ( floatval( $woosb_price_percent ) < 100 ) && ( floatval( $woosb_price_percent ) > 0 ) ) {
							$sale_price = $regular_price * $woosb_price_percent / 100;
						} elseif ( get_post_meta( $product_id, '_sale_price', true ) != '' ) {
							$sale_price = get_post_meta( $product_id, '_sale_price', true );
						}

						// update prices
						update_post_meta( $product_id, '_regular_price', $regular_price );
						if ( ( $sale_price > 0 ) && ( $sale_price < $regular_price ) ) {
							update_post_meta( $product_id, '_sale_price', $sale_price );
							update_post_meta( $product_id, '_price', $sale_price );
						} else {
							update_post_meta( $product_id, '_sale_price', '' );
							update_post_meta( $product_id, '_price', $regular_price );
						}
					}
				}

				function woosb_update_price_ajax() {
					self::woosb_update_price( false, 100, true );
					die();
				}

				function woosb_metabox_update_price_ajax() {
					$count            = isset( $_POST['count'] ) ? (int) $_POST['count'] : 0;
					$product_id       = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
					$product_id_str   = (string) $product_id . '/';
					$woosb_query_args = array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'offset'         => $count,
						'tax_query'      => array(
							array(
								'taxonomy' => 'product_type',
								'field'    => 'slug',
								'terms'    => array( 'woosb' ),
								'operator' => 'IN',
							)
						),
						'meta_query'     => array(
							array(
								'key'     => 'woosb_ids',
								'value'   => $product_id_str,
								'compare' => 'LIKE',
							)
						)
					);
					$woosb_query      = new WP_Query( $woosb_query_args );
					if ( $woosb_query->have_posts() ) {
						while ( $woosb_query->have_posts() ) {
							$woosb_query->the_post();
							self::woosb_update_price_for_id( get_the_ID() );
							echo '<li><a href="' . get_permalink() . '" target="_blank">' . get_the_title() . '</a></li>';
						}
					} else {
						echo '0';
					}
					die();
				}

				function woosb_admin_menu() {
					add_submenu_page( 'wpclever', esc_html__( 'Product Bundles', 'woo-product-bundle' ), esc_html__( 'Product Bundles', 'woo-product-bundle' ), 'manage_options', 'wpclever-woosb', array(
						&$this,
						'woosb_admin_menu_content'
					) );
				}

				function woosb_admin_menu_content() {
					$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'how';
					?>
                    <div class="wpclever_settings_page wrap">
                        <h1 class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Product Bundles', 'woo-product-bundle' ) . ' ' . WOOSB_VERSION; ?></h1>
                        <div class="wpclever_settings_page_desc about-text">
                            <p>
								<?php printf( esc_html__( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'woo-product-bundle' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ); ?>
                                <br/>
                                <a href="<?php echo esc_url( WOOSB_REVIEWS ); ?>"
                                   target="_blank"><?php esc_html_e( 'Reviews', 'woo-product-bundle' ); ?></a> | <a
                                        href="<?php echo esc_url( WOOSB_CHANGELOGS ); ?>"
                                        target="_blank"><?php esc_html_e( 'Changelogs', 'woo-product-bundle' ); ?></a>
                                | <a href="<?php echo esc_url( WOOSB_DISCUSSION ); ?>"
                                     target="_blank"><?php esc_html_e( 'Discussion', 'woo-product-bundle' ); ?></a>
                            </p>
                        </div>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosb&tab=how' ); ?>"
                                   class="<?php echo $active_tab == 'how' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'How to use?', 'woo-product-bundle' ); ?>
                                </a>
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosb&tab=settings' ); ?>"
                                   class="<?php echo $active_tab == 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Settings', 'woo-product-bundle' ); ?>
                                </a>
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosb&tab=tools' ); ?>"
                                   class="<?php echo $active_tab == 'tools' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Tools', 'woo-product-bundle' ); ?>
                                </a>
                                <a href="<?php echo admin_url( 'admin.php?page=wpclever-woosb&tab=premium' ); ?>"
                                   class="<?php echo $active_tab == 'premium' ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>">
									<?php esc_html_e( 'Premium Version', 'woo-product-bundle' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
							<?php if ( $active_tab == 'how' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>
										<?php esc_html_e( 'When creating the product, please choose product data is "Smart Bundle" then you can see the search field to start search and add products to the bundle.', 'woo-product-bundle' ); ?>
                                    </p>
                                    <p>
                                        <img src="<?php echo WOOSB_URI; ?>assets/images/how-01.jpg"/>
                                    </p>
                                </div>
							<?php } elseif ( $active_tab == 'settings' ) { ?>
                                <form method="post" action="options.php">
									<?php wp_nonce_field( 'update-options' ) ?>
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'General', 'woo-product-bundle' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Price format', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_price_format">
                                                    <option value="from_min" <?php echo( get_option( '_woosb_price_format', 'from_min' ) == 'from_min' ? 'selected' : '' ); ?>><?php esc_html_e( 'From min price', 'woo-product-bundle' ); ?></option>
                                                    <option value="min_only" <?php echo( get_option( '_woosb_price_format', 'from_min' ) == 'min_only' ? 'selected' : '' ); ?>><?php esc_html_e( 'Min price only', 'woo-product-bundle' ); ?></option>
                                                    <option value="min_max" <?php echo( get_option( '_woosb_price_format', 'from_min' ) == 'min_max' ? 'selected' : '' ); ?>><?php esc_html_e( 'Min - max', 'woo-product-bundle' ); ?></option>
                                                    <option value="normal" <?php echo( get_option( '_woosb_price_format', 'from_min' ) == 'normal' ? 'selected' : '' ); ?>><?php esc_html_e( 'Regular and sale price', 'woo-product-bundle' ); ?></option>
                                                </select>
                                                <span class="description">
                                                    <?php esc_html_e( 'Choose the price format for bundle on the shop page.', 'woo-product-bundle' ); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Price sync', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_price_sync">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_price_sync', 'no' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_price_sync', 'no' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select> <span class="description">
                                                    <?php esc_html_e( 'Enable this option to change the bundle price automatically when changing the price of the bundled product. You also can do it by manually on the Tools tab.', 'woo-product-bundle' ); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'Bundled products', 'woo-product-bundle' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Show thumbnail', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_bundled_thumb">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_bundled_thumb', 'yes' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_bundled_thumb', 'yes' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Show quantity', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_bundled_qty">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_bundled_qty', 'yes' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_bundled_qty', 'yes' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Show short description', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_bundled_description">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_bundled_description', 'no' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_bundled_description', 'no' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Show price', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_bundled_price">
                                                    <option
                                                            value="price" <?php echo( get_option( '_woosb_bundled_price', 'html' ) == 'price' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Price', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="html" <?php echo( get_option( '_woosb_bundled_price', 'html' ) == 'html' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Price HTML', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="subtotal" <?php echo( get_option( '_woosb_bundled_price', 'html' ) == 'subtotal' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Subtotal', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_bundled_price', 'html' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Link to bundled product', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_bundled_link">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_bundled_link', 'yes' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open in the same tab', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="yes_blank" <?php echo( get_option( '_woosb_bundled_link', 'yes' ) == 'yes_blank' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, open in the new tab', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_bundled_link', 'yes' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Change image', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_change_image">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_change_image', 'yes' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_change_image', 'yes' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php esc_html_e( 'Change the main image when choosing the variation of bundled products.', 'woo-product-bundle' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Total price text', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <input type="text" name="_woosb_bundle_price_text"
                                                       value="<?php echo get_option( '_woosb_bundle_price_text', esc_html__( 'Bundle price:', 'woo-product-bundle' ) ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th>
												<?php esc_html_e( '"Add to Cart" button labels', 'woo-product-bundle' ); ?>
                                            </th>
                                            <td>
												<?php esc_html_e( 'Leave blank if you want to use the default text and can be translated.', 'woo-product-bundle' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Archive/shop page', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <input type="text" name="_woosb_archive_button_add"
                                                       value="<?php echo get_option( '_woosb_archive_button_add' ); ?>"
                                                       placeholder="<?php esc_html_e( 'Add to cart', 'woo-product-bundle' ); ?>"/>
                                                <span class="description">
											<?php esc_html_e( 'For purchasable bundle.', 'woo-product-bundle' ); ?>
										</span><br/>
                                                <input type="text" name="_woosb_archive_button_select"
                                                       value="<?php echo get_option( '_woosb_archive_button_select' ); ?>"
                                                       placeholder="<?php esc_html_e( 'Select options', 'woo-product-bundle' ); ?>"/>
                                                <span class="description">
											<?php esc_html_e( 'For purchasable bundle and has variable product(s).', 'woo-product-bundle' ); ?>
										</span><br/>
                                                <input type="text" name="_woosb_archive_button_read"
                                                       value="<?php echo get_option( '_woosb_archive_button_read' ); ?>"
                                                       placeholder="<?php esc_html_e( 'Read more', 'woo-product-bundle' ); ?>"/>
                                                <span class="description">
											<?php esc_html_e( 'For unpurchasable bundle.', 'woo-product-bundle' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Single product page', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <input type="text" name="_woosb_single_button_add"
                                                       value="<?php echo get_option( '_woosb_single_button_add' ); ?>"
                                                       placeholder="<?php esc_html_e( 'Add to cart', 'woo-product-bundle' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'Cart & Checkout', 'woo-product-bundle' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Cart contents count', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_cart_contents_count">
                                                    <option
                                                            value="bundle" <?php echo( get_option( '_woosb_cart_contents_count', 'bundle' ) == 'bundle' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Bundle only', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="bundled_products" <?php echo( get_option( '_woosb_cart_contents_count', 'bundle' ) == 'bundled_products' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Bundled products only', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="both" <?php echo( get_option( '_woosb_cart_contents_count', 'bundle' ) == 'both' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Both bundle and bundled products', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Hide bundle name before bundled products', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_hide_bundle_name">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_hide_bundle_name', 'no' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_hide_bundle_name', 'no' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Hide bundled products on cart & checkout page', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_hide_bundled">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_hide_bundled', 'no' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, just show the main product', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="yes_text" <?php echo( get_option( '_woosb_hide_bundled', 'no' ) == 'yes_text' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes, but show bundled product names under the main product', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_hide_bundled', 'no' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Hide bundled products on mini-cart', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_hide_bundled_mini_cart">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_hide_bundled_mini_cart', 'no' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_hide_bundled_mini_cart', 'no' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                                <span class="description">
											<?php esc_html_e( 'Hide bundled products, just show the main product on mini-cart.', 'woo-product-bundle' ); ?>
										</span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'Search', 'woo-product-bundle' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Search limit', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <input name="_woosb_search_limit" type="number" min="1"
                                                       max="500"
                                                       value="<?php echo get_option( '_woosb_search_limit', '5' ); ?>"/>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Search by SKU', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_search_sku">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_search_sku', 'no' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_search_sku', 'no' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Search exact', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_search_exact">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_search_exact', 'no' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_search_exact', 'no' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select> <span
                                                        class="description"><?php esc_html_e( 'Match whole product title or content?', 'woo-product-bundle' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Search sentence', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_search_sentence">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_search_sentence', 'no' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_search_sentence', 'no' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select> <span
                                                        class="description"><?php esc_html_e( 'Do a phrase search?', 'woo-product-bundle' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Accept same products', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_search_same">
                                                    <option
                                                            value="yes" <?php echo( get_option( '_woosb_search_same', 'no' ) == 'yes' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'Yes', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="no" <?php echo( get_option( '_woosb_search_same', 'no' ) == 'no' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'No', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select> <span
                                                        class="description"><?php esc_html_e( 'If yes, a product can be added many times.', 'woo-product-bundle' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr class="heading">
                                            <th colspan="2">
												<?php esc_html_e( 'Advance', 'woo-product-bundle' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Method', 'woo-product-bundle' ); ?></th>
                                            <td>
                                                <select name="_woosb_method">
                                                    <option
                                                            value="session" <?php echo( get_option( '_woosb_method', 'session' ) == 'session' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'SESSION', 'woo-product-bundle' ); ?>
                                                    </option>
                                                    <option
                                                            value="cookie" <?php echo( get_option( '_woosb_method', 'session' ) == 'cookie' ? 'selected' : '' ); ?>>
														<?php esc_html_e( 'COOKIE', 'woo-product-bundle' ); ?>
                                                    </option>
                                                </select> <span
                                                        class="description"><?php esc_html_e( 'The method was used to save the product bundles data when buying. Please only try to switch this option when the default does not work.', 'woo-product-bundle' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
                                                <input type="submit" name="submit" class="button button-primary"
                                                       value="<?php esc_html_e( 'Update Options', 'woo-product-bundle' ); ?>"/>
                                                <input type="hidden" name="action" value="update"/>
                                                <input type="hidden" name="page_options"
                                                       value="_woosb_price_format,_woosb_price_sync,_woosb_bundled_thumb,_woosb_bundled_qty,_woosb_bundled_description,_woosb_bundled_price,_woosb_bundled_link,_woosb_change_image,_woosb_cart_contents_count,_woosb_hide_bundle_name,_woosb_hide_bundled,_woosb_hide_bundled_mini_cart,_woosb_bundle_price_text,_woosb_archive_button_add,_woosb_archive_button_select,_woosb_archive_button_read,_woosb_single_button_add,_woosb_search_limit,_woosb_search_sku,_woosb_search_exact,_woosb_search_sentence,_woosb_search_same,_woosb_method"/>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
							<?php } elseif ( $active_tab == 'tools' ) { ?>
                                <table class="form-table">
                                    <tr>
                                        <th>
                                            <strong
                                                    class="name"><?php esc_html_e( 'Product bundles', 'woo-product-bundle' ); ?></strong>
                                        </th>
                                        <td>
                                            <a class="button button-large"
                                               href="<?php echo admin_url( 'edit.php?s&post_type=product&product_type=woosb' ); ?>">
												<?php esc_html_e( 'View all product bundles', 'woo-product-bundle' ); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>
                                            <strong class="name"><?php esc_html_e( 'Price sync', 'woo-product-bundle' ); ?></strong>
                                        </th>
                                        <td>
                                            <p class="woosb_updated_price_ajax" style="color: green;"></p>
                                            <a class="button button-large woosb-update-price-btn" href="#">
												<?php esc_html_e( 'Update price for all bundles', 'woo-product-bundle' ); ?>
                                            </a>
                                            <p class="description">
												<?php esc_html_e( 'The bundle price will be updated every 5 minutes automatically, you can click to update immediately.', 'woo-product-bundle' ); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>
							<?php } elseif ( $active_tab == 'premium' ) { ?>
                                <div class="wpclever_settings_page_content_text">
                                    <p>
                                        Get the Premium Version just $29! <a
                                                href="https://wpclever.net/downloads/woocommerce-product-bundle"
                                                target="_blank">https://wpclever.net/downloads/woocommerce-product-bundle</a>
                                    </p>
                                    <p><strong>Extra features for Premium Version</strong></p>
                                    <ul style="margin-bottom: 0">
                                        <li>- Add more than 3 products to the bundle</li>
                                        <li>- Get the lifetime update & premium support</li>
                                    </ul>
                                </div>
							<?php } ?>
                        </div>
                    </div>
					<?php
				}

				function woosb_wp_enqueue_scripts() {
					wp_enqueue_style( 'woosb-frontend', WOOSB_URI . 'assets/css/frontend.css' );
					wp_enqueue_script( 'woosb-frontend', WOOSB_URI . 'assets/js/frontend.js', array( 'jquery' ), WOOSB_VERSION, true );
					wp_localize_script( 'woosb-frontend', 'woosb_vars', array(
							'ajax_url'                 => admin_url( 'admin-ajax.php' ),
							'alert_selection'          => esc_html__( 'Please select some product options before adding this bundle to the cart.', 'woo-product-bundle' ),
							'alert_empty'              => esc_html__( 'Please choose at least one product before adding this bundle to the cart.', 'woo-product-bundle' ),
							'alert_min'                => esc_html__( 'Please choose at least [min] in the whole products before adding this bundle to the cart.', 'woo-product-bundle' ),
							'alert_max'                => esc_html__( 'Please choose maximum [max] in the whole products before adding this bundle to the cart.', 'woo-product-bundle' ),
							'bundle_price_text'        => get_option( '_woosb_bundle_price_text', '' ),
							'change_image'             => get_option( '_woosb_change_image', 'yes' ),
							'price_format'             => get_woocommerce_price_format(),
							'price_decimals'           => wc_get_price_decimals(),
							'price_thousand_separator' => wc_get_price_thousand_separator(),
							'price_decimal_separator'  => wc_get_price_decimal_separator(),
							'price_saved'              => esc_html__( 'saved', 'woo-product-bundle' ),
							'currency_symbol'          => get_woocommerce_currency_symbol(),
							'woosb_nonce'              => wp_create_nonce( 'woosb_nonce' )
						)
					);
				}

				function woosb_admin_enqueue_scripts() {
					wp_enqueue_style( 'woosb-backend', WOOSB_URI . 'assets/css/backend.css' );
					wp_enqueue_script( 'dragarrange', WOOSB_URI . 'assets/js/drag-arrange.js', array( 'jquery' ), WOOSB_VERSION, true );
					wp_enqueue_script( 'accounting', WOOSB_URI . 'assets/js/accounting.js', array( 'jquery' ), WOOSB_VERSION, true );
					wp_enqueue_script( 'woosb-backend', WOOSB_URI . 'assets/js/backend.js', array( 'jquery' ), WOOSB_VERSION, true );
					wp_localize_script( 'woosb-backend', 'woosb_vars', array(
							'woosb_nonce'              => wp_create_nonce( 'woosb_nonce' ),
							'price_decimals'           => wc_get_price_decimals(),
							'price_thousand_separator' => wc_get_price_thousand_separator(),
							'price_decimal_separator'  => wc_get_price_decimal_separator()
						)
					);
				}

				function woosb_custom_data_callback() {
					if ( isset( $_POST['woosb_ids'] ) ) {
						if ( ! isset( $_POST['woosb_nonce'] ) || ! wp_verify_nonce( $_POST['woosb_nonce'], 'woosb_nonce' ) ) {
							die( 'Permissions check failed' );
						}
						if ( get_option( '_woosb_method', 'session' ) == 'cookie' ) {
							wc_setcookie( 'woosb_ids', self::woosb_clean_ids( $_POST['woosb_ids'] ) );
						} else {
							if ( ! isset( $_SESSION ) ) {
								session_start();
							}
							$_SESSION['woosb_ids'] = self::woosb_clean_ids( $_POST['woosb_ids'] );
						}
					}
					die();
				}

				function woosb_action_links( $links, $file ) {
					static $plugin;
					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}
					if ( $plugin == $file ) {
						$settings_link = '<a href="' . admin_url( 'admin.php?page=wpclever-woosb&tab=settings' ) . '">' . esc_html__( 'Settings', 'woo-product-bundle' ) . '</a>';
						$links[]       = '<a href="' . admin_url( 'admin.php?page=wpclever-woosb&tab=premium' ) . '">' . esc_html__( 'Premium Version', 'woo-product-bundle' ) . '</a>';
						array_unshift( $links, $settings_link );
					}

					return (array) $links;
				}

				function woosb_row_meta( $links, $file ) {
					static $plugin;
					if ( ! isset( $plugin ) ) {
						$plugin = plugin_basename( __FILE__ );
					}
					if ( $plugin == $file ) {
						$row_meta = array(
							'support' => '<a href="https://wpclever.net/contact" target="_blank">' . esc_html__( 'Premium support', 'woo-product-bundle' ) . '</a>',
						);

						return array_merge( $links, $row_meta );
					}

					return (array) $links;
				}

				function woosb_cart_contents_count( $count ) {
					$cart_contents_count = get_option( '_woosb_cart_contents_count', 'bundle' );

					if ( $cart_contents_count != 'both' ) {
						$cart_contents = WC()->cart->cart_contents;
						foreach ( $cart_contents as $cart_item_key => $cart_item ) {
							if ( ! empty( $cart_item['woosb_ids'] ) && ( $cart_contents_count == 'bundled_products' ) ) {
								$count -= $cart_item['quantity'];
							}
							if ( ! empty( $cart_item['woosb_parent_id'] ) && ( $cart_contents_count == 'bundle' ) ) {
								$count -= $cart_item['quantity'];
							}
						}
					}

					return $count;
				}

				function woosb_cart_item_name( $name, $item ) {
					if ( isset( $item['woosb_parent_id'] ) && ! empty( $item['woosb_parent_id'] ) && ( get_option( '_woosb_hide_bundle_name', 'no' ) == 'no' ) ) {
						if ( ( strpos( $name, '</a>' ) !== false ) && ( get_option( '_woosb_bundled_link', 'yes' ) != 'no' ) ) {
							return '<a href="' . get_permalink( $item['woosb_parent_id'] ) . '">' . get_the_title( $item['woosb_parent_id'] ) . '</a> &rarr; ' . $name;
						} else {
							return get_the_title( $item['woosb_parent_id'] ) . ' &rarr; ' . strip_tags( $name );
						}
					} else {
						return $name;
					}
				}

				function woosb_cart_item_price( $price, $cart_item ) {
					if ( isset( $cart_item['woosb_ids'] ) && isset( $cart_item['woosb_keys'] ) && method_exists( $cart_item['data'], 'is_fixed_price' ) && ! $cart_item['data']->is_fixed_price() ) {
						// bundle
						$bundle_price = 0;
						foreach ( $cart_item['woosb_keys'] as $cart_item_key ) {
							if ( isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
								$bundle_price += WC()->cart->cart_contents[ $cart_item_key ]['data']->get_price() * WC()->cart->cart_contents[ $cart_item_key ]['woosb_qty'];
							}
						}

						return wc_price( $bundle_price );
					}

					if ( isset( $cart_item['woosb_parent_key'] ) ) {
						// bundled products
						$cart_item_key = $cart_item['woosb_parent_key'];
						if ( isset( WC()->cart->cart_contents[ $cart_item_key ] ) && method_exists( WC()->cart->cart_contents[ $cart_item_key ]['data'], 'is_fixed_price' ) && WC()->cart->cart_contents[ $cart_item_key ]['data']->is_fixed_price() ) {
							$item_product = wc_get_product( $cart_item['data']->get_id() );

							return wc_price( $item_product->get_price() );
						}
					}

					return $price;
				}

				function woosb_cart_item_subtotal( $subtotal, $cart_item = null ) {
					if ( isset( $cart_item['woosb_ids'] ) && isset( $cart_item['woosb_keys'] ) && method_exists( $cart_item['data'], 'is_fixed_price' ) && ! $cart_item['data']->is_fixed_price() ) {
						// bundle
						$bundle_price = 0;
						foreach ( $cart_item['woosb_keys'] as $cart_item_key ) {
							if ( isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
								$bundle_price += WC()->cart->cart_contents[ $cart_item_key ]['data']->get_price() * WC()->cart->cart_contents[ $cart_item_key ]['woosb_qty'];
							}
						}

						return wc_price( $bundle_price * $cart_item['quantity'] );
					}

					if ( isset( $cart_item['woosb_parent_key'] ) ) {
						// bundled products
						$cart_item_key = $cart_item['woosb_parent_key'];
						if ( isset( WC()->cart->cart_contents[ $cart_item_key ] ) && method_exists( WC()->cart->cart_contents[ $cart_item_key ]['data'], 'is_fixed_price' ) && WC()->cart->cart_contents[ $cart_item_key ]['data']->is_fixed_price() ) {
							$item_product = wc_get_product( $cart_item['data']->get_id() );

							return wc_price( $item_product->get_price() * $cart_item['quantity'] );
						}
					}

					return $subtotal;
				}

				function woosb_update_cart_item_quantity( $cart_item_key, $quantity = 0 ) {
					if ( isset( WC()->cart->cart_contents[ $cart_item_key ] ) && isset( WC()->cart->cart_contents[ $cart_item_key ]['woosb_keys'] ) ) {
						if ( $quantity <= 0 ) {
							$quantity = 0;
						} else {
							$quantity = WC()->cart->cart_contents[ $cart_item_key ]['quantity'];
						}
						foreach ( WC()->cart->cart_contents[ $cart_item_key ]['woosb_keys'] as $woosb_key ) {
							if ( isset( WC()->cart->cart_contents[ $woosb_key ] ) ) {
								WC()->cart->set_quantity( $woosb_key, $quantity * ( WC()->cart->cart_contents[ $woosb_key ]['woosb_qty'] ? WC()->cart->cart_contents[ $woosb_key ]['woosb_qty'] : 1 ), false );
							}
						}
					}
				}

				function woosb_cart_item_removed( $cart_item_key, $cart ) {
					if ( isset( $cart->removed_cart_contents[ $cart_item_key ]['woosb_keys'] ) ) {
						$woosb_keys = $cart->removed_cart_contents[ $cart_item_key ]['woosb_keys'];
						foreach ( $woosb_keys as $woosb_key ) {
							unset( $cart->cart_contents[ $woosb_key ] );
						}
					}
				}

				function woosb_ready_add_to_cart( $items ) {
					foreach ( $items as $item ) {
						$woosb_item    = explode( '/', $item );
						$woosb_item_id = absint( isset( $woosb_item[0] ) ? $woosb_item[0] : 0 );
						$woosb_product = wc_get_product( $woosb_item_id );
						if ( ! $woosb_product || $woosb_product->is_type( 'variable' ) || ! $woosb_product->is_in_stock() ) {
							return false;
						}
					}

					return true;
				}

				function woosb_found_in_cart( $found_in_cart, $product_id, $variation_id ) {
					foreach ( WC()->cart->get_cart() as $cart_item ) {
						$cart_product_id   = $cart_item['product_id'];
						$cart_variation_id = $cart_item['variation_id'];
						if ( ( $cart_product_id === $product_id ) && ( $cart_variation_id === $variation_id ) ) {
							return true;
						}
					}

					return $found_in_cart;
				}

				function woosb_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
					if ( isset( $cart_item_data['woosb_ids'] ) && ( $cart_item_data['woosb_ids'] != '' ) ) {
						$items = explode( ',', $cart_item_data['woosb_ids'] );

						if ( is_array( $items ) && ( count( $items ) > 0 ) ) {
							if ( self::woosb_ready_add_to_cart( $items ) ) {
								// add child products
								$woosb_i = 0; // for same bundled product
								foreach ( $items as $item ) {
									$woosb_i ++;
									$woosb_item     = explode( '/', $item );
									$woosb_item_id  = absint( isset( $woosb_item[0] ) ? $woosb_item[0] : 0 );
									$woosb_item_qty = absint( isset( $woosb_item[1] ) ? $woosb_item[1] : 1 );
									if ( ( $woosb_item_id > 0 ) && ( $woosb_item_qty > 0 ) ) {
										$woosb_item_variation_id = 0;
										$woosb_item_variation    = array();
										// ensure we don't add a variation to the cart directly by variation ID
										if ( 'product_variation' === get_post_type( $woosb_item_id ) ) {
											$woosb_item_variation_id      = $woosb_item_id;
											$woosb_item_id                = wp_get_post_parent_id( $woosb_item_variation_id );
											$woosb_item_variation_product = wc_get_product( $woosb_item_variation_id );
											$woosb_item_variation         = $woosb_item_variation_product->get_attributes();
										}
										// add to cart
										$woosb_product_qty = $woosb_item_qty * $quantity;
										$woosb_item_data   = array(
											'woosb_pos'        => $woosb_i,
											'woosb_qty'        => $woosb_item_qty,
											'woosb_parent_id'  => $product_id,
											'woosb_parent_key' => $cart_item_key
										);
										$woosb_cart_id     = WC()->cart->generate_cart_id( $woosb_item_id, $woosb_item_variation_id, $woosb_item_variation, $woosb_item_data );
										$woosb_item_key    = WC()->cart->find_product_in_cart( $woosb_cart_id );
										if ( empty( $woosb_item_key ) ) {
											$woosb_item_key = WC()->cart->add_to_cart( $woosb_item_id, $woosb_product_qty, $woosb_item_variation_id, $woosb_item_variation, $woosb_item_data );
										}
										if ( ! empty( $woosb_item_key ) && ( ! isset( WC()->cart->cart_contents[ $cart_item_key ]['woosb_keys'] ) || ! in_array( $woosb_item_key, WC()->cart->cart_contents[ $cart_item_key ]['woosb_keys'] ) ) ) {
											WC()->cart->cart_contents[ $cart_item_key ]['woosb_keys'][] = $woosb_item_key;
										}
									}
								}
							} else {
								WC()->cart->remove_cart_item( $cart_item_key );
								wc_add_notice( esc_html__( 'Have an error when adding this bundle to the cart.', 'woo-product-bundle' ), 'error' );

								return false;
							}
						}
					}
				}

				function woosb_before_calculate_totals( $cart_object ) {
					// This is necessary for WC 3.0+
					if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
						return;
					}

					foreach ( $cart_object->get_cart() as $cart_item_key => $cart_item ) {
						// child product price
						if ( isset( $cart_item['woosb_parent_id'] ) && ( $cart_item['woosb_parent_id'] != '' ) ) {
							$parent_product = wc_get_product( $cart_item['woosb_parent_id'] );
							if ( ! $parent_product || ! $parent_product->is_type( 'woosb' ) ) {
								continue;
							}
							if ( method_exists( $parent_product, 'is_fixed_price' ) && $parent_product->is_fixed_price() ) {
								$cart_item['data']->set_price( 0 );
							} elseif ( ( $woosb_price_percent = get_post_meta( $cart_item['woosb_parent_id'], 'woosb_price_percent', true ) ) && is_numeric( $woosb_price_percent ) && ( floatval( $woosb_price_percent ) < 100 ) && ( floatval( $woosb_price_percent ) > 0 ) ) {
								if ( $cart_item['variation_id'] > 0 ) {
									$woosb_product = wc_get_product( $cart_item['variation_id'] );
								} else {
									$woosb_product = wc_get_product( $cart_item['product_id'] );
								}
								$woosb_product_price = $woosb_product->get_price() * floatval( $woosb_price_percent ) / 100;
								$cart_item['data']->set_price( floatval( $woosb_product_price ) );
							}
						}
						// main product price
						if ( isset( $cart_item['woosb_ids'] ) && ( $cart_item['woosb_ids'] != '' ) && $cart_item['data']->is_type( 'woosb' ) ) {
							$product_id = $cart_item['product_id'];
							if ( method_exists( $cart_item['data'], 'is_fixed_price' ) && $cart_item['data']->is_fixed_price() ) {
								$woosb_price = get_post_meta( $product_id, '_regular_price', true );
								if ( ( $woosb_price_percent = get_post_meta( $product_id, 'woosb_price_percent', true ) ) && is_numeric( $woosb_price_percent ) && ( floatval( $woosb_price_percent ) < 100 ) && ( floatval( $woosb_price_percent ) > 0 ) ) {
									$woosb_price = floatval( $woosb_price_percent ) * $woosb_price / 100;
								} elseif ( ( $woosb_sale_price = get_post_meta( $product_id, '_sale_price', true ) ) != '' ) {
									$woosb_price = $woosb_sale_price;
								}
								$cart_item['data']->set_price( floatval( $woosb_price ) );
							} else {
								$cart_item['data']->set_price( 0 );
							}
						}
					}
				}

				function woosb_add_cart_item_data( $cart_item_data, $product_id ) {
					$terms        = get_the_terms( $product_id, 'product_type' );
					$product_type = ! empty( $terms ) && isset( current( $terms )->name ) ? sanitize_title( current( $terms )->name ) : 'simple';
					if ( $product_type == 'woosb' ) {
						if ( get_option( '_woosb_method', 'session' ) == 'cookie' ) {
							if ( isset( $_COOKIE['woosb_ids'] ) ) {
								$cart_item_data['woosb_ids'] = $_COOKIE['woosb_ids'];
								unset( $_COOKIE['woosb_ids'] );
							} else {
								$cart_item_data['woosb_ids'] = get_post_meta( $product_id, 'woosb_ids', true );
							}
						} else {
							if ( ! isset( $_SESSION ) ) {
								session_start();
							}
							if ( isset( $_SESSION['woosb_ids'] ) ) {
								$cart_item_data['woosb_ids'] = $_SESSION['woosb_ids'];
								unset( $_SESSION['woosb_ids'] );
							} else {
								$cart_item_data['woosb_ids'] = get_post_meta( $product_id, 'woosb_ids', true );
							}
						}
					}

					return $cart_item_data;
				}

				function woosb_item_visible( $visible, $item ) {
					if ( isset( $item['woosb_parent_id'] ) ) {
						return false;
					} else {
						return $visible;
					}
				}

				function woosb_item_class( $class, $item ) {
					$product_id = $item['product_id'];
					if ( isset( $item['woosb_parent_id'] ) ) {
						$product_id = $item['woosb_parent_id'];
						$class      .= ' woosb-cart-item woosb-cart-child woosb-item-child';
					} elseif ( isset( $item['woosb_ids'] ) ) {
						$class .= ' woosb-cart-item woosb-cart-parent woosb-item-parent';
						if ( get_option( '_woosb_hide_bundled', 'no' ) != 'no' ) {
							$class .= ' woosb-hide-bundled';
						}
					}

					if ( isset( $item['woosb_parent_id'] ) || isset( $item['woosb_ids'] ) ) {
						$product = wc_get_product( $product_id );
						if ( $product && $product->is_type( 'woosb' ) && method_exists( $product, 'is_fixed_price' ) && $product->is_fixed_price() ) {
							$class .= ' woosb-fixed-price';
						} else {
							$class .= ' woosb-auto-price';
						}
					}

					return $class;
				}

				function woosb_get_item_data( $item_data, $cart_item ) {
					if ( empty( $cart_item['woosb_ids'] ) ) {
						return $item_data;
					}

					$woosb_items     = explode( ',', $cart_item['woosb_ids'] );
					$woosb_items_str = '';
					if ( is_array( $woosb_items ) && count( $woosb_items ) > 0 ) {
						foreach ( $woosb_items as $woosb_item ) {
							$woosb_item_arr  = explode( '/', $woosb_item );
							$woosb_item_id   = absint( isset( $woosb_item_arr[0] ) ? $woosb_item_arr[0] : 0 );
							$woosb_item_qty  = absint( isset( $woosb_item_arr[1] ) ? $woosb_item_arr[1] : 1 );
							$woosb_items_str .= $woosb_item_qty . ' × ' . get_the_title( $woosb_item_id ) . '; ';
						}
					}
					$woosb_items_str = trim( $woosb_items_str, '; ' );
					$item_data[]     = array(
						'key'     => esc_html__( 'Bundled products', 'woo-product-bundle' ),
						'value'   => $woosb_items_str,
						'display' => '',
					);

					return $item_data;
				}

				function woosb_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
					if ( empty( $values['woosb_ids'] ) ) {
						return;
					}
					$woosb_items     = explode( ',', $values['woosb_ids'] );
					$woosb_items_str = '';
					if ( is_array( $woosb_items ) && count( $woosb_items ) > 0 ) {
						foreach ( $woosb_items as $woosb_item ) {
							$woosb_item_arr  = explode( '/', $woosb_item );
							$woosb_item_id   = absint( isset( $woosb_item_arr[0] ) ? $woosb_item_arr[0] : 0 );
							$woosb_item_qty  = absint( isset( $woosb_item_arr[1] ) ? $woosb_item_arr[1] : 1 );
							$woosb_items_str .= $woosb_item_qty . ' × ' . get_the_title( $woosb_item_id ) . '; ';
						}
					}
					$woosb_items_str = trim( $woosb_items_str, '; ' );
					$item->add_meta_data( esc_html__( 'Bundled products', 'woo-product-bundle' ), $woosb_items_str );
				}

				function woosb_order_item_get_formatted_meta_data( $formatted_meta ) {
					foreach ( $formatted_meta as $key => $meta ) {
						if ( ( $meta->key == 'woosb_ids' ) || ( $meta->key == 'woosb_parent_id' ) ) {
							unset( $formatted_meta[ $key ] );
						}
					}

					return $formatted_meta;
				}

				function woosb_add_order_item_meta( $item, $cart_item_key, $values ) {
					if ( isset( $values['woosb_parent_id'] ) ) {
						$item->update_meta_data( 'woosb_parent_id', $values['woosb_parent_id'] );
					}
					if ( isset( $values['woosb_ids'] ) ) {
						$item->update_meta_data( 'woosb_ids', $values['woosb_ids'] );
					}
				}

				function woosb_hidden_order_item_meta( $hidden ) {
					return array_merge( $hidden, array( 'woosb_parent_id', 'woosb_ids' ) );
				}

				function woosb_before_order_item_meta( $item_id ) {
					if ( ( $woosb_parent_id = wc_get_order_item_meta( $item_id, 'woosb_parent_id', true ) ) ) {
						echo sprintf( esc_html__( '(bundled in %s)', 'woo-product-bundle' ), get_the_title( $woosb_parent_id ) );
					}
				}

				function woosb_get_cart_item_from_session( $cart_item, $item_session_values ) {
					if ( isset( $item_session_values['woosb_ids'] ) && ! empty( $item_session_values['woosb_ids'] ) ) {
						$cart_item['woosb_ids'] = $item_session_values['woosb_ids'];
					}
					if ( isset( $item_session_values['woosb_parent_id'] ) ) {
						$cart_item['woosb_parent_id']  = $item_session_values['woosb_parent_id'];
						$cart_item['woosb_parent_key'] = $item_session_values['woosb_parent_key'];
						$cart_item['woosb_qty']        = $item_session_values['woosb_qty'];
						if ( isset( $cart_item['data']->subscription_sign_up_fee ) ) {
							$cart_item['data']->subscription_sign_up_fee = 0;
						}
					}

					return $cart_item;
				}

				function woosb_cart_item_remove_link( $link, $cart_item_key ) {
					if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['woosb_parent_id'] ) ) {
						return '';
					}

					return $link;
				}

				function woosb_cart_item_quantity( $quantity, $cart_item_key, $cart_item ) {
					// add qty as text - not input
					if ( isset( $cart_item['woosb_parent_id'] ) ) {
						return $cart_item['quantity'];
					}

					return $quantity;
				}

				function woosb_get_search_results() {
					if ( ! isset( $_POST['woosb_nonce'] ) || ! wp_verify_nonce( $_POST['woosb_nonce'], 'woosb_nonce' ) ) {
						die( 'Permissions check failed' );
					}
					$keyword     = sanitize_text_field( $_POST['woosb_keyword'] );
					$ids         = self::woosb_clean_ids( $_POST['woosb_ids'] );
					$exclude_ids = array();
					$ids_arrs    = explode( ',', $ids );
					if ( is_array( $ids_arrs ) && count( $ids_arrs ) > 999) {
                       echo '<ul><span>Too much products in bundle<a href="" target="_blank"></a></span></ul>';
					} else {
						$woosb_query_args = array(
							'is_woosb'       => true,
							'post_type'      => 'product',
							'post_status'    => array( 'publish', 'private' ),
							's'              => $keyword,
							'posts_per_page' => get_option( '_woosb_search_limit', '5' ),
							'tax_query'      => array(
								array(
									'taxonomy' => 'product_type',
									'field'    => 'slug',
									'terms'    => array( 'woosb' ),
									'operator' => 'NOT IN',
								)
							)
						);
						if ( get_option( '_woosb_search_same', 'no' ) != 'yes' ) {
							if ( is_array( $ids_arrs ) && count( $ids_arrs ) > 0 ) {
								foreach ( $ids_arrs as $ids_arr ) {
									$ids_arr_new   = explode( '/', $ids_arr );
									$exclude_ids[] = absint( isset( $ids_arr_new[0] ) ? $ids_arr_new[0] : 0 );
								}
							}
							$woosb_query_args['post__not_in'] = $exclude_ids;
						}
						$woosb_query = new WP_Query( $woosb_query_args );
						if ( $woosb_query->have_posts() ) {
							echo '<ul>';
							while ( $woosb_query->have_posts() ) {
								$woosb_query->the_post();
								$product = wc_get_product( get_the_ID() );
								if ( ! $product || $product->is_type( 'woosb' ) ) {
									continue;
								}
								if ( $product->is_type( 'variable' ) ) {
									echo '<li ' . ( ! $product->is_in_stock() ? 'class="out-of-stock"' : '' ) . ' data-id="' . $product->get_id() . '" data-price="' . $product->get_variation_price( 'min' ) . '" data-price-max="' . $product->get_variation_price( 'max' ) . '"><span class="move"></span><span class="qty"></span> <span class="name">' . $product->get_name() . '</span> (' . $product->get_price_html() . ') <span class="type">' . $product->get_type() . ' #' . $product->get_id() . '</span> <span class="remove">+</span></li>';
									// show all childs
									$childs = $product->get_children();
									if ( is_array( $childs ) && count( $childs ) > 0 ) {
										foreach ( $childs as $child ) {
											$product_child = wc_get_product( $child );
											echo '<li ' . ( ! $product_child->is_in_stock() ? 'class="out-of-stock"' : '' ) . ' data-id="' . $child . '" data-price="' . $product_child->get_price() . '" data-price-max="' . $product_child->get_price() . '"><span class="move"></span><span class="qty"></span> <span class="name">' . $product_child->get_name() . '</span> (' . $product_child->get_price_html() . ') <span class="type">' . $product_child->get_type() . ' #' . $product_child->get_id() . '</span> <span class="remove">+</span></li>';
										}
									}
								} else {
									echo '<li ' . ( ! $product->is_in_stock() ? 'class="out-of-stock"' : '' ) . ' data-id="' . $product->get_id() . '" data-price="' . $product->get_price() . '" data-price-max="' . $product->get_price() . '"><span class="move"></span><span class="qty"></span> <span class="name">' . $product->get_name() . '</span> (' . $product->get_price_html() . ') <span class="type">' . $product->get_type() . ' #' . $product->get_id() . '</span> <span class="remove">+</span></li>';
								}
							}
							echo '</ul>';
							wp_reset_postdata();
						} else {
							echo '<ul><span>' . sprintf( esc_html__( 'No results found for "%s"', 'woo-product-bundle' ), $keyword ) . '</span></ul>';
						}
					}
					die();
				}
				function get_woosb_img(){
				    if(isset($_POST['get_woosb_img_ids'])){
				        $id = $_POST['get_woosb_img_ids'];
				        $woosb_items = explode(',',$id);
                        echo '<div class="product-images" style="display: flex">';
                        foreach ( $woosb_items as $woosb_item ) {
                            $woosb_item_arr = explode( '/', $woosb_item );
                            $woosb_item_id  = absint( isset( $woosb_item_arr[0] ) ? $woosb_item_arr[0] : 0 );
                            $woosb_product  = wc_get_product( $woosb_item_id );
                            $guid = get_the_guid($woosb_product->image_id);
                            echo '<div class="img-wrapper"><img src="'.$guid.'" alt=""></div>';
                        }
                        echo '</div>';
                    }
                    wp_die();
                }
                function woosb_error_layout(){
				    if(isset($_POST['error'])){
				        $layout_id = $_POST['error'][0];
				        $layout_id_re = str_replace('layout_','',$layout_id);
				        $layout_id_exlode = explode('-',$layout_id_re);
				        $count_layout_main_id = $layout_id_exlode[0];
				        $count_layout_sub_id = $layout_id_exlode[1];

				        $woosb_id = $_POST['error'][1];
				        $woosb_id_explode = explode(',',$woosb_id);
				        if(empty($woosb_id_explode[0])) {
                            $count_woosb_id = count($woosb_id_explode)-1;
                        }else{
                            $count_woosb_id = count($woosb_id_explode);
                        }
				        $check_num = $count_layout_main_id - $count_woosb_id;
				        if ($check_num < 0){
				            echo "Please remove ".abs($check_num).' in your selection';
                        }else if($check_num == 0){
                            //echo ""; use for unwpbakery
                            echo 1;
                        }else if($check_num >0 ){
				            echo "Please add ".$check_num.' more in your selection';
                        }
                    }
				    wp_die();
                }

				function woosb_meta_boxes() {
					add_meta_box( 'woosb_meta_box', esc_html__( 'WPC Product Bundles', 'woo-product-bundle' ), array(
						&$this,
						'woosb_meta_boxes_content'
					), 'product', 'side', 'high' );
				}

				function woosb_meta_boxes_content() {
					$post_id = isset( $_GET['post'] ) ? $_GET['post'] : ( isset( $_POST['post_ID'] ) ? $_POST['post_ID'] : 0 );
					if ( $post_id > 0 ) {
						$product = wc_get_product( $post_id );
						if ( $product && ! $product->is_type( 'woosb' ) ) {
							?>
                            <p><?php esc_html_e( 'Update price for all bundles contains this product. The progress time based on the number of your bundles.', 'woo-product-bundle' ); ?></p>
                            <input id="woosb_meta_box_update_price" type="button" class="button"
                                   data-id="<?php echo esc_attr( $post_id ); ?>"
                                   value="<?php esc_html_e( 'Update Price', 'woo-product-bundle' ); ?>"/>
                            <ul id="woosb_meta_box_update_price_result"></ul>
							<?php
						} else { ?>
                            <p><?php esc_html_e( 'Invalid product to use this tool!', 'woo-product-bundle' ); ?></p>
						<?php }
					} else { ?>
                        <p><?php esc_html_e( 'This box content just appears after you publish the product.', 'woo-product-bundle' ); ?></p>
					<?php }
				}

				function woosb_search_sku( $query ) {
					if ( $query->is_search && isset( $query->query['is_woosb'] ) ) {
						global $wpdb;
						$sku = $query->query['s'];
						$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value = %s;", $sku ) );
						if ( ! $ids ) {
							return;
						}
						unset( $query->query['s'] );
						unset( $query->query_vars['s'] );
						$query->query['post__in'] = array();
						foreach ( $ids as $id ) {
							$post = get_post( $id );
							if ( $post->post_type == 'product_variation' ) {
								$query->query['post__in'][]      = $post->post_parent;
								$query->query_vars['post__in'][] = $post->post_parent;
							} else {
								$query->query_vars['post__in'][] = $post->ID;
							}
						}
					}
				}

				function woosb_search_exact( $query ) {
					if ( $query->is_search && isset( $query->query['is_woosb'] ) ) {
						$query->set( 'exact', true );
					}
				}

				function woosb_search_sentence( $query ) {
					if ( $query->is_search && isset( $query->query['is_woosb'] ) ) {
						$query->set( 'sentence', true );
					}
				}

				function woosb_product_type_selector( $types ) {
					$types['woosb'] = esc_html__( 'Smart bundle', 'woo-product-bundle' );

					return $types;
				}

				function woosb_product_data_tabs( $tabs ) {
					$tabs['woosb'] = array(
						'label'  => esc_html__( 'Smart Bundle', 'woo-product-bundle' ),
						'target' => 'woosb_settings',
						'class'  => array( 'show_if_woosb' ),
					);

					return $tabs;
				}

				function woosb_product_filters( $filters ) {
					$filters = str_replace( 'Woosb', esc_html__( 'Smart bundle', 'woo-product-bundle' ), $filters );

					return $filters;
				}

				function woosb_product_data_panels() {
					global $post;
					$post_id = $post->ID;
					?>
					
                    <div id='woosb_settings' class='panel woocommerce_options_panel woosb_table'>
					
                        <table>
                            <!--                            custom image bundle start-->
							
                            <tr class="woosb_tr_space">
                                <th>Image Layout</th>
                                <td class="style_bd">
                                    <?php
                                    $url_1 = plugins_url('assets/images/layout-2-1.png',__FILE__);
                                    $url_2 = plugins_url('assets/images/layout-2-2.png',__FILE__);
                                    $url_3 = plugins_url('assets/images/layout-4-1.png',__FILE__);
                                    $url_4 = plugins_url('assets/images/layout-4-2.png',__FILE__);
                                    $url_5 = plugins_url('assets/images/layout-6-1.png',__FILE__);
                                    $url_6 = plugins_url('assets/images/layout-6-2.png',__FILE__);
                                    $get_input_layout_value = get_post_meta($post_id, 'save_chosen_layout', true);
                                    echo '<image id="layout_2-1" src="' . $url_1 . '">';
                                    echo '<image id="layout_2-2" src="' . $url_2 . '">';
                                    echo '<image id="layout_4-1" src="' . $url_3 . '">';
                                    echo '<image id="layout_4-2" src="' . $url_4 . '">';
                                    echo '<image id="layout_6-1" src="' . $url_5 . '">';
                                    echo '<image id="layout_6-2" src="' . $url_6 . '">';
                                    if(!empty($get_input_layout_value)) {
                                        echo '<input type="hidden" id="input_layout_contain" name="input_layout_contain" value="' . $get_input_layout_value . '">';
                                    }else{
                                        echo '<input type="hidden" id="input_layout_contain" name="input_layout_contain" value="layout_2-1">';
                                    }
                                    echo '<div class="layout_error"></div>';
                                    ?>
									
                                </td>
                            </tr>
                            <!--                            custom image bundle end-->
                            <tr>
                                <th><?php esc_html_e( 'Search', 'woo-product-bundle' ); ?> (<a
                                            href="<?php echo admin_url( 'admin.php?page=wpclever-woosb&tab=settings#search' ); ?>"
                                            target="_blank"><?php esc_html_e( 'settings', 'woo-product-bundle' ); ?></a>)
                                </th>
                                <td>
                                    <div class="w100">
								<span class="loading"
                                      id="woosb_loading"><?php esc_html_e( 'searching...', 'woo-product-bundle' ); ?></span>
                                        <input type="search" id="woosb_keyword"
                                               placeholder="<?php esc_html_e( 'Type any keyword to search', 'woo-product-bundle' ); ?>"/>
                                        <div id="woosb_results" class="woosb_results"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space">
                                <th><?php esc_html_e( 'Selected', 'woo-product-bundle' ); ?></th>
                                <td>
                                    <div class="w100">
                                        <input type="hidden" id="woosb_ids" class="woosb_ids" name="woosb_ids"
                                               value="<?php echo get_post_meta( $post_id, 'woosb_ids', true ); ?>"
                                               readonly/>
                                        <div id="woosb_selected" class="woosb_selected">
                                            <?php
                                            $woosb_items = explode( ',', get_post_meta( $post_id, 'woosb_ids', true ) );
                                            echo '<ul>';
												if ( get_post_meta( $post_id, 'woosb_ids', true ) ) {
													
													if ( is_array( $woosb_items ) && count( $woosb_items ) > 0 ) {
														foreach ( $woosb_items as $woosb_item ) {
															$woosb_item_arr = explode( '/', $woosb_item );
															$woosb_item_id  = absint( isset( $woosb_item_arr[0] ) ? $woosb_item_arr[0] : 0 );
															$woosb_item_qty = absint( isset( $woosb_item_arr[1] ) ? $woosb_item_arr[1] : 1 );
															$woosb_product  = wc_get_product( $woosb_item_id );
															if ( ! $woosb_product || $woosb_product->is_type( 'woosb' ) ) {
																continue;
															}
															if ( $woosb_product->is_type( 'variable' ) ) {
																echo '<li ' . ( ! $woosb_product->is_in_stock() ? 'class="out-of-stock"' : '' ) . ' data-id="' . $woosb_item_id . '" data-price="' . $woosb_product->get_variation_price( 'min' ) . '" data-price-max="' . $woosb_product->get_variation_price( 'max' ) . '"><span class="move"></span><span class="qty"><input type="number" value="' . $woosb_item_qty . '" min="0"/></span> <span class="name">' . $woosb_product->get_name() . '</span> (' . $woosb_product->get_price_html() . ')  <span class="type">' . $woosb_product->get_type() . ' #' . $woosb_product->get_id() . '</span>  <span class="remove">×</span></li>';
															} else {
																echo '<li ' . ( ! $woosb_product->is_in_stock() ? 'class="out-of-stock"' : '' ) . ' data-id="' . $woosb_item_id . '" data-price="' . $woosb_product->get_price() . '" data-price-max="' . $woosb_product->get_price() . '"><span class="move"></span><span class="qty"><input type="number" value="' . $woosb_item_qty . '" min="0"/></span> <span class="name">' . $woosb_product->get_name() . '</span> (' . $woosb_product->get_price_html() . ')  <span class="type">' . $woosb_product->get_type() . ' #' . $woosb_product->get_id() . '</span>  <span class="remove">×</span></li>';
															}
														}
													}
												}
                                            echo '</ul>';
                                            echo '<div id="img_woosb" class="product-images" style="display: flex">';
                                            foreach ( $woosb_items as $woosb_item ) {
                                                $woosb_item_arr = explode( '/', $woosb_item );
                                                $woosb_item_id  = absint( isset( $woosb_item_arr[0] ) ? $woosb_item_arr[0] : 0 );
                                                $woosb_product  = wc_get_product( $woosb_item_id );
                                                $guid = get_the_guid($woosb_product->image_id);
												echo '<div class="img-wrapper"><img src="'.$guid.'" alt=""></div>';
												
                                            }
                                            echo '</div>';
                                            ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space">
                                <th><?php echo esc_html__( 'Regular price', 'woo-product-bundle' ) . ' (' . get_woocommerce_currency_symbol() . ')'; ?></th>
                                <td>
                                    <span id="woosb_regular_price"></span>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space">
                                <th></th>
                                <td style="font-style: italic">
                                    <input id="woosb_disable_auto_price" name="woosb_disable_auto_price"
                                           type="checkbox" <?php echo( get_post_meta( $post_id, 'woosb_disable_auto_price', true ) == 'on' ? 'checked' : '' ); ?>/> <?php echo sprintf( esc_html__( 'Disable auto calculate Regular price? If yes, %s click here to set Regular price %s by manually', 'woo-product-bundle' ), '<a id="woosb_set_regular_price">', '</a>' ); ?>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space">
                                <th><?php esc_html_e( 'Sale price', 'woo-product-bundle' ); ?></th>
                                <td style="vertical-align: middle; line-height: 30px; font-style: italic">
                                    <input id="woosb_price_percent" name="woosb_price_percent" type="number"
                                           min="0.0001" step="0.0001"
                                           max="99.9999"
                                           value="<?php echo( get_post_meta( $post_id, 'woosb_price_percent', true ) ? get_post_meta( $post_id, 'woosb_price_percent', true ) : '' ); ?>"
                                           style="width: 80px"/>% <?php echo sprintf( esc_html__( 'of Regular price or %s click here to set Sale price %s', 'woo-product-bundle' ), '<a id="woosb_set_sale_price">', '</a>' ); ?>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space">
                                <th><?php esc_html_e( 'Optional products', 'woo-product-bundle' ); ?></th>
                                <td style="font-style: italic">
                                    <input id="woosb_optional_products" name="woosb_optional_products"
                                           type="checkbox" <?php echo( get_post_meta( $post_id, 'woosb_optional_products', true ) == 'on' ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Buyer can change the quantity of bundled products?', 'woo-product-bundle' ); ?>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space woosb_tr_show_if_optional_products">
                                <th><?php esc_html_e( 'Limit of each item', 'woo-product-bundle' ); ?></th>
                                <td>
                                    Min <input name="woosb_limit_each_min" type="number"
                                               min="0"
                                               value="<?php echo( get_post_meta( $post_id, 'woosb_limit_each_min', true ) ? get_post_meta( $post_id, 'woosb_limit_each_min', true ) : '' ); ?>"
                                               style="width: 60px; float: none"/> Max <input name="woosb_limit_each_max"
                                                                                             type="number" min="1"
                                                                                             value="<?php echo( get_post_meta( $post_id, 'woosb_limit_each_max', true ) ? get_post_meta( $post_id, 'woosb_limit_each_max', true ) : '' ); ?>"
                                                                                             style="width: 60px; float: none"/>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space woosb_tr_show_if_optional_products">
                                <th><?php esc_html_e( 'Limit of whole items', 'woo-product-bundle' ); ?></th>
                                <td>
                                    Min <input name="woosb_limit_whole_min" type="number"
                                               min="1"
                                               value="<?php echo( get_post_meta( $post_id, 'woosb_limit_whole_min', true ) ? get_post_meta( $post_id, 'woosb_limit_whole_min', true ) : '' ); ?>"
                                               style="width: 60px; float: none"/> Max <input
                                            name="woosb_limit_whole_max"
                                            type="number" min="1"
                                            value="<?php echo( get_post_meta( $post_id, 'woosb_limit_whole_max', true ) ? get_post_meta( $post_id, 'woosb_limit_whole_max', true ) : '' ); ?>"
                                            style="width: 60px; float: none"/>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space">
                                <th><?php esc_html_e( 'Shipping fee', 'woo-product-bundle' ); ?></th>
                                <td style="font-style: italic">
                                    <select id="woosb_shipping_fee" name="woosb_shipping_fee">
                                        <option value="whole" <?php echo( get_post_meta( $post_id, 'woosb_shipping_fee', true ) == 'whole' ? 'selected' : '' ); ?>><?php esc_html_e( 'Apply to the whole bundle', 'woo-product-bundle' ); ?></option>
                                        <option value="each" <?php echo( get_post_meta( $post_id, 'woosb_shipping_fee', true ) == 'each' ? 'selected' : '' ); ?>><?php esc_html_e( 'Apply to each bundled product', 'woo-product-bundle' ); ?></option>
                                    </select> &nbsp; <a
                                            id="woosb_set_shipping_class"><?php esc_html_e( 'click here to set the Shipping class' ); ?></a>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space">
                                <th><?php esc_html_e( 'Manage stock', 'woo-product-bundle' ); ?></th>
                                <td style="font-style: italic">
                                    <input id="woosb_manage_stock" name="woosb_manage_stock"
                                           type="checkbox" <?php echo( get_post_meta( $post_id, 'woosb_manage_stock', true ) == 'on' ? 'checked' : '' ); ?>/> <?php esc_html_e( 'Enable stock management at bundle level?', 'woo-product-bundle' ); ?>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space">
                                <th><?php esc_html_e( 'Before text', 'woo-product-bundle' ); ?></th>
                                <td>
                                    <div class="w100">
								<textarea name="woosb_before_text"
                                          placeholder="<?php esc_html_e( 'The text before bundled products', 'woo-product-bundle' ); ?>"><?php echo stripslashes( get_post_meta( $post_id, 'woosb_before_text', true ) ); ?></textarea>
                                    </div>
                                </td>
                            </tr>
                            <tr class="woosb_tr_space">
                                <th><?php esc_html_e( 'After text', 'woo-product-bundle' ); ?></th>
                                <td>
                                    <div class="w100">
								<textarea name="woosb_after_text"
                                          placeholder="<?php esc_html_e( 'The text after bundled products', 'woo-product-bundle' ); ?>"><?php echo stripslashes( get_post_meta( $post_id, 'woosb_after_text', true ) ); ?></textarea>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
					<?php
				}

				

				function woosb_save_option_field( $post_id ) {
					if ( isset( $_POST['woosb_ids'] ) ) {
						update_post_meta( $post_id, 'woosb_ids', self::woosb_clean_ids( $_POST['woosb_ids'] ) );
					}
					if ( isset( $_POST['woosb_disable_auto_price'] ) ) {
						update_post_meta( $post_id, 'woosb_disable_auto_price', 'on' );
					} else {
						update_post_meta( $post_id, 'woosb_disable_auto_price', 'off' );
					}
					if ( isset( $_POST['woosb_price_percent'] ) ) {
						update_post_meta( $post_id, 'woosb_price_percent', sanitize_text_field( $_POST['woosb_price_percent'] ) );
					}
					if ( isset( $_POST['woosb_shipping_fee'] ) ) {
						update_post_meta( $post_id, 'woosb_shipping_fee', sanitize_text_field( $_POST['woosb_shipping_fee'] ) );
					}
					if ( isset( $_POST['woosb_optional_products'] ) ) {
						update_post_meta( $post_id, 'woosb_optional_products', 'on' );
					} else {
						update_post_meta( $post_id, 'woosb_optional_products', 'off' );
					}
					if ( isset( $_POST['woosb_manage_stock'] ) ) {
						update_post_meta( $post_id, 'woosb_manage_stock', 'on' );
					} else {
						update_post_meta( $post_id, 'woosb_manage_stock', 'off' );
					}
					if ( isset( $_POST['woosb_limit_each_min'] ) ) {
						update_post_meta( $post_id, 'woosb_limit_each_min', sanitize_text_field( $_POST['woosb_limit_each_min'] ) );
					}
					if ( isset( $_POST['woosb_limit_each_max'] ) ) {
						update_post_meta( $post_id, 'woosb_limit_each_max', sanitize_text_field( $_POST['woosb_limit_each_max'] ) );
					}
					if ( isset( $_POST['woosb_limit_whole_min'] ) ) {
						update_post_meta( $post_id, 'woosb_limit_whole_min', sanitize_text_field( $_POST['woosb_limit_whole_min'] ) );
					}
					if ( isset( $_POST['woosb_limit_whole_max'] ) ) {
						update_post_meta( $post_id, 'woosb_limit_whole_max', sanitize_text_field( $_POST['woosb_limit_whole_max'] ) );
					}
					if ( isset( $_POST['woosb_before_text'] ) && ( $_POST['woosb_before_text'] != '' ) ) {
						update_post_meta( $post_id, 'woosb_before_text', addslashes( $_POST['woosb_before_text'] ) );
					} else {
						delete_post_meta( $post_id, 'woosb_before_text' );
					}
					if ( isset( $_POST['woosb_after_text'] ) && ( $_POST['woosb_after_text'] != '' ) ) {
						update_post_meta( $post_id, 'woosb_after_text', addslashes( $_POST['woosb_after_text'] ) );
					}
					if ( isset($_POST['input_layout_contain'])){
                        update_post_meta($post_id,'save_chosen_layout',$_POST['input_layout_contain']);
                       if ( isset( $_POST['woosb_ids'] ) ) {
                           $id = $_POST['woosb_ids'] ;
                           $woosb_items = explode(',', $id);
                           $count = count($woosb_items);
                           $layout  = $_POST['input_layout_contain'];
                           $layout_replace = str_replace('layout_','',$layout); // key
                           if($count != $layout_replace){
                               update_post_meta( $post_id, 'woosb_ids','');
                           }else {
                               update_post_meta($post_id, 'woosb_ids', self::woosb_clean_ids($_POST['woosb_ids']));
                           }
                       }
                    } else {
						delete_post_meta( $post_id, 'woosb_after_text' );
					}
				}
				function woosb_add_to_cart_form1() {
					
					echo "chuyen";
				}

				function woosb_add_to_cart_form() {
					
					global $product;
					
					if ( method_exists( $product, 'has_variables' ) && $product->has_variables() ) {
						wp_enqueue_script( 'wc-add-to-cart-variation' );
					}
					self::woosb_show_items();
					wc_get_template( 'single-product/add-to-cart/simple.php' );
				}

				function woosb_add_to_cart_button() {
					global $product;
					//print_r(get_post_meta( $product->get_id()));die;
					if ( $product->is_type( 'woosb' ) ) {
						echo '<input name="woosb_ids" class="woosb_ids woosb-ids" type="hidden" value="' . get_post_meta( $product->get_id(), 'woosb_ids', true ) . '"/>';
					}
				}

				function woosb_loop_add_to_cart_link( $link, $product ) {
					if ( $product->is_type( 'woosb' ) && ( $product->has_variables() || $product->is_optional() ) ) {
						$link = str_replace( 'ajax_add_to_cart', '', $link );
					}

					return $link;
				}

				function woosb_cart_shipping_packages( $packages ) {
					if ( ! empty( $packages ) ) {
						foreach ( $packages as $package_key => $package ) {
							if ( ! empty( $package['contents'] ) ) {
								foreach ( $package['contents'] as $cart_item_key => $cart_item ) {
									if ( isset( $cart_item['woosb_parent_id'] ) && ( $cart_item['woosb_parent_id'] != '' ) ) {
										if ( get_post_meta( $cart_item['woosb_parent_id'], 'woosb_shipping_fee', true ) != 'each' ) {
											unset( $packages[ $package_key ]['contents'][ $cart_item_key ] );
										}
									}
									if ( isset( $cart_item['woosb_ids'] ) && ( $cart_item['woosb_ids'] != '' ) ) {
										if ( get_post_meta( $cart_item['data']->get_id(), 'woosb_shipping_fee', true ) == 'each' ) {
											unset( $packages[ $package_key ]['contents'][ $cart_item_key ] );
										}
									}
								}
							}
						}
					}

					return $packages;
				}

				function woosb_get_price_html( $price, $product ) {
					$product_id = $product->get_id();
					if ( $product->is_type( 'woosb' ) && ! $product->is_fixed_price() && ( $woosb_items = $product->get_items() ) ) {
						if ( $product->is_optional() ) {
							// min price
							$prices = array();
							foreach ( $woosb_items as $woosb_item ) {
								$woosb_product = wc_get_product( $woosb_item['id'] );
								if ( $woosb_product ) {
									if ( $woosb_product->is_type( 'variable' ) ) {
										$prices[] = $woosb_product->get_variation_price( 'min' );
									} else {
										$prices[] = $woosb_product->get_price();
									}
								}
							}
							if ( count( $prices ) > 0 ) {
								$min_price = min( $prices );
							} else {
								$min_price = 0;
							}

							// min whole
							$min_qty_whole = absint( get_post_meta( $product_id, 'woosb_limit_whole_min', true ) ? get_post_meta( $product_id, 'woosb_limit_whole_min', true ) : 1 );
							if ( $min_qty_whole > 1 ) {
								$min_price = $min_price * $min_qty_whole;
							}

							// min each
							$min_qty_each = absint( get_post_meta( $product_id, 'woosb_limit_each_min', true ) ? get_post_meta( $product_id, 'woosb_limit_each_min', true ) : 0 );
							if ( $min_qty_each > 0 ) {
								$min_price = 0;
								foreach ( $prices as $price ) {
									$min_price += absint( $price );
								}
								$min_price = $min_price * $min_qty_each;
							}

							if ( ( $woosb_price_percent = get_post_meta( $product->get_id(), 'woosb_price_percent', true ) ) && is_numeric( $woosb_price_percent ) && ( floatval( $woosb_price_percent ) < 100 ) && ( floatval( $woosb_price_percent ) > 0 ) ) {
								$min_price = ( $min_price * floatval( $woosb_price_percent ) ) / 100;
							}

							switch ( get_option( '_woosb_price_format', 'from_min' ) ) {
								case 'min_only':
									return wc_price( $min_price );
									break;
								case 'from_min':
									return esc_html__( 'From', 'woo-product-bundle' ) . ' ' . wc_price( $min_price );
									break;
							}
						} elseif ( $product->has_variables() ) {
							$min_price = $max_price = 0;
							foreach ( $woosb_items as $woosb_item ) {
								$woosb_product = wc_get_product( $woosb_item['id'] );
								if ( $woosb_product ) {
									if ( $woosb_product->is_type( 'variable' ) ) {
										$min_price += $woosb_product->get_variation_price( 'min' ) * $woosb_item['qty'];
										$max_price += $woosb_product->get_variation_price( 'max' ) * $woosb_item['qty'];
									} else {
										$min_price += $woosb_product->get_price() * $woosb_item['qty'];
										$max_price += $woosb_product->get_price() * $woosb_item['qty'];
									}
								}
							}
							if ( ( $woosb_price_percent = get_post_meta( $product->get_id(), 'woosb_price_percent', true ) ) && is_numeric( $woosb_price_percent ) && ( floatval( $woosb_price_percent ) < 100 ) && ( floatval( $woosb_price_percent ) > 0 ) ) {
								$min_price = ( $min_price * floatval( $woosb_price_percent ) ) / 100;
								$max_price = ( $max_price * floatval( $woosb_price_percent ) ) / 100;
							}

							switch ( get_option( '_woosb_price_format', 'from_min' ) ) {
								case 'min_only':
									return wc_price( $min_price );
									break;
								case 'min_max':
									return wc_price( $min_price ) . ' - ' . wc_price( $max_price );
									break;
								case 'from_min':
									return esc_html__( 'From', 'woo-product-bundle' ) . ' ' . wc_price( $min_price );
									break;
							}
						}
					}

					return $price;
				}

				function woosb_order_again_cart_item_data( $item_data, $item, $order ) {
					if ( isset( $item['woosb_ids'] ) ) {
						$item_data['woosb_order_again'] = 'yes';
					}

					return $item_data;
				}

				function woosb_cart_loaded_from_session() {
					foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
						if ( isset( $cart_item['woosb_order_again'] ) ) {
							WC()->cart->remove_cart_item( $cart_item_key );
							wc_add_notice( sprintf( esc_html__( 'The bundle "%s" could not be added to your cart from order again button. Please buy it directly.', 'woo-product-bundle' ), $cart_item['data']->get_name() ), 'error' );
						}
					}
				}

				function woosb_show_items() {
					global $product;
					$woosb_count = 0;
					$product_id  = $product->get_id();
					
					if ( $woosb_items = $product->get_items() ) {
						echo '<div class="woosb_wrap woosb-wrap">';
						if ( $woosb_before_text = apply_filters( 'woosb_before_text', get_post_meta( $product_id, 'woosb_before_text', true ), $product_id ) ) {
							echo '<div class="woosb_before_text woosb-before-text woosb-text">' . do_shortcode( stripslashes( $woosb_before_text ) ) . '</div>';
						}
						do_action( 'woosb_before_table', $product );
						?>
                        <table cellspacing="0" class="woosb_products woosb-table woosb-products"
                               data-percent="<?php echo esc_attr( get_post_meta( $product_id, 'woosb_price_percent', true ) ); ?>"
                               data-regular="<?php echo esc_attr( get_post_meta( $product_id, '_regular_price', true ) ); ?>"
                               data-sale="<?php echo esc_attr( get_post_meta( $product_id, '_sale_price', true ) ); ?>"
                               data-variables="<?php echo esc_attr( $product->has_variables() ? 'yes' : 'no' ); ?>"
                               data-optional="<?php echo esc_attr( $product->is_optional() ? 'yes' : 'no' ); ?>"
                               data-min="<?php echo esc_attr( get_post_meta( $product_id, 'woosb_limit_whole_min', true ) ? get_post_meta( $product_id, 'woosb_limit_whole_min', true ) : 1 ); ?>"
                               data-max="<?php echo esc_attr( get_post_meta( $product_id, 'woosb_limit_whole_max', true ) ? get_post_meta( $product_id, 'woosb_limit_whole_max', true ) : '' ); ?>">
                            <tbody>
							<?php foreach ( $woosb_items as $woosb_item ) {
								$woosb_product = wc_get_product( $woosb_item['id'] );
								if ( ! $woosb_product || ( $woosb_count > 5 ) ) {
									continue;
								}

								$woosb_product_qty     = $woosb_item['qty'];
								$woosb_product_qty_min = absint( get_post_meta( $product_id, 'woosb_limit_each_min', true ) ? get_post_meta( $product_id, 'woosb_limit_each_min', true ) : 0 );
								$woosb_product_qty_max = absint( get_post_meta( $product_id, 'woosb_limit_each_max', true ) ? get_post_meta( $product_id, 'woosb_limit_each_max', true ) : 1000 );
								if ( $woosb_product_qty < $woosb_product_qty_min ) {
									$woosb_product_qty = $woosb_product_qty_min;
								}
								if ( ( $woosb_product_qty_max > $woosb_product_qty_min ) && ( $woosb_product_qty > $woosb_product_qty_max ) ) {
									$woosb_product_qty = $woosb_product_qty_max;
								}
								if ( ! $woosb_product->is_in_stock() || ! $woosb_product->has_enough_stock( $woosb_product_qty ) ) {
									$woosb_product_qty = 0;
								}
								?>
                                <tr class="woosb-product"
                                    data-id="<?php echo esc_attr( $woosb_product->is_type( 'variable' ) ? 0 : $woosb_item['id'] ); ?>"
                                    data-price="<?php echo esc_attr( $woosb_product->get_price() ); ?>"
                                    data-qty="<?php echo esc_attr( $woosb_product_qty ); ?>">
									<?php if ( get_option( '_woosb_bundled_thumb', 'yes' ) != 'no' ) { ?>
                                        <td class="woosb-thumb">
                                            <div class="woosb-thumb-ori">
												<?php echo apply_filters( 'woosb_item_thumbnail', $woosb_product->get_image( array(
													40,
													40
												) ), $woosb_product ); ?>
                                            </div>
                                            <div class="woosb-thumb-new"></div>
                                        </td>
									<?php } ?>
                                    <td class="woosb-title">
										<?php
										do_action( 'woosb_before_item_name', $woosb_product );
										echo '<div class="woosb-title-inner">';
										if ( ( get_option( '_woosb_bundled_qty', 'yes' ) == 'yes' ) && ( get_post_meta( $product_id, 'woosb_optional_products', true ) != 'on' ) ) {
											echo apply_filters( 'woosb_item_qty', $woosb_item['qty'] . ' × ', $woosb_item['qty'], $woosb_product );
										}
										$woosb_item_name = '';
										if ( $woosb_product->is_visible() && ( get_option( '_woosb_bundled_link', 'yes' ) != 'no' ) ) {
											$woosb_item_name .= '<a href="' . get_permalink( $woosb_item['id'] ) . '" ' . ( get_option( '_woosb_bundled_link', 'yes' ) == 'yes_blank' ? 'target="_blank"' : '' ) . '>';
										}
										if ( $woosb_product->is_in_stock() && $woosb_product->has_enough_stock( $woosb_product_qty ) ) {
											$woosb_item_name .= $woosb_product->get_name();
										} else {
											$woosb_item_name .= '<s>' . $woosb_product->get_name() . '</s>';
										}
										if ( $woosb_product->is_visible() && ( get_option( '_woosb_bundled_link', 'yes' ) != 'no' ) ) {
											$woosb_item_name .= '</a>';
										}
										echo apply_filters( 'woosb_item_name', $woosb_item_name, $woosb_product );
										echo '</div>';
										do_action( 'woosb_after_item_name', $woosb_product );
										if ( get_option( '_woosb_bundled_description', 'no' ) == 'yes' ) {
											echo '<div class="woosb-description">' . apply_filters( 'woosb_item_description', $woosb_product->get_short_description(), $woosb_product ) . '</div>';
										}
										if ( $woosb_product->is_type( 'variable' ) ) {
											$attributes           = $woosb_product->get_variation_attributes();
											$available_variations = $woosb_product->get_available_variations();
											if ( is_array( $attributes ) && ( count( $attributes ) > 0 ) ) {
												echo '<form class="variations_form" data-product_id="' . absint( $woosb_product->get_id() ) . '" data-product_variations="' . htmlspecialchars( wp_json_encode( $available_variations ) ) . '">';
												echo '<div class="variations">';
												foreach ( $attributes as $attribute_name => $options ) { ?>
                                                    <div class="variation">
                                                        <div
                                                                class="label"><?php echo wc_attribute_label( $attribute_name ); ?></div>
                                                        <div class="select">
															<?php
															$selected = isset( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ? wc_clean( stripslashes( urldecode( $_REQUEST[ 'attribute_' . sanitize_title( $attribute_name ) ] ) ) ) : $woosb_product->get_variation_default_attribute( $attribute_name );
															wc_dropdown_variation_attribute_options( array(
																'options'          => $options,
																'attribute'        => $attribute_name,
																'product'          => $woosb_product,
																'selected'         => $selected,
																'show_option_none' => esc_html__( 'Choose', 'woo-product-bundle' ) . ' ' . wc_attribute_label( $attribute_name )
															) );
															?>
                                                        </div>
                                                    </div>
												<?php }
												echo '<div class="reset">' . apply_filters( 'woocommerce_reset_variations_link', '<a class="reset_variations" href="#">' . esc_html__( 'Clear', 'woo-product-bundle' ) . '</a>' ) . '</div>';
												echo '</div>';
												echo '</form>';
												if ( get_option( '_woosb_bundled_description', 'no' ) == 'yes' ) {
													echo '<div class="woosb-variation-description"></div>';
												}
											}
											do_action( 'woosb_after_item_variations', $woosb_product );
										}
										?>
                                    </td>
									<?php if ( get_post_meta( $product_id, 'woosb_optional_products', true ) == 'on' ) {
										$min_qty = absint( get_post_meta( $product_id, 'woosb_limit_each_min', true ) ? get_post_meta( $product_id, 'woosb_limit_each_min', true ) : 0 );
										$max_qty = absint( get_post_meta( $product_id, 'woosb_limit_each_max', true ) ? get_post_meta( $product_id, 'woosb_limit_each_max', true ) : 1000 );
										if ( ( $woosb_product->get_backorders() == 'no' ) && ( $woosb_product->get_stock_status() != 'onbackorder' ) && is_int( $woosb_product->get_stock_quantity() ) && ( $woosb_product->get_stock_quantity() < $max_qty ) ) {
											$max_qty = $woosb_product->get_stock_quantity();
										}
										if ( $woosb_product->is_in_stock() ) {
											?>
                                            <td class="woosb-qty">
                                                <input type="number" class="input-text qty text"
                                                       value="<?php echo esc_attr( $woosb_product_qty ); ?>"
                                                       min="<?php echo esc_attr( $min_qty ); ?>"
                                                       max="<?php echo esc_attr( $max_qty ); ?>"/>
                                            </td>
											<?php
										} else { ?>
                                            <td class="woosb-qty">
                                                <input type="number" class="input-text qty text" value="0" disabled/>
                                            </td>
										<?php }
									} ?>
									<?php if ( get_option( '_woosb_bundled_price', 'html' ) != 'no' ) { ?>
                                        <td class="woosb-price">
                                            <div class="woosb-price-ori">
												<?php
												$woosb_price = '';
												switch ( get_option( '_woosb_bundled_price', 'html' ) ) {
													case 'price':
														$woosb_price = wc_price( $woosb_product->get_price() );
														break;
													case 'html':
														$woosb_price = $woosb_product->get_price_html();
														break;
													case 'subtotal':
														$woosb_price = wc_price( $woosb_product->get_price() * $woosb_item['qty'] );
														break;
												}
												echo apply_filters( 'woosb_item_price', $woosb_price, $woosb_product );
												?>
                                            </div>
                                            <div class="woosb-price-new"></div>
											<?php do_action( 'woosb_after_item_price', $woosb_product ); ?>
                                        </td>
									<?php } ?>
                                </tr>
								<?php
								$woosb_count ++;
							} ?>
                            </tbody>
                        </table>
						<?php
						if ( ( $product->has_variables() || $product->is_optional() ) && method_exists( $product, 'is_fixed_price' ) && ! $product->is_fixed_price() ) {
							echo '<div class="woosb_total woosb-total woosb-text"></div>';
						}
						do_action( 'woosb_after_table', $product );
						if ( $woosb_after_text = apply_filters( 'woosb_after_text', get_post_meta( $product_id, 'woosb_after_text', true ), $product_id ) ) {
							echo '<div class="woosb_after_text woosb-after-text woosb-text">' . do_shortcode( stripslashes( $woosb_after_text ) ) . '</div>';
						}
						echo '</div>';
					}
				}

				function woosb_get_items( $product_id ) {
					$woosb_arr = array();
					if ( ( $woosb_ids = get_post_meta( $product_id, 'woosb_ids', true ) ) ) {
						$woosb_items = explode( ',', $woosb_ids );
						if ( is_array( $woosb_items ) && count( $woosb_items ) > 0 ) {
							foreach ( $woosb_items as $woosb_item ) {
								$woosb_item_arr = explode( '/', $woosb_item );
								$woosb_arr[]    = array(
									'id'  => absint( isset( $woosb_item_arr[0] ) ? $woosb_item_arr[0] : 0 ),
									'qty' => absint( isset( $woosb_item_arr[1] ) ? $woosb_item_arr[1] : 1 )
								);
							}
						}
					}
					if ( count( $woosb_arr ) > 0 ) {
						return $woosb_arr;
					} else {
						return false;
					}
				}

				function woosb_clean_ids( $ids ) {
					$ids = preg_replace( '/[^,\/0-9]/', '', $ids );

					return $ids;
				}

				function woosb_deactivation() {
					wp_clear_scheduled_hook( 'woosb_cron_jobs' );
				}

				function find_bundles( $product_id = 0 ) {
					$product_id_str   = (string) $product_id . '/';
					$woosb_query_args = array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => - 1,
						'tax_query'      => array(
							array(
								'taxonomy' => 'product_type',
								'field'    => 'slug',
								'terms'    => array( 'woosb' ),
								'operator' => 'IN',
							)
						),
						'meta_query'     => array(
							array(
								'key'     => 'woosb_ids',
								'value'   => $product_id_str,
								'compare' => 'LIKE',
							)
						)
					);
					$woosb_query      = new WP_Query( $woosb_query_args );
					if ( $woosb_query->have_posts() ) {
						while ( $woosb_query->have_posts() ) {
							$woosb_query->the_post();
							echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
						}
					}
				}
			}

			new WPcleverWoosb();
		}
	}

	function woosb_notice_wc() {
		?>
        <div class="error">
            <p><?php esc_html_e( 'WPC Product Bundles require WooCommerce version 3.0.0 or greater.', 'woo-product-bundle' ); ?></p>
        </div>
		<?php
	}
}