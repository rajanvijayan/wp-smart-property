/**
 * Price and area formatting, matching EstateCraft\Format in PHP.
 */
import { __, sprintf } from '@wordpress/i18n';

export function formatNumber( value, decimals, point, thousands ) {
	const fixed = Math.abs( Number( value ) || 0 ).toFixed( decimals );
	const [ whole, fraction ] = fixed.split( '.' );
	const grouped = whole.replace( /\B(?=(\d{3})+(?!\d))/g, thousands );
	return fraction ? grouped + point + fraction : grouped;
}

export function formatPrice( amount, period, onRequest, general ) {
	if ( onRequest || ! Number( amount ) ) {
		return __( 'Price on request', 'wp-smart-property' );
	}
	const number = formatNumber(
		amount,
		general.decimals,
		general.decimal_point,
		general.thousands
	);
	const price = general.symbol_after
		? number + ' ' + general.symbol
		: general.symbol + number;
	const labels = {
		day: __( 'day', 'wp-smart-property' ),
		week: __( 'week', 'wp-smart-property' ),
		month: __( 'month', 'wp-smart-property' ),
		year: __( 'year', 'wp-smart-property' ),
	};
	if ( ! labels[ period ] ) {
		return price;
	}
	return sprintf(
		/* translators: 1: price, 2: period, e.g. month */
		__( '%1$s / %2$s', 'wp-smart-property' ),
		price,
		labels[ period ]
	);
}

export const unitLabel = ( unit ) =>
	unit === 'm2'
		? __( 'm²', 'wp-smart-property' )
		: __( 'sq ft', 'wp-smart-property' );
