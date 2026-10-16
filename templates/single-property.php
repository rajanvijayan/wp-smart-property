<?php
/**
 * Single property, for classic themes.
 *
 * To change it, copy this file to yourtheme/estatecraft/single-property.php. The layout is made of
 * blocks; EstateCraft\Frontend\Templates::body() lists them.
 *
 * @package EstateCraft
 */

defined( 'ABSPATH' ) || exit;

get_header();
EstateCraft\Frontend\Templates::render( 'single-property' );
get_footer();
