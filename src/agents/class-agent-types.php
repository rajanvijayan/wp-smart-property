<?php
/**
 * Agents and agencies.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Agents;

/**
 * Public agent profiles (optionally linked to a WordPress user) and agencies that group them.
 */
final class Agent_Types {

	const AGENT  = 'estate_agent';
	const AGENCY = 'estate_agency';

	/**
	 * Register the post types and their meta.
	 */
	public static function register() {
		$common = array(
			'public'          => true,
			'show_in_rest'    => true,
			'show_in_menu'    => 'edit.php?post_type=property',
			'capability_type' => array( 'estate_agent', 'estate_agents' ),
			'map_meta_cap'    => true,
			'has_archive'     => true,
		);
		register_post_type(
			self::AGENT,
			$common + array(
				'labels'    => array(
					'name'          => __( 'Agents', 'wp-smart-property' ),
					'singular_name' => __( 'Agent', 'wp-smart-property' ),
					'add_new_item'  => __( 'Add new agent', 'wp-smart-property' ),
					'edit_item'     => __( 'Edit agent', 'wp-smart-property' ),
					'all_items'     => __( 'Agents', 'wp-smart-property' ),
				),
				'rest_base' => 'estate-agents',
				'rewrite'   => array(
					'slug'       => 'agent',
					'with_front' => false,
				),
				'supports'  => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			)
		);
		register_post_type(
			self::AGENCY,
			$common + array(
				'labels'    => array(
					'name'          => __( 'Agencies', 'wp-smart-property' ),
					'singular_name' => __( 'Agency', 'wp-smart-property' ),
					'add_new_item'  => __( 'Add new agency', 'wp-smart-property' ),
					'edit_item'     => __( 'Edit agency', 'wp-smart-property' ),
					'all_items'     => __( 'Agencies', 'wp-smart-property' ),
				),
				'rest_base' => 'estate-agencies',
				'rewrite'   => array(
					'slug'       => 'agency',
					'with_front' => false,
				),
				'supports'  => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			)
		);

		$fields = array(
			self::AGENT  => array(
				'email'     => 'email',
				'phone'     => 'text',
				'mobile'    => 'text',
				'license'   => 'text',
				'website'   => 'url',
				'agency'    => 'id',
				'user_id'   => 'id',
				'languages' => 'text',
			),
			self::AGENCY => array(
				'email'   => 'email',
				'phone'   => 'text',
				'website' => 'url',
				'address' => 'textarea',
			),
		);
		foreach ( $fields as $post_type => $keys ) {
			foreach ( $keys as $key => $type ) {
				register_post_meta(
					$post_type,
					'_estatecraft_' . $key,
					array(
						'type'              => 'id' === $type ? 'integer' : 'string',
						'single'            => true,
						// The linked user is internal; contact details are public on the profile.
						'show_in_rest'      => 'user_id' !== $key,
						'sanitize_callback' => static function ( $value ) use ( $type ) {
							switch ( $type ) {
								case 'email':
									return sanitize_email( (string) $value );
								case 'url':
									return esc_url_raw( (string) $value, array( 'http', 'https' ) );
								case 'id':
									return absint( $value );
								case 'textarea':
									return sanitize_textarea_field( (string) $value );
							}
							return sanitize_text_field( (string) $value );
						},
						'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
							return current_user_can( 'edit_post', $post_id );
						},
					)
				);
			}
		}
	}
}
