<?php 
$class = '';
if(!class_exists('WooCommerce')) {
	$class = ' no-wocommerce';
}?>
<header id="masthead" class="site-header header-three<?php echo esc_attr($class); ?>">
	<div class="store-mart-lite-top-header-wrap">
		<div class="store-mart-lite-header-icons">
			<?php do_action('zigcy_lite_top_left_header'); ?>
			<?php do_action('zigcy_baby_top_right_header'); ?>
		</div>
	</div>
	<div class="container">		
		<div class ="store-mart-lite-logos">
			<?php do_action('zigcy_lite_header_logo'); ?>
			<?php if ( class_exists( 'WooCommerce' ) ) {
				echo zigcy_lite_product_search(); // WPCS: XSS OK.
			} ?>
			<div class="store-mart-lite-login-wrap">
				<?php echo zigcy_lite_login_signup(); // WPCS: XSS OK. ?>
				<?php echo zigcy_lite_wishlist_header_count(); // WPCS: XSS OK. ?>
				<?php echo zigcy_lite_woo_cart_icon(); // WPCS: XSS OK. ?>
			</div>			
		</div>
	</div>
	<div class="zigcy-menu-wrap">
		<div class="store-mart-lite-nav-menu">
			<div class="container">
				<?php do_action('zigcy_lite_main_navigation'); ?>
			</div>
		</div>
	</div>
</header><!-- #masthead -->