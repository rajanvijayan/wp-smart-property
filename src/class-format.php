<?php
/**
 * Price and area formatting.
 *
 * @package EstateCraft
 */

namespace EstateCraft;

/**
 * Uses the currency and unit settings.
 */
final class Format {

	/**
	 * Price with currency symbol and rental period, e.g. "$1,250,000" or "£1,200 / month".
	 *
	 * @param float  $amount       Amount.
	 * @param string $period       '', day, week, month or year.
	 * @param bool   $on_request   Price on request.
	 * @return string
	 */
	public static function price( $amount, $period = '', $on_request = false ) {
		if ( $on_request || ! $amount ) {
			return __( 'Price on request', 'wp-smart-property' );
		}
		$g      = Settings::get( 'general' );
		$number = number_format( (float) $amount, $g['decimals'], $g['decimal_point'], $g['thousands'] );
		$price  = $g['symbol_after'] ? $number . ' ' . $g['symbol'] : $g['symbol'] . $number;
		$labels = array(
			'day'   => __( 'day', 'wp-smart-property' ),
			'week'  => __( 'week', 'wp-smart-property' ),
			'month' => __( 'month', 'wp-smart-property' ),
			'year'  => __( 'year', 'wp-smart-property' ),
		);
		if ( isset( $labels[ $period ] ) ) {
			/* translators: 1: price, 2: period, e.g. month */
			$price = sprintf( __( '%1$s / %2$s', 'wp-smart-property' ), $price, $labels[ $period ] );
		}
		return $price;
	}

	/**
	 * Area with unit, e.g. "1,200 sq ft" or "85 m²".
	 *
	 * @param float $value Area.
	 * @return string
	 */
	public static function area( $value ) {
		if ( ! $value ) {
			return '';
		}
		$g = Settings::get( 'general' );
		return number_format( (float) $value, floor( $value ) == $value ? 0 : 1, $g['decimal_point'], $g['thousands'] ) . ' ' . self::unit(); // phpcs:ignore Universal.Operators.StrictComparisons -- comparing a float to its floor.
	}

	/**
	 * Area unit label.
	 *
	 * @return string
	 */
	public static function unit() {
		return 'm2' === Settings::get( 'general' )['area_unit'] ? __( 'm²', 'wp-smart-property' ) : __( 'sq ft', 'wp-smart-property' );
	}
}
