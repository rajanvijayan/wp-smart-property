<?php 
/*
Plugin Name: WP Smart Property
Plugin URI: https://www.wpsmartplugin.com/
Description: This is Real Estate property management plugin with smart admin control and searching option and easy to modify for your theme. This plugin stores properties like a post so its not need any theme updation. 
Author: Rajan V
Version: 2.0
Author URI: https://www.fb.com/rajanit2000
Tested up to: 4.7.1
/************************************************************
 * Copyright (c) 2017 WP Smart Plugin
 * https://www.wpsmartplugin.com/
 ************************************************************/
 
if( !defined( 'WP_SMPR_VERSION' ) )
	define( 'WP_SMPR_VERSION' , '2.0' );

// Baasic default values
$property_plugin_dir = WP_PLUGIN_DIR . '/' . basename(dirname(__FILE__));
$property_plugin_url = WP_PLUGIN_URL . '/' . basename(dirname(__FILE__));

// Required Files
require_once "$property_plugin_dir/core/install.php";
require_once "$property_plugin_dir/core/metabox.php";
require_once "$property_plugin_dir/core/extras.php";
require_once "$property_plugin_dir/post-grid/post-grid.php";
?>