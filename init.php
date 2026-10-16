<?php 
/*
Plugin Name: WP Smart Property
Plugin URI: https://www.wpsmartplugin.com/
Description: This is Real Estate property management plugin with smart admin control and searching option and easy to modify for your theme. This plugin stores properties like a post so its not need any theme updation. 
Author: Rajan V
Version: 2.0.1
Author URI: https://www.fb.com/rajanit2000
Requires at least: 4.7
Requires PHP: 5.6
Text Domain: wp-smart-property
License: GPLv2 or later
/************************************************************
 * Copyright (c) 2017 WP Smart Plugin
 * https://www.wpsmartplugin.com/
 ************************************************************/
 
if( !defined( 'WP_SMPR_VERSION' ) )
	define( 'WP_SMPR_VERSION' , '2.0.1' );

// Baasic default values
$property_plugin_dir = dirname( __FILE__ );
$property_plugin_url = plugins_url( '', __FILE__ );

// Required Files
require_once "$property_plugin_dir/core/install.php";
require_once "$property_plugin_dir/core/metabox.php";
require_once "$property_plugin_dir/core/extras.php";

// The grid is a modified copy of the Post Grid plugin. When that plugin is active it already provides
// the grid post type and shortcode, so loading the copy as well would clash.
$wp_smart_property_post_grid_active = class_exists( 'PostGrid' )
	|| in_array( 'post-grid/post-grid.php', (array) get_option( 'active_plugins', array() ), true )
	|| ( is_multisite() && array_key_exists( 'post-grid/post-grid.php', (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
if ( ! $wp_smart_property_post_grid_active ) {
	require_once "$property_plugin_dir/post-grid/post-grid.php";
} else {
	add_action( 'admin_notices', 'wp_smart_property_post_grid_notice' );
}

/**
 * Tell administrators that the Post Grid plugin provides the grids.
 */
function wp_smart_property_post_grid_notice() {
	if ( current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-info"><p>' . esc_html__( 'WP Smart Property: the Post Grid plugin is active, so it provides the property grids and the [post_grid] shortcode.', 'wp-smart-property' ) . '</p></div>';
	}
}
?>