<?php
/**
 * Inquiries from visitors.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Inquiries;

/**
 * Inquiries are private records: never public, never in REST through the core routes.
 */
final class Inquiry_Type {

	const POST_TYPE = 'estate_inquiry';

	/**
	 * Register the post type.
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Inquiries', 'wp-smart-property' ),
					'singular_name' => __( 'Inquiry', 'wp-smart-property' ),
					'all_items'     => __( 'Inquiries', 'wp-smart-property' ),
					'edit_item'     => __( 'Inquiry', 'wp-smart-property' ),
				),
				'public'              => false,
				// The inquiries screen arrives with the inquiry forms.
				'show_ui'             => (bool) apply_filters( 'estatecraft_inquiries_ui', false ),
				'show_in_menu'        => 'edit.php?post_type=property',
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'rewrite'             => false,
				'capability_type'     => array( 'estate_inquiry', 'estate_inquiries' ),
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor' ),
			)
		);
	}
}
