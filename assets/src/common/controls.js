/**
 * Form controls with the current WordPress sizing defaults, shared by the admin apps.
 */
import {
	TextControl,
	TextareaControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';

export function Text( props ) {
	return (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			{ ...props }
			value={ props.value ?? '' }
		/>
	);
}

export function NumberInput( { value, onChange, ...props } ) {
	return (
		<Text
			type="number"
			min={ 0 }
			{ ...props }
			value={ value === 0 || value === '0' ? '' : value }
			placeholder={ props.placeholder ?? '0' }
			onChange={ ( next ) => onChange( next === '' ? 0 : next ) }
		/>
	);
}

export function TextArea( props ) {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			{ ...props }
			value={ props.value ?? '' }
		/>
	);
}

export function Select( props ) {
	return (
		<SelectControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			{ ...props }
		/>
	);
}

export function Toggle( { checked, ...props } ) {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			{ ...props }
			checked={ !! checked }
		/>
	);
}

/**
 * "2026-05-01 14:30" (stored) <-> "2026-05-01T14:30" (datetime-local input).
 *
 * @param {string} value Stored value.
 * @return {string} Input value.
 */
export const toLocalInput = ( value ) =>
	value ? value.slice( 0, 16 ).replace( ' ', 'T' ) : '';

export const fromLocalInput = ( value ) =>
	value ? value.replace( 'T', ' ' ).slice( 0, 16 ) : '';
