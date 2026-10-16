<?php
/**
 * Moves WP Smart Property 2.0 data to the EstateCraft data model.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Legacy;

use EstateCraft\Properties\Fields;
use EstateCraft\Properties\Property_Type;

/**
 * Runs once per site, in batches, and can resume. Old terms and meta are never changed or deleted.
 *
 * - "Property Type" → property_type and "Property Category" → property_category (same slugs, so the
 *   old archive URLs keep working).
 * - "Country" and "City" → property_location; a city goes under its country when all of its
 *   properties are in that one country.
 * - "Bed Room" and "Bath Room" term names → the bedrooms and bathrooms fields ("3", "2 Bedrooms",
 *   "Three", "1.5"); names that are not a number become features.
 * - property_address → street (geocoding queued); admin_notes → private notes.
 */
final class Migrator {

	const OPTION = 'estatecraft_migration';
	const MAP    = 'estatecraft_legacy_terms';
	const LOCK   = 'estatecraft_migrating';
	const CRON   = 'estatecraft_migrate';

	/**
	 * Version of the migration. Bump to run a new step on sites that already migrated.
	 */
	const VERSION = 1;

	/**
	 * Properties per batch.
	 */
	const BATCH = 100;

	/**
	 * Current state.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'version' => 0,
				'stage'   => 'terms',
				'offset'  => 0,
				'report'  => array(
					'terms'      => 0,
					'properties' => 0,
					'bedrooms'   => 0,
					'bathrooms'  => 0,
					'features'   => array(),
					'addresses'  => 0,
				),
			)
		);
	}

	/**
	 * Whether this site still has a migration to finish.
	 *
	 * @return bool
	 */
	public static function pending() {
		return (int) self::state()['version'] < self::VERSION;
	}

	/**
	 * Hooks: continue unfinished migrations in the background.
	 */
	public static function register() {
		add_action( self::CRON, array( self::class, 'step' ) );
	}

	/**
	 * Start or continue on load: one batch now, the rest in the background.
	 */
	public static function maybe_run() {
		if ( ! self::pending() ) {
			return;
		}
		if ( ! self::has_legacy_data() ) {
			self::finish( self::state() );
			return;
		}
		self::step();
	}

	/**
	 * Run one batch (terms first, then properties).
	 *
	 * @return bool True when the migration is complete.
	 */
	public static function step() {
		if ( ! self::pending() ) {
			return true;
		}
		// One request at a time; a stale lock (fatal error mid-batch) expires after five minutes.
		if ( ! add_option( self::LOCK, time(), '', false ) ) {
			if ( time() - (int) get_option( self::LOCK ) < 300 ) {
				return false;
			}
			update_option( self::LOCK, time(), false );
		}

		$state = self::state();
		if ( 'terms' === $state['stage'] ) {
			$state['report']['terms'] = self::migrate_terms();
			$state['stage']           = 'properties';
			$state['offset']          = 0;
		} else {
			$ids = get_posts(
				array(
					'post_type'        => Property_Type::POST_TYPE,
					'post_status'      => 'any',
					'fields'           => 'ids',
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'numberposts'      => self::BATCH,
					'offset'           => $state['offset'],
					'suppress_filters' => false,
				)
			);
			foreach ( $ids as $id ) {
				self::migrate_property( $id, $state['report'] );
			}
			$state['offset'] += count( $ids );
			if ( count( $ids ) < self::BATCH ) {
				delete_option( self::LOCK );
				self::finish( $state );
				return true;
			}
		}

		update_option( self::OPTION, $state, false );
		delete_option( self::LOCK );
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_single_event( time() + 30, self::CRON );
		}
		return false;
	}

	/**
	 * Run to the end (activation, tests, WP-CLI).
	 */
	public static function run_all() {
		$guard = 0;
		while ( ! self::step() && $guard++ < 10000 ) {
			if ( get_option( self::LOCK ) ) {
				break;
			}
		}
	}

	/**
	 * Mark complete.
	 *
	 * @param array $state State.
	 */
	private static function finish( array $state ) {
		$state['version'] = self::VERSION;
		$state['stage']   = 'done';
		update_option( self::OPTION, $state, false );
		wp_clear_scheduled_hook( self::CRON );
		if ( $state['report']['properties'] > 0 ) {
			update_option( 'estatecraft_migration_notice', 1, false );
		}
	}

	/**
	 * Whether any 2.0 data exists.
	 *
	 * @return bool
	 */
	public static function has_legacy_data() {
		global $wpdb;
		list( $type, $category, $city, $country, $beds, $baths ) = array_keys( Legacy_Taxonomies::all() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off upgrade check.
		$terms = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy IN (%s, %s, %s, %s, %s, %s)", $type, $category, $city, $country, $beds, $baths ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off upgrade check.
		$meta = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ('property_address','admin_notes')" );
		return $terms > 0 || $meta > 0;
	}

	/**
	 * Legacy term ID => new term ID.
	 *
	 * @return int[]
	 */
	public static function term_map() {
		$map = get_option( self::MAP, array() );
		return is_array( $map ) ? array_map( 'intval', $map ) : array();
	}

	/**
	 * Copy the terms of the 2.0 taxonomies into the new ones.
	 *
	 * @return int Terms created or matched.
	 */
	private static function migrate_terms() {
		$map   = self::term_map();
		$pairs = array(
			Legacy_Taxonomies::TYPE     => 'property_type',
			Legacy_Taxonomies::CATEGORY => 'property_category',
			Legacy_Taxonomies::COUNTRY  => 'property_location',
			Legacy_Taxonomies::CITY     => 'property_location',
		);
		$count = 0;
		foreach ( $pairs as $legacy => $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $legacy,
					'hide_empty' => false,
					'orderby'    => 'parent',
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				if ( isset( $map[ $term->term_id ] ) && term_exists( $map[ $term->term_id ], $taxonomy ) ) {
					continue;
				}
				$parent = 0;
				if ( Legacy_Taxonomies::CITY === $legacy ) {
					$parent = self::city_country( $term, $map );
				} elseif ( $term->parent && isset( $map[ $term->parent ] ) ) {
					$parent = $map[ $term->parent ];
				}
				$new_id = self::ensure_term( $term, $taxonomy, $parent );
				if ( $new_id ) {
					$map[ $term->term_id ] = $new_id;
					++$count;
				}
			}
		}
		update_option( self::MAP, $map, false );
		return $count;
	}

	/**
	 * The one country all properties of a city are in, if any.
	 *
	 * @param \WP_Term $city City term.
	 * @param int[]    $map  Term map so far.
	 * @return int New location term ID, or 0.
	 */
	private static function city_country( \WP_Term $city, array $map ) {
		$objects = get_objects_in_term( $city->term_id, Legacy_Taxonomies::CITY );
		if ( is_wp_error( $objects ) || ! $objects ) {
			return 0;
		}
		$countries = wp_get_object_terms( $objects, Legacy_Taxonomies::COUNTRY, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $countries ) ) {
			return 0;
		}
		$countries = array_unique( array_map( 'intval', $countries ) );
		return 1 === count( $countries ) && isset( $map[ $countries[0] ] ) ? $map[ $countries[0] ] : 0;
	}

	/**
	 * Find or create the matching term in a new taxonomy.
	 *
	 * @param \WP_Term $term     Legacy term.
	 * @param string   $taxonomy New taxonomy.
	 * @param int      $parent_id Parent term ID.
	 * @return int Term ID, or 0.
	 */
	private static function ensure_term( \WP_Term $term, $taxonomy, $parent_id ) {
		$existing = get_term_by( 'slug', $term->slug, $taxonomy );
		if ( $existing && (int) $existing->parent === (int) $parent_id ) {
			return (int) $existing->term_id;
		}
		$args = array(
			'parent'      => $parent_id,
			'description' => $term->description,
		);
		// Keep the old slug (and so the old URL) unless the new taxonomy already uses it elsewhere.
		if ( ! $existing ) {
			$args['slug'] = $term->slug;
		}
		$result = wp_insert_term( $term->name, $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			$id = $result->get_error_data( 'term_exists' );
			return $id ? (int) $id : 0;
		}
		return (int) $result['term_id'];
	}

	/**
	 * Move one property's terms and fields.
	 *
	 * @param int   $post_id Property ID.
	 * @param array $report  Report (updated).
	 */
	private static function migrate_property( $post_id, array &$report ) {
		$map     = self::term_map();
		$changed = false;

		$by_taxonomy = array();
		foreach ( array(
			Legacy_Taxonomies::TYPE     => 'property_type',
			Legacy_Taxonomies::CATEGORY => 'property_category',
			Legacy_Taxonomies::COUNTRY  => 'property_location',
			Legacy_Taxonomies::CITY     => 'property_location',
		) as $legacy => $taxonomy ) {
			$ids = wp_get_object_terms( $post_id, $legacy, array( 'fields' => 'ids' ) );
			foreach ( is_wp_error( $ids ) ? array() : $ids as $id ) {
				if ( isset( $map[ $id ] ) ) {
					$by_taxonomy[ $taxonomy ][] = $map[ $id ];
				}
			}
		}
		foreach ( $by_taxonomy as $taxonomy => $ids ) {
			wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), $taxonomy, true );
			$changed = true;
		}

		foreach ( array(
			'bedrooms'  => Legacy_Taxonomies::BEDS,
			'bathrooms' => Legacy_Taxonomies::BATHS,
		) as $field => $legacy ) {
			$names = wp_get_object_terms( $post_id, $legacy, array( 'fields' => 'names' ) );
			if ( is_wp_error( $names ) || ! $names ) {
				continue;
			}
			$best = null;
			foreach ( $names as $name ) {
				$count = self::parse_count( $name, 'bathrooms' === $field );
				if ( null === $count ) {
					wp_set_object_terms( $post_id, $name, 'property_feature', true );
					$report['features'][ $name ] = true;
				} else {
					$best = null === $best ? $count : max( $best, $count );
				}
			}
			if ( null !== $best && ! Fields::get( $post_id, $field ) ) {
				Fields::set( $post_id, $field, $best );
				++$report[ $field ];
				$changed = true;
			}
		}

		$address = trim( (string) get_post_meta( $post_id, 'property_address', true ) );
		if ( '' !== $address && '' === Fields::get( $post_id, 'street' ) ) {
			Fields::set( $post_id, 'street', preg_replace( '/\s*[\r\n]+\s*/', ', ', $address ) );
			if ( ! Fields::get( $post_id, 'lat' ) && ! Fields::get( $post_id, 'lng' ) ) {
				Fields::set( $post_id, 'geocode', 'pending' );
			}
			++$report['addresses'];
			$changed = true;
		}
		$notes = trim( (string) get_post_meta( $post_id, 'admin_notes', true ) );
		if ( '' !== $notes && '' === Fields::get( $post_id, 'private_notes' ) ) {
			Fields::set( $post_id, 'private_notes', $notes );
			$changed = true;
		}
		if ( $changed ) {
			++$report['properties'];
		}
	}

	/**
	 * A bedroom or bathroom count from a 2.0 term name.
	 *
	 * @param string $name      Term name, e.g. "3", "2 Bedrooms", "Three", "1.5 baths", "4+".
	 * @param bool   $allow_half Allow halves (bathrooms).
	 * @return float|int|null Count, or null when the name is not a number.
	 */
	public static function parse_count( $name, $allow_half = false ) {
		$name = strtolower( trim( (string) $name ) );
		if ( preg_match( '/(\d+(?:[.,]5)?)/', $name, $m ) ) {
			$value = (float) str_replace( ',', '.', $m[1] );
			return $allow_half ? $value : (int) floor( $value );
		}
		$words = array( 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve' );
		foreach ( $words as $i => $word ) {
			if ( preg_match( '/\b' . $word . '\b/', $name ) ) {
				return $i + 1;
			}
		}
		if ( in_array( $name, array( 'studio', 'none', 'zero' ), true ) ) {
			return 0;
		}
		return null;
	}
}
