<?php
/**
 * Background geocoding of addresses without a map pin.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Search;

use EstateCraft\Frontend\View;
use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;
use EstateCraft\Settings;
use WP_Error;

/**
 * Properties with an address but no pin are marked "pending" (the 2.0 upgrade, new properties, or
 * the Tools button). A cron job every five minutes looks up a few of them, one per second, as the
 * Nominatim usage policy asks. Pins placed by hand are never changed.
 */
final class Geocode_Queue {

	const CRON = 'estatecraft_geocode';

	const INTERVAL = 'estatecraft_five_minutes';

	/**
	 * Addresses looked up per run.
	 */
	const BATCH = 10;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_filter( 'cron_schedules', array( self::class, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- five minutes, see schedules().
		add_action( self::CRON, array( self::class, 'run' ) );
		add_action( 'save_post_' . Property_Type::POST_TYPE, array( self::class, 'on_save' ), 30 );
		// Checked on admin pages (the 2.0 upgrade queues addresses) and after saves, not on every visit.
		add_action( 'admin_init', array( self::class, 'schedule' ) );
	}

	/**
	 * Five-minute schedule.
	 *
	 * @param array[] $schedules Schedules.
	 * @return array[]
	 */
	public static function schedules( $schedules ) {
		$schedules[ self::INTERVAL ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'wp-smart-property' ),
		);
		return $schedules;
	}

	/**
	 * Keep the job scheduled while there is work and lookups are on; remove it otherwise.
	 */
	public static function schedule() {
		$scheduled = wp_next_scheduled( self::CRON );
		$wanted    = Settings::get( 'maps' )['geocoding'] && self::pending_count() > 0;
		if ( $wanted && ! $scheduled ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::INTERVAL, self::CRON );
		} elseif ( ! $wanted && $scheduled ) {
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	/**
	 * Mark a saved property pending when it has an address but no pin.
	 *
	 * @param int $post_id Property ID.
	 */
	public static function on_save( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( self::needs_pin( $post_id ) && '' === Fields::get( $post_id, 'geocode' ) ) {
			Fields::set( $post_id, 'geocode', 'pending' );
			self::schedule();
		}
	}

	/**
	 * Whether a property has an address to look up and no pin.
	 *
	 * @param int $post_id Property ID.
	 * @return bool
	 */
	public static function needs_pin( $post_id ) {
		return '' !== self::query( $post_id ) && ! (float) Fields::get( $post_id, 'lat' ) && ! (float) Fields::get( $post_id, 'lng' );
	}

	/**
	 * Address text to look up: street, postcode and location.
	 *
	 * @param int $post_id Property ID.
	 * @return string
	 */
	public static function query( $post_id ) {
		$street = trim( (string) Fields::get( $post_id, 'street' ) );
		if ( '' === $street ) {
			return '';
		}
		$parts = array_filter( array( $street, trim( (string) Fields::get( $post_id, 'postcode' ) ) ) );
		foreach ( array_filter( explode( ', ', View::location( $post_id ) ) ) as $name ) {
			if ( false === stripos( implode( ' ', $parts ), $name ) ) {
				$parts[] = $name;
			}
		}
		return implode( ', ', $parts );
	}

	/**
	 * Pending properties.
	 *
	 * @param int $limit Most to return.
	 * @return int[]
	 */
	public static function pending( $limit = self::BATCH ) {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => Property_Type::POST_TYPE,
					'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
					'fields'      => 'ids',
					'numberposts' => $limit,
					'orderby'     => 'ID',
					'order'       => 'ASC',
					'meta_key'    => Fields::PREFIX . 'geocode', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'  => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		);
	}

	/**
	 * Number of pending properties.
	 *
	 * @return int
	 */
	public static function pending_count() {
		$query = new \WP_Query(
			array(
				'post_type'              => Property_Type::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft', 'future', 'private' ),
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => Fields::PREFIX . 'geocode', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Queue every property with an address and no pin, failed ones included (the Tools button).
	 *
	 * @return int Properties queued.
	 */
	public static function queue_missing() {
		$queued = 0;
		$ids    = get_posts(
			array(
				'post_type'   => Property_Type::POST_TYPE,
				'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ),
				'fields'      => 'ids',
				'numberposts' => -1,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-off tools action.
					array(
						'key'     => Fields::PREFIX . 'street',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			if ( self::needs_pin( $id ) && 'manual' !== Fields::get( $id, 'geocode' ) && 'pending' !== Fields::get( $id, 'geocode' ) ) {
				Fields::set( $id, 'geocode', 'pending' );
				++$queued;
			}
		}
		self::schedule();
		return $queued;
	}

	/**
	 * Look up a batch of pending addresses.
	 *
	 * @param callable|null $wait Called between lookups (sleeps one second; tests pass a no-op).
	 * @return array { done, failed, left }
	 */
	public static function run( $wait = null ) {
		$result = array(
			'done'   => 0,
			'failed' => 0,
			'left'   => 0,
		);
		if ( ! Settings::get( 'maps' )['geocoding'] ) {
			return $result;
		}
		$wait = is_callable( $wait ) ? $wait : static function () {
			sleep( 1 );
		};
		foreach ( self::pending() as $index => $id ) {
			if ( $index > 0 ) {
				// The site-wide limit allows one request per second.
				call_user_func( $wait );
			}
			if ( ! self::needs_pin( $id ) ) {
				// Pinned by hand meanwhile, or the address was removed.
				Fields::set( $id, 'geocode', (float) Fields::get( $id, 'lat' ) ? 'manual' : '' );
				continue;
			}
			$found = Geocoder::lookup( self::query( $id ) );
			if ( $found instanceof WP_Error ) {
				if ( in_array( $found->get_error_code(), array( 'estatecraft_geocode_none', 'estatecraft_geocode_empty' ), true ) ) {
					Fields::set( $id, 'geocode', 'failed' );
					++$result['failed'];
					continue;
				}
				// Busy or unreachable: try again on the next run.
				break;
			}
			Fields::set( $id, 'lat', $found['lat'] );
			Fields::set( $id, 'lng', $found['lng'] );
			Fields::set( $id, 'geocode', 'done' );
			++$result['done'];
		}
		$result['left'] = self::pending_count();
		if ( ! $result['left'] ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		return $result;
	}
}
