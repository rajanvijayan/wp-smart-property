<?php
/**
 * Inline SVG icons.
 *
 * @package EstateCraft
 */

namespace EstateCraft\Frontend;

/**
 * Simple 24×24 line icons drawn for EstateCraft. They take the text colour.
 */
final class Icons {

	/**
	 * Path data by name.
	 *
	 * @return string[]
	 */
	private static function paths() {
		return array(
			'bed'      => 'M3 18v-7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v7M3 15h18M6 9V6h5v3m2 0V6h5v3M3 18v2m18-2v2',
			'bath'     => 'M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-3zm2 0V6a2 2 0 0 1 4 0M7 19l-1 2m11-2 1 2',
			'area'     => 'M4 4h6M4 4v6M20 20h-6m6 0v-6M4 4l6 6m10 10-6-6M20 4h-6m6 0v6M4 20h6m-6 0v-6',
			'land'     => 'M3 20h18M5 20l4-9 3 5 2-3 5 7M15 7a2 2 0 1 0 0-.01',
			'garage'   => 'M3 21V9l9-5 9 5v12M7 21v-8h10v8M7 16h10',
			'calendar' => 'M4 6h16v14H4zM4 10h16M8 3v4m8-4v4',
			'pin'      => 'M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21zm0-9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
			'check'    => 'M5 12.5l4.5 4.5L19 7',
			'phone'    => 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z',
			'mail'     => 'M3 6h18v12H3zM3 7l9 6 9-6',
			'globe'    => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM3 12h18M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9s1-6.5 3.5-9z',
			'share'    => 'M12 15V3m0 0L8 7m4-4 4 4M5 12v7h14v-7',
			'print'    => 'M7 8V3h10v5M7 17H4v-7h16v7h-3M7 14h10v7H7z',
			'home'     => 'M3 11l9-7 9 7M5 9.5V20h14V9.5M10 20v-6h4v6',
			'clock'    => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zm0 4v5l3 2',
			'expand'   => 'M4 9V4h5M20 9V4h-5M4 15v5h5m11-5v5h-5',
			'license'  => 'M4 5h16v14H4zM8 10h8M8 14h5',
			'language' => 'M4 5h9M8.5 3v2M6 5c.5 3 2.5 6 6 8M11 5c-.5 3-2.5 6-6 8m7 7 4-9 4 9m-6.5-3h5',
		);
	}

	/**
	 * Icon markup.
	 *
	 * @param string $name Icon name.
	 * @return string
	 */
	public static function get( $name ) {
		$paths = self::paths();
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="estatecraft-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . esc_attr( $paths[ $name ] ) . '"/></svg>';
	}
}
