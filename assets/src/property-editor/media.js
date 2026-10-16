/**
 * Gallery and floor plans, using the WordPress media library.
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { NumberInput, Text } from '../common/controls';

/**
 * Open the media library.
 *
 * @param {Object}   options          Options.
 * @param {string}   options.title    Frame title.
 * @param {boolean}  options.multiple Allow several images.
 * @param {Function} options.onSelect Called with the chosen attachments.
 */
function pick( { title, multiple, onSelect } ) {
	const frame = window.wp.media( {
		title,
		multiple: multiple ? 'add' : false,
		library: { type: 'image' },
		button: { text: __( 'Use these images', 'wp-smart-property' ) },
	} );
	frame.on( 'select', () =>
		onSelect( frame.state().get( 'selection' ).toJSON() )
	);
	frame.open();
}

const thumbOf = ( attachment ) =>
	attachment.sizes?.thumbnail?.url ||
	attachment.sizes?.medium?.url ||
	attachment.url;

function move( list, from, to ) {
	const next = [ ...list ];
	const [ item ] = next.splice( from, 1 );
	next.splice( to, 0, item );
	return next;
}

export function Gallery( { ids, thumbs, onChange, onThumbs } ) {
	const add = () =>
		pick( {
			title: __( 'Add photos', 'wp-smart-property' ),
			multiple: true,
			onSelect: ( attachments ) => {
				onThumbs(
					Object.fromEntries(
						attachments.map( ( a ) => [ a.id, thumbOf( a ) ] )
					)
				);
				onChange( [
					...ids,
					...attachments
						.map( ( a ) => a.id )
						.filter( ( id ) => ! ids.includes( id ) ),
				] );
			},
		} );

	return (
		<div className="estatecraft-gallery">
			{ ids.length > 0 && (
				<ul className="estatecraft-gallery__list">
					{ ids.map( ( id, index ) => (
						<li key={ id } className="estatecraft-gallery__item">
							{ thumbs[ id ] ? (
								<img src={ thumbs[ id ] } alt="" />
							) : (
								<span className="estatecraft-gallery__missing">
									#{ id }
								</span>
							) }
							{ index === 0 && (
								<span className="estatecraft-gallery__badge">
									{ __( 'First photo', 'wp-smart-property' ) }
								</span>
							) }
							<div className="estatecraft-gallery__actions">
								<Button
									size="small"
									icon="arrow-left-alt2"
									label={ __(
										'Move earlier',
										'wp-smart-property'
									) }
									disabled={ index === 0 }
									onClick={ () =>
										onChange(
											move( ids, index, index - 1 )
										)
									}
								/>
								<Button
									size="small"
									icon="arrow-right-alt2"
									label={ __(
										'Move later',
										'wp-smart-property'
									) }
									disabled={ index === ids.length - 1 }
									onClick={ () =>
										onChange(
											move( ids, index, index + 1 )
										)
									}
								/>
								<Button
									size="small"
									icon="no-alt"
									isDestructive
									label={ __(
										'Remove photo',
										'wp-smart-property'
									) }
									onClick={ () =>
										onChange(
											ids.filter(
												( item ) => item !== id
											)
										)
									}
								/>
							</div>
						</li>
					) ) }
				</ul>
			) }
			<Button variant="secondary" onClick={ add }>
				{ ids.length
					? __( 'Add more photos', 'wp-smart-property' )
					: __( 'Add photos', 'wp-smart-property' ) }
			</Button>
			{ ids.length > 0 && (
				<p className="description">
					{ sprintf(
						/* translators: %d: number of photos */
						__(
							'%d photos. Without a featured image, the first photo is used as the main image.',
							'wp-smart-property'
						),
						ids.length
					) }
				</p>
			) }
		</div>
	);
}

export function FloorPlans( { plans, thumbs, unit, onChange, onThumbs } ) {
	const update = ( index, changes ) =>
		onChange(
			plans.map( ( plan, i ) =>
				i === index ? { ...plan, ...changes } : plan
			)
		);
	const chooseImage = ( index ) =>
		pick( {
			title: __( 'Floor plan image', 'wp-smart-property' ),
			multiple: false,
			onSelect: ( [ attachment ] ) => {
				onThumbs( { [ attachment.id ]: thumbOf( attachment ) } );
				update( index, { image_id: attachment.id } );
			},
		} );

	return (
		<div className="estatecraft-plans">
			{ plans.map( ( plan, index ) => (
				<div className="estatecraft-plans__row" key={ index }>
					<button
						type="button"
						className="estatecraft-plans__image"
						onClick={ () => chooseImage( index ) }
						aria-label={ __(
							'Choose floor plan image',
							'wp-smart-property'
						) }
					>
						{ plan.image_id && thumbs[ plan.image_id ] ? (
							<img src={ thumbs[ plan.image_id ] } alt="" />
						) : (
							__( 'Image', 'wp-smart-property' )
						) }
					</button>
					<div className="estatecraft-plans__fields">
						<Text
							label={ __( 'Title', 'wp-smart-property' ) }
							placeholder={ __(
								'Ground floor',
								'wp-smart-property'
							) }
							value={ plan.title }
							onChange={ ( title ) => update( index, { title } ) }
						/>
						<NumberInput
							label={ sprintf(
								/* translators: %s: area unit */
								__( 'Area (%s)', 'wp-smart-property' ),
								unit
							) }
							value={ plan.area }
							onChange={ ( area ) => update( index, { area } ) }
						/>
						<NumberInput
							label={ __( 'Bedrooms', 'wp-smart-property' ) }
							value={ plan.bedrooms }
							onChange={ ( bedrooms ) =>
								update( index, { bedrooms } )
							}
						/>
						<NumberInput
							label={ __( 'Bathrooms', 'wp-smart-property' ) }
							step={ 0.5 }
							value={ plan.bathrooms }
							onChange={ ( bathrooms ) =>
								update( index, { bathrooms } )
							}
						/>
					</div>
					<Button
						icon="trash"
						isDestructive
						label={ __( 'Remove floor plan', 'wp-smart-property' ) }
						onClick={ () =>
							onChange( plans.filter( ( p, i ) => i !== index ) )
						}
					/>
				</div>
			) ) }
			<Button
				variant="secondary"
				disabled={ plans.length >= 20 }
				onClick={ () =>
					onChange( [
						...plans,
						{
							title: '',
							image_id: 0,
							area: 0,
							bedrooms: 0,
							bathrooms: 0,
						},
					] )
				}
			>
				{ __( 'Add floor plan', 'wp-smart-property' ) }
			</Button>
		</div>
	);
}
