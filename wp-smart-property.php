<?php
/**
 * Plugin Name: WP Smart Property
 * Description: WP Smart Property is a comprehensive WordPress plugin designed to manage property listings with ease. This plugin allows users to create, edit, and display property listings on their WordPress website. It provides an intuitive interface for managing property-related taxonomies and meta information, making it perfect for real estate agencies, property managers, and individual property owners.
 * Version: 3.0.0
 * Author: Rajan Vijayan
 * Author URI: https://rajanvijayan.com
 * Text Domain: wp-smart-property
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Autoload classes using Composer
require_once __DIR__ . '/vendor/autoload.php';

use WPSmartProperty\WP_Smart_Property;

// Initialize the plugin
WP_Smart_Property::getInstance();
