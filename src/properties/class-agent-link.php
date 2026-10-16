<?php
/**
 * Which properties an agent lists.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Properties;

/**
 * The agents field is one array; finding an agent's listings in it would mean matching serialized
 * data. A plain meta row per agent (_estatecraft_agent_id, not single) is kept in step with it so
 * agent pages can query by ID.
 */
final class Agent_Link {

	const KEY = '_estatecraft_agent_id';

	const VERSION = 'estatecraft_agent_links';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'added_post_meta', array( self::class, 'on_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( self::class, 'on_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( self::class, 'on_meta' ), 10, 3 );
	}

	/**
	 * Re-sync when the agents field changes.
	 *
	 * @param int|int[] $meta_id   Meta ID(s).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 */
	public static function on_meta( $meta_id, $object_id, $meta_key ) {
		if ( Fields::PREFIX . 'agents' === $meta_key ) {
			self::sync( (int) $object_id );
		}
		unset( $meta_id );
	}

	/**
	 * Write the per-agent rows for a property.
	 *
	 * @param int $post_id Property ID.
	 */
	public static function sync( $post_id ) {
		$want = Fields::get( $post_id, 'agents' );
		$want = is_array( $want ) ? array_map( 'intval', $want ) : array();
		$have = array_map( 'intval', get_post_meta( $post_id, self::KEY, false ) );
		foreach ( array_diff( $have, $want ) as $id ) {
			delete_post_meta( $post_id, self::KEY, $id );
		}
		foreach ( array_diff( $want, $have ) as $id ) {
			add_post_meta( $post_id, self::KEY, $id );
		}
	}

	/**
	 * Create the rows for properties saved before they existed (once).
	 */
	public static function maybe_backfill() {
		if ( get_option( self::VERSION ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'   => Property_Type::POST_TYPE,
				'post_status' => 'any',
				'fields'      => 'ids',
				'numberposts' => -1,
				'meta_key'    => Fields::PREFIX . 'agents', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-time backfill.
			)
		);
		foreach ( $ids as $id ) {
			self::sync( $id );
		}
		update_option( self::VERSION, 1, true );
	}
}
