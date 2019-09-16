<?php
add_action( 'after_setup_theme', 'zigcy_baby_setup' );
if ( ! function_exists( 'zigcy_baby_setup' ) ){
	function zigcy_baby_setup() {
		load_child_theme_textdomain( 'zigcy-baby', get_stylesheet_directory() . '/languages' );
	}
}

add_action( 'wp_enqueue_scripts', 'zigcy_baby_enqueue_styles');
function zigcy_baby_enqueue_styles() {

	$query_args = array('family' => 'Poppins:100,200,300,400,500,600,700,800|Baloo');
	wp_enqueue_style('google-fonts', add_query_arg($query_args, "//fonts.googleapis.com/css"));

	$parent_style = 'zigcy-lite';
	wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css',array());
	wp_enqueue_style( 'zigcy-baby-responsive', get_stylesheet_directory_uri() . '/assets/css/responsive.css',array('zigcy-lite-style','zigcy-lite-responsive'));
}

if ( get_stylesheet() !== get_template() ) {
	add_filter( 'pre_update_option_theme_mods_' . get_stylesheet(), function ( $value, $old_value ) {
		update_option( 'theme_mods_' . get_template(), $value );
         return $old_value; // prevent update to child theme mods
     }, 10, 2 );
	add_filter( 'pre_option_theme_mods_' . get_stylesheet(), function ( $default ) {
		return get_option( 'theme_mods_' . get_template(), $default );
	} );
}

/**
 * Allow HTML in term (category, tag) descriptions
 */
foreach ( array( 'pre_term_description' ) as $filter ) {
	remove_filter( $filter, 'wp_filter_kses' );
}


/**
*
* PRoduct Cat Section Two
*/
if( ! function_exists('zigcy_lite_pro_cat_two')){
	function zigcy_lite_pro_cat_two(){
		$slider_enable =  get_theme_mod('zigcy_lite_pro_cat_two_enable','off');
		if($slider_enable == 'on'){ ?>
			<section id="plx_prod_cat_two_section" class="plx_prod_cat_section">
				<?php  zigcy_lite_pro_cat_setting(true); ?>
			</section>
		<?php }

	}
} add_action( 'zigcy_lite_pro_cat_two_section', 'zigcy_lite_pro_cat_two');

/** Include customizer options addition file */
require get_stylesheet_directory() . '/inc/customizer/zigcy-baby-customizer.php';

add_action( 'zigcy_baby_top_right_header','zigcy_baby_top_right_header_callback', 10);
if ( !function_exists( 'zigcy_baby_top_right_header_callback' ) ) {
	function zigcy_baby_top_right_header_callback() {
		$zigcy_lite_call_title = get_theme_mod('zigcy_lite_call_title','Shop online or call');
		$zigcy_lite_contact_no = get_theme_mod('zigcy_lite_contact_no');
		$zigcy_lite_header = get_theme_mod('zigcy_lite_header_type','layout1');?>

		<div class="store-mart-lite-top-header-left">
			<?php if ($zigcy_lite_header == 'layout1' || $zigcy_lite_header == 'layout3'){ 
				if ( $zigcy_lite_call_title ) { ?>
					<div class="top-header-call-title">
						<?php echo esc_html($zigcy_lite_call_title); ?>
					</div>
				<?php }
				if ( $zigcy_lite_contact_no ) { ?>
					<div class="top-header-contact-num">
						<i class="fa fa-phone"></i>
						<?php echo esc_html($zigcy_lite_contact_no); ?>
					</div>
				<?php }
			} ?>
			<div class="store-mart-lite-sc-icons">
				<?php do_action('zigcy_lite_social_icons'); ?>
			</div>
		</div>
		<?php 
	}
}


/**
 * Zigcy Lite Product Category Section
*/ 
if(!function_exists('zigcy_lite_pro_cat_setting')){
	function zigcy_lite_pro_cat_setting($duplicate=false) { 
		if(! class_exists('Woocommerce')) {
			return;
		}?>
		<div class="container">
			<div class="store-mart-lite-cat-pro-wrap">
				<?php
				$pro_cat_num = array('one','two','three','four','five','six');
				if($duplicate==true){
					$pro_cat_num = array('one','two');
				}
				foreach ($pro_cat_num as $zigcy_pro_cat) {

					$zigcy_lite_product_category = get_theme_mod( 'zigcy_lite_product_categories_'.$zigcy_pro_cat,'0' );
					if($duplicate==true){
						$zigcy_lite_product_category = get_theme_mod( 'zigcy_lite_product_categories_two_'.$zigcy_pro_cat,'0' );
					}

					if(!empty($zigcy_lite_product_category)){

						$zigcy_term = get_term_by( 'id', $zigcy_lite_product_category, 'product_cat' );
						$zigcy_thumbnail_id = get_woocommerce_term_meta( $zigcy_lite_product_category, 'thumbnail_id' );
						$zigcy_image = wp_get_attachment_url( $zigcy_thumbnail_id );
						$zigcy_category_link = get_category_link( $zigcy_lite_product_category );

						?>
						<div class="zigcy-baby-prod-cat-wrapper store-mart-lite-prod-cat-wrapper-<?php echo esc_attr($zigcy_pro_cat);?>">
							<?php if($zigcy_term){ ?>
								<div class="store-mart-lite-cat-prod-content">
									<?php if(!empty($zigcy_term->description)){ ?>
										<div class="store-mart-lite-cat-prod-description">
											<?php echo wp_kses($zigcy_term->description,array('span' => array('class' => array()))); ?>
										</div>
										<?php
									}
									if($duplicate==true){
										?>
										<div class="store-mart-lite-cat-prod-title layout2">
											<?php echo esc_html($zigcy_term->name); ?>
										</div>
										<a href="<?php echo esc_url( $zigcy_category_link ); ?>">
											<?php esc_html_e('Shop Now','zigcy-baby'); ?>
										</a>
										<?php
									}
									else{
										?>
										<div class="store-mart-lite-cat-prod-title">
											<a href="<?php echo esc_url( $zigcy_category_link ); ?>">
												<?php echo esc_html($zigcy_term->name); ?>
												<span class="prod-count"><?php echo esc_html($zigcy_term->count); ?></span>
											</a>
										</div>
										<?php
									}?>
								</div>
								<div class="store-mart-lite-cat-prod-image">
									<a href="<?php echo esc_url( $zigcy_category_link ); ?>">
										<img src="<?php echo esc_url($zigcy_image); ?>" title="<?php the_title_attribute() ?>" alt="<?php the_title_attribute() ?>" />
									</a>
								</div>
							<?php } ?>
						</div>
						<?php
					}
				}
				?>
			</div>
		</div>
		<?php 
	}
}

/**
 * Store Villa Header Promo Function Area 
*/ 
if ( ! function_exists( 'zigcy_lite_cta_setting' ) ) { 
	function zigcy_lite_cta_setting() {        
		$cta_title = get_theme_mod( 'zigcy_lite_cta_title'  );
		$cta_subtitle = get_theme_mod( 'zigcy_lite_cta_subtitle'  );
		$cta_button_text = get_theme_mod( 'zigcy_lite_cta_button_text'  );
		$cta_button_link = get_theme_mod( 'zigcy_lite_cta_button_link'  );
		$cta_price = get_theme_mod('zigcy_lite_price_title');
		$cta_shortcode = get_theme_mod('zigcy_baby_cta_shortcode');

		?>
		<?php if($cta_title || $cta_subtitle || $cta_button_text || $cta_button_link || $cta_price ){
			?>
			<div class="store-mart-lite-cta-wrapper">
				<div class="store-mart-lite-cta-content-wrap">
					<?php if($cta_title){ ?>
						<div class="cta-title"><?php echo esc_html($cta_title); ?></div>
					<?php } ?>

					<?php if($cta_subtitle){ ?>
						<div class="cta-subtitle"><?php echo esc_html($cta_subtitle); ?></div>
					<?php } ?>

					<?php if($cta_price){ ?>
						<div class="cta-price-text"><?php echo esc_html($cta_price); ?></div>
					<?php } ?>

					<?php if($cta_shortcode){ ?>
						<div class="cta-shortcode"><?php echo do_shortcode($cta_shortcode); ?></div>
					<?php } ?>

					<?php if($cta_button_link){ ?>
						<div class="store-mart-lite-cta-button ">
							<a href="<?php echo esc_url($cta_button_link); ?>">
								<?php echo esc_html($cta_button_text); ?>
							</a>
						</div>
					<?php } ?>
				</div>
			</div>
		<?php } 
	}
}


if ( ! function_exists( 'zigcy_lite_blog_setting' ) ) {
  /**
   * Display the blog
   * @since  1.0.0
   * @return void
   */
  function zigcy_lite_blog_setting() {

  	$blogtitle = get_theme_mod( 'zigcy_lite_blog_title' );
  	$blogsubTitle = get_theme_mod( 'zigcy_lite_blog_subtitle' );
  	$blogCategories = get_theme_mod( 'zigcy_lite_blog_categories' );
  	$excerptLength = get_theme_mod( 'zigcy_lite_post_excerpt_length','200' );

  	?>
  	<div class="container">
  		<div class="store-mart-lite-blog-wrapper">
  			<?php if($blogtitle || $blogsubTitle){?>
  				<div class="section-title-sub-wrap ">
  					<?php if($blogtitle){ ?>
  						<h5 class="blog-title"><?php echo esc_html($blogtitle); ?></h5>
  					<?php } ?>
  					<?php if($blogsubTitle) { ?>
  						<h3 class = "blog-subtitle"><?php echo esc_html($blogsubTitle); ?></h3>
  					<?php } ?>
  				</div>
  			<?php } ?>
  			<div class="store-mart-lite-blog-content clear ">
  				<?php
  				$blog_args = array(
  					'cat' => $blogCategories,
  					'posts_per_page' => 2,
  					'post_status'=>'publish',
  				);
  				$blog_query = new WP_Query( $blog_args );
  				if( $blog_query->have_posts() ) { 
  					while( $blog_query->have_posts() ) { 
  						$blog_query->the_post();
  						$image_src = wp_get_attachment_image_src(get_post_thumbnail_id(),'large');
  						$image_path = $image_src[0];

  						$image_id   = get_post_thumbnail_id();
  						$alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
  						?>
  						<div class="blog-inner-content">
  							<div class="blog-img-wrapper">
  								<img src="<?php echo esc_url($image_path); ?>" alt="<?php echo esc_attr($alt); ?>"/>
  							</div>
  							<div class="blog-inner-content-wrapper">
  								<div class="post-meta-wrapp">
  									<?php do_action('zigcy_lite_post_meta'); ?>  
  								</div>
  								<h2 class="blog-title">
  									<a href="<?php the_permalink() ?>"><?php the_title(); ?></a>
  								</h2>

  								<div class="blog-excerpt">
  									<?php echo zigcy_lite_blog_excerpt($excerptLength); ?> 
  								</div>
  							</div>
  						</div>
  					<?php  } } wp_reset_postdata();   ?> 
  				</div>
  			</div>
  		</div>
  		<?php
  	}
  }