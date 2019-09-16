<?php
add_action( 'customize_register', 'zigcy_baby_prio_customize_register', 9);
function zigcy_baby_prio_customize_register( $wp_customize ) {
//Product Category section Two
	$wp_customize->add_section( 'zigcy_lite_pro_cat_two_section', array(
		'title'           =>      esc_html__('Product Category Section Two', 'zigcy-lite'),
		'priority'        =>      '4',
		'panel' => 'zigcy_lite_home_setting'
	));
}//prioritised customizer close

add_action( 'customize_register', 'zigcy_baby_customize_register', 100);
function zigcy_baby_customize_register( $wp_customize ) {

	$zigcy_lite_cat_lists   = zigcy_lite_cat_lists(); 
	$zigcy_lite_prod_cats = zigcy_lite_product_cats();
	$cat_list = zigcy_lite_category_lists();
	/** header Type **/
	$wp_customize->get_setting( 'zigcy_lite_header_type')->sanitize_callback = 'zigcy_layout_sanitize';
	$wp_customize->get_control( 'zigcy_lite_header_type')->choices = array(
		'layout1'     => esc_html__('Layout One', 'zigcy-baby'),
		'layout2'     => esc_html__('Layout Two', 'zigcy-baby'),
		'layout3'     => esc_html__('Layout Three', 'zigcy-baby'),
	);

	/** pro cat section */
	if(class_exists('WooCommerce')){
		$pro_cat_num = array('four','five','six');
		foreach($pro_cat_num as $pro_cat){
			$wp_customize->add_setting( 'zigcy_lite_product_categories_'.$pro_cat,array(
				'sanitize_callback' => 'absint',
				'default'           => 0,
			));
			$wp_customize->add_control( 'zigcy_lite_product_categories_'.$pro_cat, array(
				'label'       => esc_html__('Select Category ', 'zigcy-lite').ucwords($pro_cat),
				'description' => esc_html__('The list will display categories from Product', 'zigcy-lite'),
				'section'     => 'zigcy_lite_pro_cat_setting',
				'type'        => 'select',
				'choices'     => $zigcy_lite_prod_cats,
			));
		}
	}

	//Product Category section Two options
	if(class_exists('WooCommerce')){

		$wp_customize->add_setting( 'zigcy_lite_pro_cat_two_enable', array(
			'default'             => 'off',
			'sanitize_callback'   => 'zigcy_lite_sanitize_text',
		) );

		$wp_customize->add_control( new zigcy_lite_Customizer_Buttonset_Control( $wp_customize, 'zigcy_lite_pro_cat_two_enable', array(
			'label'         => esc_html__( 'Enable Disable Porduct Category', 'zigcy-lite' ),
			'section'       => 'zigcy_lite_pro_cat_two_section',
			'priority'        => 1,
			'choices'         => array(
				'on'        => esc_html__( 'Yes', 'zigcy-lite' ),
				'off'       => esc_html__( 'No', 'zigcy-lite' ),
			)
		) ) );

		/** drop down categories for features two **/
		$pro_cat_num = array('one','two');
		foreach($pro_cat_num as $pro_cat){
			$wp_customize->add_setting( 'zigcy_lite_product_categories_two_'.$pro_cat,array(
				'sanitize_callback' => 'absint',
				'default'           => 0,
			));
			$wp_customize->add_control( 'zigcy_lite_product_categories_two_'.$pro_cat, array(
				'label'       => esc_html__('Select Category ', 'zigcy-lite').ucwords($pro_cat),
				'description' => esc_html__('The list will display categories from Product', 'zigcy-lite'),
				'section'     => 'zigcy_lite_pro_cat_two_section',
				'type'        => 'select',
				'choices'     => $zigcy_lite_prod_cats,
			));
		}
	}else{
  		//instructions
		$wp_customize->add_setting( 'zigcy_lite_instructions_two', array(
			'sanitize_callback'    => 'sanitize_text_field'
		));
		$wp_customize->add_control( new zigcy_lite_Customize_Info( $wp_customize,'zigcy_lite_instructions_two', array(
			'section'         => 'zigcy_lite_pro_cat_two_section',
			'label'           => esc_html__('Instructions','zigcy-lite'),
			'description'     => esc_html__('Install WooCommerce Plugin to list options','zigcy-lite'),
		)));
	}

	//CTA form shortcode
	$wp_customize->add_setting( 'zigcy_baby_cta_shortcode', array(
		'sanitize_callback'   => 'sanitize_text_field'
	));

	$wp_customize->add_control('zigcy_baby_cta_shortcode', array(
		'label'     => esc_html__('Shortcode','zigcy-lite'),
		'type'      => 'text',
		'section'   => 'zigcy_lite_cta_setting',
	));
} //customizer action close