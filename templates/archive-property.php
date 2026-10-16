<?php
/**
 * Property archive (also property types, statuses, locations and features), for classic themes.
 *
 * To change it, copy this file to yourtheme/estatecraft/archive-property.php. The layout is made of
 * blocks; EstateCraft\Frontend\Templates::body() lists them.
 *
 * @package EstateCraft
 */

defined( 'ABSPATH' ) || exit;

get_header();
EstateCraft\Frontend\Templates::render( 'archive-property' );
get_footer();
