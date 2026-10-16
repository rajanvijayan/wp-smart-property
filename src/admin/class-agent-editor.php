<?php
/**
 * Contact details boxes for agents and agencies.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Admin;

use EstateCraft\Agents\Agent_Types;
use WP_Post;

/**
 * Plain form fields; values are sanitized by the registered meta callbacks.
 */
final class Agent_Editor {

	const NONCE = 'estatecraft_agent';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'add_meta_boxes', array( self::class, 'add_boxes' ) );
		add_action( 'save_post_' . Agent_Types::AGENT, array( self::class, 'save' ) );
		add_action( 'save_post_' . Agent_Types::AGENCY, array( self::class, 'save' ) );
	}

	/**
	 * Fields per post type: key => array( label, input type ).
	 *
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function fields( $post_type ) {
		if ( Agent_Types::AGENCY === $post_type ) {
			return array(
				'email'   => array( __( 'Email', 'wp-smart-property' ), 'email' ),
				'phone'   => array( __( 'Phone', 'wp-smart-property' ), 'tel' ),
				'website' => array( __( 'Website', 'wp-smart-property' ), 'url' ),
				'address' => array( __( 'Address', 'wp-smart-property' ), 'textarea' ),
			);
		}
		return array(
			'email'     => array( __( 'Email', 'wp-smart-property' ), 'email' ),
			'phone'     => array( __( 'Phone', 'wp-smart-property' ), 'tel' ),
			'mobile'    => array( __( 'Mobile', 'wp-smart-property' ), 'tel' ),
			'website'   => array( __( 'Website', 'wp-smart-property' ), 'url' ),
			'license'   => array( __( 'License number', 'wp-smart-property' ), 'text' ),
			'languages' => array( __( 'Languages spoken', 'wp-smart-property' ), 'text' ),
			'agency'    => array( __( 'Agency', 'wp-smart-property' ), 'agency' ),
			'user_id'   => array( __( 'WordPress user', 'wp-smart-property' ), 'user' ),
		);
	}

	/**
	 * Add the boxes.
	 *
	 * @param string $post_type Post type.
	 */
	public static function add_boxes( $post_type ) {
		if ( in_array( $post_type, array( Agent_Types::AGENT, Agent_Types::AGENCY ), true ) ) {
			add_meta_box( 'estatecraft-contact', __( 'Contact details', 'wp-smart-property' ), array( self::class, 'render' ), $post_type, 'normal', 'high' );
		}
	}

	/**
	 * Box markup.
	 *
	 * @param WP_Post $post Agent or agency.
	 */
	public static function render( WP_Post $post ) {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( self::fields( $post->post_type ) as $key => $field ) {
			list( $label, $type ) = $field;
			$id                   = 'estatecraft-' . $key;
			$name                 = 'estatecraft_contact[' . $key . ']';
			$value                = get_post_meta( $post->ID, '_estatecraft_' . $key, true );
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( 'textarea' === $type ) {
				echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
			} elseif ( 'agency' === $type ) {
				$agencies = get_posts(
					array(
						'post_type'   => Agent_Types::AGENCY,
						'numberposts' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- one dropdown of agencies.
						'orderby'     => 'title',
						'order'       => 'ASC',
					)
				);
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="0">' . esc_html__( '— None —', 'wp-smart-property' ) . '</option>';
				foreach ( $agencies as $agency ) {
					echo '<option value="' . esc_attr( $agency->ID ) . '"' . selected( (int) $value, $agency->ID, false ) . '>' . esc_html( get_the_title( $agency ) ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'user' === $type ) {
				wp_dropdown_users(
					array(
						'name'              => $name,
						'id'                => $id,
						'selected'          => (int) $value,
						'show_option_none'  => __( '— None —', 'wp-smart-property' ),
						'option_none_value' => 0,
						'capability'        => array( 'edit_estate_properties' ),
					)
				);
				echo '<p class="description">' . esc_html__( 'Lets this person manage their listings and inquiries in the portal.', 'wp-smart-property' ) . '</p>';
			} else {
				echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Save.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ], $_POST['estatecraft_contact'] ) || ! is_array( $_POST['estatecraft_contact'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$values = wp_unslash( $_POST['estatecraft_contact'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by the registered meta callbacks.
		foreach ( array_keys( self::fields( get_post_type( $post_id ) ) ) as $key ) {
			if ( ! isset( $values[ $key ] ) || ! is_scalar( $values[ $key ] ) ) {
				continue;
			}
			// Linking a WordPress user is for people who manage agents.
			if ( 'user_id' === $key && ! current_user_can( 'edit_others_estate_agents' ) ) {
				continue;
			}
			update_post_meta( $post_id, '_estatecraft_' . $key, $values[ $key ] );
		}
	}
}
