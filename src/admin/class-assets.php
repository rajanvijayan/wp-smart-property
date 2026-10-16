<?php
/**
 * Built script metadata.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Admin;

/**
 * Reads build/{name}.asset.php written by @wordpress/scripts.
 */
final class Assets {

	/**
	 * Dependencies and version of a built entry.
	 *
	 * @param string $name Entry name.
	 * @return array { dependencies, version }
	 */
	public static function asset( $name ) {
		$file = ESTATECRAFT_DIR . 'build/' . $name . '.asset.php';
		if ( file_exists( $file ) ) {
			$asset = include $file;
			if ( is_array( $asset ) ) {
				return $asset;
			}
		}
		return array(
			'dependencies' => array(),
			'version'      => ESTATECRAFT_VERSION,
		);
	}
}
