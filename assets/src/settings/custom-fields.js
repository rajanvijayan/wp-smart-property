/**
 * Custom property fields builder.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Select, Text, TextArea, Toggle } from '../common/controls';

const TYPES = [
	{ value: 'text', label: __( 'Text', 'wp-smart-property' ) },
	{ value: 'number', label: __( 'Number', 'wp-smart-property' ) },
	{ value: 'select', label: __( 'Choice list', 'wp-smart-property' ) },
	{ value: 'checkbox', label: __( 'Yes / no', 'wp-smart-property' ) },
	{ value: 'date', label: __( 'Date', 'wp-smart-property' ) },
];

/**
 * The key a new field gets from its label (the same rules as the server).
 *
 * @param {string} label Field label.
 * @return {string} Key.
 */
export const keyFromLabel = ( label ) =>
	label
		.toLowerCase()
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.replace( /[^a-z0-9]+/g, '_' )
		.replace( /^_+|_+$/g, '' )
		.slice( 0, 40 );

export default function CustomFields( { fields, savedKeys, onChange } ) {
	const update = ( index, changes ) =>
		onChange(
			fields.map( ( field, i ) =>
				i === index ? { ...field, ...changes } : field
			)
		);
	const move = ( from, to ) => {
		const next = [ ...fields ];
		const [ field ] = next.splice( from, 1 );
		next.splice( to, 0, field );
		onChange( next );
	};

	return (
		<section className="estatecraft-settings__section">
			<h2>{ __( 'Custom fields', 'wp-smart-property' ) }</h2>
			<p className="description">
				{ __(
					'Extra property details, edited under “More details” on each property. Fields marked as filters can be searched.',
					'wp-smart-property'
				) }
			</p>
			{ fields.map( ( field, index ) => {
				const saved = savedKeys.includes( field.key );
				return (
					<div className="estatecraft-cf" key={ index }>
						<div className="estatecraft-cf__main">
							<Text
								label={ __( 'Label', 'wp-smart-property' ) }
								value={ field.label }
								onChange={ ( label ) =>
									update( index, {
										label,
										...( saved
											? {}
											: { key: keyFromLabel( label ) } ),
									} )
								}
							/>
							<Text
								label={ __( 'Key', 'wp-smart-property' ) }
								help={
									saved
										? __(
												'Saved values use this key, so it cannot change.',
												'wp-smart-property'
										  )
										: __(
												'Letters, numbers and underscores.',
												'wp-smart-property'
										  )
								}
								readOnly={ saved }
								value={ field.key }
								onChange={ ( key ) =>
									update( index, {
										key: keyFromLabel( key ),
									} )
								}
							/>
							<Select
								label={ __( 'Type', 'wp-smart-property' ) }
								value={ field.type }
								options={ TYPES }
								onChange={ ( type ) =>
									update( index, { type } )
								}
							/>
						</div>
						{ field.type === 'select' && (
							<TextArea
								label={ __( 'Choices', 'wp-smart-property' ) }
								help={ __(
									'One per line.',
									'wp-smart-property'
								) }
								rows={ 4 }
								value={ ( field.options || [] ).join( '\n' ) }
								onChange={ ( text ) =>
									update( index, {
										options: text.split( '\n' ),
									} )
								}
							/>
						) }
						<div className="estatecraft-cf__footer">
							<Toggle
								label={ __(
									'Show on the property page',
									'wp-smart-property'
								) }
								checked={ field.show }
								onChange={ ( show ) =>
									update( index, { show } )
								}
							/>
							<Toggle
								label={ __(
									'Use as a search filter',
									'wp-smart-property'
								) }
								checked={ field.search }
								onChange={ ( search ) =>
									update( index, { search } )
								}
							/>
							<div className="estatecraft-cf__actions">
								<Button
									size="small"
									icon="arrow-up-alt2"
									label={ __(
										'Move up',
										'wp-smart-property'
									) }
									disabled={ index === 0 }
									onClick={ () => move( index, index - 1 ) }
								/>
								<Button
									size="small"
									icon="arrow-down-alt2"
									label={ __(
										'Move down',
										'wp-smart-property'
									) }
									disabled={ index === fields.length - 1 }
									onClick={ () => move( index, index + 1 ) }
								/>
								<Button
									size="small"
									icon="trash"
									isDestructive
									label={ __(
										'Remove field',
										'wp-smart-property'
									) }
									onClick={ () =>
										onChange(
											fields.filter(
												( f, i ) => i !== index
											)
										)
									}
								/>
							</div>
						</div>
					</div>
				);
			} ) }
			<Button
				variant="secondary"
				disabled={ fields.length >= 50 }
				onClick={ () =>
					onChange( [
						...fields,
						{
							key: '',
							label: '',
							type: 'text',
							options: [],
							search: false,
							show: true,
						},
					] )
				}
			>
				{ __( 'Add field', 'wp-smart-property' ) }
			</Button>
			<p className="description">
				{ __(
					'Removing a field hides it everywhere; values already saved on properties are kept.',
					'wp-smart-property'
				) }
			</p>
		</section>
	);
}
