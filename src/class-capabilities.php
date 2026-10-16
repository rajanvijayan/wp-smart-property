<?php
/**
 * Roles and capabilities.
 *
 * @package EstateCraft
 */

namespace EstateCraft;

/**
 * Properties, agents and inquiries have their own capabilities, mapped per post. Administrators,
 * editors and estate managers manage everything. Authors and contributors keep what they could do
 * in 2.0 (where properties used post capabilities). Estate agents manage their own listings; whether
 * they publish without review is a portal setting.
 */
final class Capabilities {

	/**
	 * Bump to re-apply capabilities on existing sites.
	 */
	const VERSION = 1;

	const OPTION = 'estatecraft_caps_version';

	/**
	 * Primitive capabilities of a post type with plural capability name $plural.
	 *
	 * @param string $plural Plural, e.g. estate_properties.
	 * @return string[] Grouped: own, publish, others.
	 */
	private static function post_caps( $plural ) {
		return array(
			'own'     => array( "edit_$plural", "delete_$plural", "edit_published_$plural", "delete_published_$plural" ),
			'publish' => array( "publish_$plural" ),
			'others'  => array( "edit_others_$plural", "delete_others_$plural", "read_private_$plural", "edit_private_$plural", "delete_private_$plural" ),
		);
	}

	/**
	 * Capabilities per role.
	 *
	 * @return array[] role => capabilities.
	 */
	public static function grants() {
		$property    = self::post_caps( 'estate_properties' );
		$agent       = self::post_caps( 'estate_agents' );
		$inquiry     = self::post_caps( 'estate_inquiries' );
		$all         = array_merge(
			$property['own'],
			$property['publish'],
			$property['others'],
			$agent['own'],
			$agent['publish'],
			$agent['others'],
			$inquiry['own'],
			$inquiry['publish'],
			$inquiry['others'],
			array( 'manage_estate_terms', 'manage_estatecraft' )
		);
		$own_publish = array_merge( $property['own'], $property['publish'] );

		return array(
			'administrator'  => $all,
			'editor'         => array_diff( $all, array( 'manage_estatecraft' ) ),
			'estate_manager' => array_diff( $all, array( 'manage_estatecraft' ) ),
			'author'         => $own_publish,
			'estate_agent'   => $property['own'],
			'contributor'    => array( 'edit_estate_properties', 'delete_estate_properties' ),
		);
	}

	/**
	 * Create the roles and grant capabilities.
	 */
	public static function install() {
		if ( ! get_role( 'estate_manager' ) ) {
			add_role(
				'estate_manager',
				__( 'Estate manager', 'wp-smart-property' ),
				array(
					'read'         => true,
					'upload_files' => true,
				)
			);
		}
		if ( ! get_role( 'estate_agent' ) ) {
			add_role(
				'estate_agent',
				__( 'Estate agent', 'wp-smart-property' ),
				array(
					'read'         => true,
					'upload_files' => true,
				)
			);
		}
		foreach ( self::grants() as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
		update_option( self::OPTION, self::VERSION );
	}

	/**
	 * Re-apply after an update (activation hooks do not run on updates).
	 */
	public static function maybe_install() {
		if ( (int) get_option( self::OPTION, 0 ) < self::VERSION ) {
			self::install();
		}
	}

	/**
	 * Remove the roles and capabilities (uninstall).
	 */
	public static function uninstall() {
		foreach ( self::grants() as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $caps as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
		remove_role( 'estate_manager' );
		remove_role( 'estate_agent' );
		delete_option( self::OPTION );
	}
}
