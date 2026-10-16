<?php
/**
 * Agent and agency archive, for classic themes.
 *
 * To change it, copy this file to yourtheme/estatecraft/archive-estate_agent.php. The layout is made of
 * blocks; EstateCraft\Frontend\Templates::body() lists them.
 *
 * @package EstateCraft
 */

defined( 'ABSPATH' ) || exit;

get_header();
EstateCraft\Frontend\Templates::render( 'archive-estate_agent' );
get_footer();
