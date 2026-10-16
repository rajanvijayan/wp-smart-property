<?php
/**
 * Search index: one row per property with the values search and the map filter on.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Search;

use EstateCraft\Frontend\View;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;
use WP_Post;

/**
 * Faceted search and map-bounds queries over post meta would need a join per field; this table keeps
 * them in one indexed row. It is derived data: rebuilt from posts at any time, kept in sync on save,
 * term changes, status changes and deletes.
 */
final class Index {

	/**
	 * Bump when the table changes (dbDelta runs again).
	 */
	const SCHEMA = 2;

	const OPTION = 'estatecraft_index_schema';

	const CRON = 'estatecraft_reindex';

	/**
	 * Properties changed in this request, written once at shutdown.
	 *
	 * @var int[]
	 */
	private static $queue = array();

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'estatecraft_index';
	}

	/**
	 * Hook into post changes.
	 */
	public static function register() {
		add_action( 'save_post_' . Property_Type::POST_TYPE, array( self::class, 'on_save' ), 20, 2 );
		add_action( 'set_object_terms', array( self::class, 'on_terms' ), 20 );
		add_action( 'updated_post_meta', array( self::class, 'on_meta' ), 20, 3 );
		add_action( 'added_post_meta', array( self::class, 'on_meta' ), 20, 3 );
		add_action( 'deleted_post', array( self::class, 'remove' ) );
		add_action( 'transition_post_status', array( self::class, 'on_status' ), 20, 3 );
		add_action( 'shutdown', array( self::class, 'flush' ) );
		add_action( self::CRON, array( self::class, 'rebuild_batch' ) );
	}

	/**
	 * Queue a property for re-indexing.
	 *
	 * @param int $post_id Property ID.
	 */
	public static function queue( $post_id ) {
		self::$queue[ (int) $post_id ] = true;
	}

	/**
	 * Write queued rows. A save touches many fields; each property is written once.
	 */
	public static function flush() {
		$ids         = array_keys( self::$queue );
		self::$queue = array();
		foreach ( $ids as $id ) {
			self::sync( $id );
		}
	}

	/**
	 * Create or update the table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
			post_id bigint(20) unsigned NOT NULL,
			post_status varchar(20) NOT NULL DEFAULT '',
			listing varchar(200) NOT NULL DEFAULT '',
			price decimal(15,2) NOT NULL DEFAULT 0,
			price_period varchar(10) NOT NULL DEFAULT '',
			bedrooms smallint(5) unsigned NOT NULL DEFAULT 0,
			bathrooms decimal(4,1) NOT NULL DEFAULT 0,
			area decimal(12,2) NOT NULL DEFAULT 0,
			land_area decimal(14,2) NOT NULL DEFAULT 0,
			year_built smallint(5) unsigned NOT NULL DEFAULT 0,
			lat decimal(10,7) NOT NULL DEFAULT 0,
			lng decimal(10,7) NOT NULL DEFAULT 0,
			featured tinyint(1) unsigned NOT NULL DEFAULT 0,
			author bigint(20) unsigned NOT NULL DEFAULT 0,
			published datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			expires datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (post_id),
			KEY status_price (post_status,price),
			KEY geo (lat,lng),
			KEY bedrooms (bedrooms),
			KEY bathrooms (bathrooms),
			KEY area (area),
			KEY featured (featured)
			) $charset;"
		);
		update_option( self::OPTION, self::SCHEMA, true );
	}

	/**
	 * Create the table on sites updated without activation.
	 */
	public static function maybe_install() {
		if ( (int) get_option( self::OPTION, 0 ) < self::SCHEMA ) {
			self::install();
			// Sites updated in place have properties the index has not seen yet.
			self::schedule_rebuild();
		}
	}

	/**
	 * Rebuild the whole index in the background, 200 properties per cron run.
	 */
	public static function schedule_rebuild() {
		wp_clear_scheduled_hook( self::CRON );
		wp_schedule_single_event( time(), self::CRON, array( 0 ) );
	}

	/**
	 * One background rebuild batch; schedules the next.
	 *
	 * @param int $offset First property.
	 */
	public static function rebuild_batch( $offset = 0 ) {
		$offset = absint( $offset );
		if ( self::rebuild( $offset, 200 ) >= 200 ) {
			wp_schedule_single_event( time(), self::CRON, array( $offset + 200 ) );
		}
	}

	/**
	 * Row values for a property.
	 *
	 * @param WP_Post $post Property.
	 * @return array
	 */
	public static function row( WP_Post $post ) {
		$listing = wp_get_object_terms( $post->ID, 'property_status', array( 'fields' => 'slugs' ) );
		$expires = Fields::get( $post->ID, 'expires' );
		// Searches and maps only ever see the approximate area of a property with a hidden address.
		$point = View::map_point( $post->ID );
		return array(
			'post_id'      => $post->ID,
			'post_status'  => $post->post_status,
			'listing'      => is_array( $listing ) ? implode( ',', $listing ) : '',
			'price'        => Fields::get( $post->ID, 'price' ),
			'price_period' => Fields::get( $post->ID, 'price_period' ),
			'bedrooms'     => Fields::get( $post->ID, 'bedrooms' ),
			'bathrooms'    => Fields::get( $post->ID, 'bathrooms' ),
			'area'         => Fields::get( $post->ID, 'area' ),
			'land_area'    => Fields::get( $post->ID, 'land_area' ),
			'year_built'   => min( 65535, Fields::get( $post->ID, 'year_built' ) ),
			'lat'          => $point ? $point['lat'] : 0,
			'lng'          => $point ? $point['lng'] : 0,
			'featured'     => Fields::get( $post->ID, 'featured' ) ? 1 : 0,
			'author'       => (int) $post->post_author,
			'published'    => '0000-00-00 00:00:00' !== $post->post_date_gmt ? $post->post_date_gmt : $post->post_date,
			'expires'      => '' !== $expires ? $expires . ( strlen( $expires ) === 10 ? ' 00:00:00' : '' ) : '0000-00-00 00:00:00',
		);
	}

	/**
	 * Write the row for one property (or remove it when the post is gone).
	 *
	 * @param int $post_id Property ID.
	 */
	public static function sync( $post_id ) {
		global $wpdb;
		$post = get_post( $post_id );
		if ( ! $post || Property_Type::POST_TYPE !== $post->post_type || in_array( $post->post_status, array( 'auto-draft', 'inherit' ), true ) ) {
			self::remove( $post_id );
			return;
		}
		$wpdb->replace( self::table(), self::row( $post ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- derived index table.
	}

	/**
	 * Remove a row.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function remove( $post_id ) {
		global $wpdb;
		unset( self::$queue[ (int) $post_id ] );
		$wpdb->delete( self::table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- derived index table.
	}

	/**
	 * Save hook.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::queue( $post->ID );
	}

	/**
	 * Term hook.
	 *
	 * @param int $object_id Object ID.
	 */
	public static function on_terms( $object_id ) {
		if ( Property_Type::POST_TYPE === get_post_type( $object_id ) ) {
			self::queue( $object_id );
		}
	}

	/**
	 * Meta hook: only our fields change the row.
	 *
	 * @param int    $meta_id   Meta ID.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Key.
	 */
	public static function on_meta( $meta_id, $object_id, $meta_key ) {
		if ( 0 === strpos( $meta_key, Fields::PREFIX ) && Property_Type::POST_TYPE === get_post_type( $object_id ) ) {
			self::queue( $object_id );
		}
	}

	/**
	 * Status hook (trash, publish, unpublish without a full save).
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function on_status( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status && Property_Type::POST_TYPE === $post->post_type ) {
			self::queue( $post->ID );
		}
	}

	/**
	 * Rebuild part of the index from posts.
	 *
	 * @param int $offset First property (by ID order).
	 * @param int $limit  Batch size.
	 * @return int Properties indexed in this batch.
	 */
	public static function rebuild( $offset = 0, $limit = 200 ) {
		global $wpdb;
		if ( 0 === $offset ) {
			$wpdb->query( 'TRUNCATE TABLE ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		}
		$ids = get_posts(
			array(
				'post_type'        => Property_Type::POST_TYPE,
				'post_status'      => 'any',
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'offset'           => $offset,
				'numberposts'      => $limit,
				'suppress_filters' => false,
			)
		);
		foreach ( $ids as $id ) {
			self::sync( $id );
		}
		return count( $ids );
	}

	/**
	 * Number of rows.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
	}

	/**
	 * Drop the table (uninstall).
	 */
	public static function uninstall() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall.
		delete_option( self::OPTION );
	}
}
