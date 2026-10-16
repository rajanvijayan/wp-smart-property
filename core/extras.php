<?php
/*
 * @Author 		wp-smart-property
 * @Author 		WP Smart Plugin
 * Copyright: 	https://www.wpsmartplugin.com/
 */
class WPSMPR_Extra extends WPSMPR_Register_Post{
	/**
	 * Init, extra features for properties panel
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'property_remove_meta_boxes' ), 5 );
	}
	public static function property_remove_meta_boxes(){
		/**
		* Remove Custom fiels options on property page
		*/
		remove_meta_box('postcustom', 'property', 'normal');
	}
}
WPSMPR_Extra::init();
?>