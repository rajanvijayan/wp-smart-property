<?php
/**
 * The "Property details" box on the property edit screen.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Admin;

use EstateCraft\Agents\Agent_Types;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;
use EstateCraft\Settings;
use WP_Post;

/**
 * A meta box hosting a React app. Meta boxes work the same in the block and classic editors: the app
 * keeps its values in a hidden field that is posted with the box and saved here.
 */
final class Property_Editor {

	const NONCE = 'estatecraft_details';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'add_meta_boxes_' . Property_Type::POST_TYPE, array( self::class, 'add_box' ) );
		add_action( 'save_post_' . Property_Type::POST_TYPE, array( self::class, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Add the box (and hide the raw Custom Fields box: every field has a proper control here).
	 */
	public static function add_box() {
		add_meta_box( 'estatecraft-details', __( 'Property details', 'wp-smart-property' ), array( self::class, 'render' ), Property_Type::POST_TYPE, 'normal', 'high' );
		remove_meta_box( 'postcustom', Property_Type::POST_TYPE, 'normal' );
	}

	/**
	 * Box markup.
	 *
	 * @param WP_Post $post Property.
	 */
	public static function render( WP_Post $post ) {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		echo '<input type="hidden" name="estatecraft_details" id="estatecraft-details-input" value="' . esc_attr( wp_json_encode( self::values( $post->ID ) ) ) . '" />';
		echo '<div id="estatecraft-property-editor" class="estatecraft-editor"><p>' . esc_html__( 'Loading…', 'wp-smart-property' ) . '</p></div>';
	}

	/**
	 * All field values of a property.
	 *
	 * @param int $post_id Property ID.
	 * @return array
	 */
	public static function values( $post_id ) {
		$values = array();
		foreach ( array_keys( Fields::all() ) as $key ) {
			$values[ $key ] = Fields::get( $post_id, $key );
		}
		return $values;
	}

	/**
	 * Save the posted values.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ], $_POST['estatecraft_details'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Each value is sanitized by its field type in Fields::set().
		$values = json_decode( wp_unslash( $_POST['estatecraft_details'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $values ) ) {
			return;
		}
		$fields = Fields::all();
		foreach ( $values as $key => $value ) {
			if ( ! isset( $fields[ $key ] ) ) {
				continue;
			}
			// Only managers change the listing's expiry and the geocoding state by hand.
			if ( in_array( $key, array( 'expires', 'geocode' ), true ) && ! current_user_can( 'edit_others_estate_properties' ) ) {
				continue;
			}
			Fields::set( $post_id, $key, $value );
		}
		// A pin placed or moved by hand is never overwritten by automatic geocoding.
		if ( isset( $values['lat'], $values['lng'] ) && ( (float) $values['lat'] || (float) $values['lng'] ) && 'pending' === Fields::get( $post_id, 'geocode' ) ) {
			Fields::set( $post_id, 'geocode', 'manual' );
		}
		unset( $post );
	}

	/**
	 * Scripts on the property edit screen.
	 *
	 * @param string $hook Admin page.
	 */
	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || Property_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$asset = Assets::asset( 'property-editor' );
		wp_enqueue_media();
		wp_enqueue_script( 'estatecraft-property-editor', ESTATECRAFT_URL . 'build/property-editor.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'estatecraft-property-editor', ESTATECRAFT_URL . 'build/property-editor.css', array( 'wp-components' ), $asset['version'] );
		wp_set_script_translations( 'estatecraft-property-editor', 'wp-smart-property' );

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen context.
		$values  = $post_id ? self::values( $post_id ) : array();
		wp_localize_script(
			'estatecraft-property-editor',
			'estateCraftEditor',
			array(
				'general'     => Settings::get( 'general' ),
				'maps'        => Settings::get( 'maps' ),
				'custom'      => Settings::get( 'fields' ),
				'canManage'   => current_user_can( 'edit_others_estate_properties' ),
				'agents'      => self::agent_names( isset( $values['agents'] ) ? $values['agents'] : array() ),
				'media'       => self::media( $values ),
				'periods'     => Fields::PERIODS,
				'energy'      => Fields::ENERGY,
				'agentRest'   => 'estate-agents',
				'settingsUrl' => admin_url( 'edit.php?post_type=property&page=estatecraft-settings' ),
			)
		);
	}

	/**
	 * Titles of the chosen agents.
	 *
	 * @param int[] $ids Agent IDs.
	 * @return array[]
	 */
	private static function agent_names( array $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$agent = get_post( $id );
			if ( $agent && Agent_Types::AGENT === $agent->post_type ) {
				$out[] = array(
					'id'    => $agent->ID,
					'title' => get_the_title( $agent ),
				);
			}
		}
		return $out;
	}

	/**
	 * Thumbnails for the gallery and floor plan images.
	 *
	 * @param array $values Field values.
	 * @return array id => thumbnail URL.
	 */
	private static function media( array $values ) {
		$ids = isset( $values['gallery'] ) ? $values['gallery'] : array();
		foreach ( isset( $values['floor_plans'] ) ? $values['floor_plans'] : array() as $plan ) {
			$ids[] = $plan['image_id'];
		}
		$out = array();
		foreach ( array_filter( array_unique( $ids ) ) as $id ) {
			$url = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( $url ) {
				$out[ $id ] = $url;
			}
		}
		return $out;
	}
}
