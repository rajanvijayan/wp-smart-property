/**
 * Property details editor. Runs inside a meta box, so it works in the block and classic editors:
 * every change is written as JSON to a hidden field that is saved with the post.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, TabPanel } from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import {
	NumberInput,
	Select,
	Text,
	TextArea,
	Toggle,
	fromLocalInput,
	toLocalInput,
} from '../common/controls';
import { formatPrice, unitLabel } from '../common/format';
import Agents from './agents';
import MapPicker from './map-picker';
import { FloorPlans, Gallery } from './media';
import './index.scss';

const config = window.estateCraftEditor || {};

const PERIOD_LABELS = {
	'': __( 'One-off price (sale)', 'wp-smart-property' ),
	day: __( 'Per day', 'wp-smart-property' ),
	week: __( 'Per week', 'wp-smart-property' ),
	month: __( 'Per month', 'wp-smart-property' ),
	year: __( 'Per year', 'wp-smart-property' ),
};

const GEOCODE_LABELS = {
	'': __( 'No pin yet', 'wp-smart-property' ),
	pending: __( 'Waiting for automatic address lookup', 'wp-smart-property' ),
	done: __( 'Pin found from the address', 'wp-smart-property' ),
	failed: __( 'Address lookup found nothing', 'wp-smart-property' ),
	manual: __( 'Pin placed by hand', 'wp-smart-property' ),
};

function Field( { children, wide } ) {
	return (
		<div className={ 'estatecraft-field' + ( wide ? ' is-wide' : '' ) }>
			{ children }
		</div>
	);
}

function Grid( { children } ) {
	return <div className="estatecraft-grid">{ children }</div>;
}

function Listing( { values, set } ) {
	const preview = formatPrice(
		values.price,
		values.price_period,
		values.price_on_request,
		config.general
	);
	return (
		<>
			<Grid>
				<Field>
					<NumberInput
						label={ sprintf(
							/* translators: %s: currency code */
							__( 'Price (%s)', 'wp-smart-property' ),
							config.general.currency
						) }
						step="any"
						value={ values.price }
						onChange={ ( price ) => set( { price } ) }
						help={ preview }
					/>
				</Field>
				<Field>
					<Select
						label={ __( 'Price is', 'wp-smart-property' ) }
						value={ values.price_period }
						options={ config.periods.map( ( value ) => ( {
							value,
							label: PERIOD_LABELS[ value ] || value,
						} ) ) }
						onChange={ ( period ) =>
							set( { price_period: period } )
						}
					/>
				</Field>
				<Field>
					<Text
						label={ __( 'Reference', 'wp-smart-property' ) }
						help={ __(
							'Your own listing number, shown on the property page.',
							'wp-smart-property'
						) }
						value={ values.reference }
						onChange={ ( reference ) => set( { reference } ) }
					/>
				</Field>
				<Field>
					<Select
						label={ __( 'Energy class', 'wp-smart-property' ) }
						value={ values.energy_class }
						options={ config.energy.map( ( value ) => ( {
							value,
							label:
								value || __( 'Not given', 'wp-smart-property' ),
						} ) ) }
						onChange={ ( energy ) =>
							set( { energy_class: energy } )
						}
					/>
				</Field>
			</Grid>
			<Toggle
				label={ __( 'Price on request', 'wp-smart-property' ) }
				help={ __(
					'Show “Price on request” instead of the price. The price is still used for search filters.',
					'wp-smart-property'
				) }
				checked={ values.price_on_request }
				onChange={ ( on ) => set( { price_on_request: on } ) }
			/>
			<Toggle
				label={ __( 'Featured property', 'wp-smart-property' ) }
				help={ __(
					'Featured properties are shown first and get a badge.',
					'wp-smart-property'
				) }
				checked={ values.featured }
				onChange={ ( featured ) => set( { featured } ) }
			/>
			{ config.canManage && (
				<Grid>
					<Field>
						<Text
							type="date"
							label={ __(
								'Listing expires',
								'wp-smart-property'
							) }
							help={ __(
								'Leave empty to keep the listing until you remove it.',
								'wp-smart-property'
							) }
							value={ ( values.expires || '' ).slice( 0, 10 ) }
							onChange={ ( expires ) => set( { expires } ) }
						/>
					</Field>
				</Grid>
			) }
		</>
	);
}

function Rooms( { values, set } ) {
	const unit = unitLabel( config.general.area_unit );
	return (
		<Grid>
			<Field>
				<NumberInput
					label={ __( 'Bedrooms', 'wp-smart-property' ) }
					value={ values.bedrooms }
					onChange={ ( bedrooms ) => set( { bedrooms } ) }
				/>
			</Field>
			<Field>
				<NumberInput
					label={ __( 'Bathrooms', 'wp-smart-property' ) }
					help={ __(
						'Halves are allowed, e.g. 1.5',
						'wp-smart-property'
					) }
					step={ 0.5 }
					value={ values.bathrooms }
					onChange={ ( bathrooms ) => set( { bathrooms } ) }
				/>
			</Field>
			<Field>
				<NumberInput
					label={ __(
						'Garages / parking spaces',
						'wp-smart-property'
					) }
					value={ values.garages }
					onChange={ ( garages ) => set( { garages } ) }
				/>
			</Field>
			<Field>
				<NumberInput
					label={ __( 'Year built', 'wp-smart-property' ) }
					min={ 1000 }
					max={ 2100 }
					placeholder=""
					value={ values.year_built }
					onChange={ ( year ) => set( { year_built: year } ) }
				/>
			</Field>
			<Field>
				<NumberInput
					label={ sprintf(
						/* translators: %s: area unit */
						__( 'Living area (%s)', 'wp-smart-property' ),
						unit
					) }
					step="any"
					value={ values.area }
					onChange={ ( area ) => set( { area } ) }
				/>
			</Field>
			<Field>
				<NumberInput
					label={ sprintf(
						/* translators: %s: area unit */
						__( 'Land area (%s)', 'wp-smart-property' ),
						unit
					) }
					step="any"
					value={ values.land_area }
					onChange={ ( area ) => set( { land_area: area } ) }
				/>
			</Field>
		</Grid>
	);
}

function Location( { values, set } ) {
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( null );
	const query = [ values.street, values.postcode ]
		.filter( Boolean )
		.join( ', ' );

	const lookup = () => {
		setBusy( true );
		setMessage( null );
		apiFetch( {
			path: '/estatecraft/v1/geocode',
			method: 'POST',
			data: { query },
		} )
			.then( ( result ) => {
				set( { lat: result.lat, lng: result.lng, geocode: 'done' } );
				setMessage( {
					status: 'success',
					text: sprintf(
						/* translators: %s: place found */
						__( 'Pin placed at: %s', 'wp-smart-property' ),
						result.label
					),
				} );
			} )
			.catch( ( error ) =>
				setMessage( { status: 'error', text: error.message } )
			)
			.finally( () => setBusy( false ) );
	};

	const place = ( lat, lng ) =>
		set( {
			lat: Math.round( lat * 1e7 ) / 1e7,
			lng: Math.round( lng * 1e7 ) / 1e7,
			geocode: 'manual',
		} );

	return (
		<>
			<Grid>
				<Field wide>
					<Text
						label={ __( 'Street address', 'wp-smart-property' ) }
						placeholder={ __(
							'12 Rose Lane, London',
							'wp-smart-property'
						) }
						value={ values.street }
						onChange={ ( street ) => set( { street } ) }
					/>
				</Field>
				<Field>
					<Text
						label={ __( 'Postcode', 'wp-smart-property' ) }
						value={ values.postcode }
						onChange={ ( postcode ) => set( { postcode } ) }
					/>
				</Field>
			</Grid>
			<p className="description">
				{ __(
					'Choose the country, region and city in the Locations box.',
					'wp-smart-property'
				) }
			</p>
			<div className="estatecraft-location__tools">
				{ config.maps.geocoding && (
					<Button
						variant="secondary"
						isBusy={ busy }
						disabled={ busy || ! query }
						onClick={ lookup }
					>
						{ __( 'Find address on the map', 'wp-smart-property' ) }
					</Button>
				) }
				{ !! ( Number( values.lat ) || Number( values.lng ) ) && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () => set( { lat: 0, lng: 0, geocode: '' } ) }
					>
						{ __( 'Remove pin', 'wp-smart-property' ) }
					</Button>
				) }
				{ config.canManage && (
					<span className="estatecraft-location__state">
						{ GEOCODE_LABELS[ values.geocode ] || '' }
					</span>
				) }
			</div>
			{ message && (
				<Notice
					status={ message.status }
					isDismissible
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }
			<MapPicker
				lat={ values.lat }
				lng={ values.lng }
				maps={ config.maps }
				onChange={ place }
			/>
			<Grid>
				<Field>
					<Text
						type="number"
						step="any"
						label={ __( 'Latitude', 'wp-smart-property' ) }
						value={ values.lat || '' }
						onChange={ ( lat ) =>
							set( { lat: lat || 0, geocode: 'manual' } )
						}
					/>
				</Field>
				<Field>
					<Text
						type="number"
						step="any"
						label={ __( 'Longitude', 'wp-smart-property' ) }
						value={ values.lng || '' }
						onChange={ ( lng ) =>
							set( { lng: lng || 0, geocode: 'manual' } )
						}
					/>
				</Field>
			</Grid>
			<Toggle
				label={ __( 'Hide the exact address', 'wp-smart-property' ) }
				help={ __(
					'Visitors see the area on the map, not the street or the exact pin.',
					'wp-smart-property'
				) }
				checked={ values.hide_address }
				onChange={ ( hide ) => set( { hide_address: hide } ) }
			/>
		</>
	);
}

function Media( { values, set, thumbs, addThumbs } ) {
	return (
		<>
			<h3>{ __( 'Photos', 'wp-smart-property' ) }</h3>
			<Gallery
				ids={ values.gallery }
				thumbs={ thumbs }
				onThumbs={ addThumbs }
				onChange={ ( gallery ) => set( { gallery } ) }
			/>
			<h3>{ __( 'Floor plans', 'wp-smart-property' ) }</h3>
			<FloorPlans
				plans={ values.floor_plans }
				thumbs={ thumbs }
				unit={ unitLabel( config.general.area_unit ) }
				onThumbs={ addThumbs }
				onChange={ ( plans ) => set( { floor_plans: plans } ) }
			/>
			<h3>{ __( 'Video and virtual tour', 'wp-smart-property' ) }</h3>
			<Grid>
				<Field>
					<Text
						type="url"
						label={ __( 'Video link', 'wp-smart-property' ) }
						help={ __(
							'A YouTube or Vimeo link.',
							'wp-smart-property'
						) }
						value={ values.video_url }
						onChange={ ( url ) => set( { video_url: url } ) }
					/>
				</Field>
				<Field>
					<Text
						type="url"
						label={ __( 'Virtual tour link', 'wp-smart-property' ) }
						help={ __(
							'For example a Matterport or Kuula tour.',
							'wp-smart-property'
						) }
						value={ values.tour_url }
						onChange={ ( url ) => set( { tour_url: url } ) }
					/>
				</Field>
			</Grid>
		</>
	);
}

function Viewings( { values, set } ) {
	const slots = values.open_houses;
	const update = ( index, changes ) =>
		set( {
			open_houses: slots.map( ( slot, i ) =>
				i === index ? { ...slot, ...changes } : slot
			),
		} );
	return (
		<>
			<p className="description">
				{ __(
					'Open house times are shown on the property page until they have passed.',
					'wp-smart-property'
				) }
			</p>
			{ slots.map( ( slot, index ) => (
				<div className="estatecraft-slot" key={ index }>
					<Text
						type="datetime-local"
						label={ __( 'Starts', 'wp-smart-property' ) }
						value={ toLocalInput( slot.start ) }
						onChange={ ( value ) =>
							update( index, { start: fromLocalInput( value ) } )
						}
					/>
					<Text
						type="datetime-local"
						label={ __( 'Ends', 'wp-smart-property' ) }
						value={ toLocalInput( slot.end ) }
						min={ toLocalInput( slot.start ) }
						onChange={ ( value ) =>
							update( index, { end: fromLocalInput( value ) } )
						}
					/>
					<Button
						icon="trash"
						isDestructive
						label={ __( 'Remove time', 'wp-smart-property' ) }
						onClick={ () =>
							set( {
								open_houses: slots.filter(
									( s, i ) => i !== index
								),
							} )
						}
					/>
				</div>
			) ) }
			<Button
				variant="secondary"
				disabled={ slots.length >= 20 }
				onClick={ () =>
					set( {
						open_houses: [ ...slots, { start: '', end: '' } ],
					} )
				}
			>
				{ __( 'Add open house', 'wp-smart-property' ) }
			</Button>
		</>
	);
}

function CustomFields( { values, set } ) {
	if ( ! config.custom.length ) {
		return (
			<p className="description">
				{ __( 'There are no custom fields yet.', 'wp-smart-property' ) }{ ' ' }
				{ config.canManage && (
					<a href={ config.settingsUrl + '#fields' }>
						{ __( 'Add custom fields', 'wp-smart-property' ) }
					</a>
				) }
			</p>
		);
	}
	return (
		<Grid>
			{ config.custom.map( ( field ) => {
				const key = 'cf_' + field.key;
				const value = values[ key ];
				const change = ( next ) => set( { [ key ]: next } );
				let control;
				switch ( field.type ) {
					case 'number':
						control = (
							<NumberInput
								label={ field.label }
								step="any"
								value={ value }
								onChange={ change }
							/>
						);
						break;
					case 'select':
						control = (
							<Select
								label={ field.label }
								value={ value || '' }
								options={ [
									{
										value: '',
										label: __( '—', 'wp-smart-property' ),
									},
									...field.options.map( ( option ) => ( {
										value: option,
										label: option,
									} ) ),
								] }
								onChange={ change }
							/>
						);
						break;
					case 'checkbox':
						control = (
							<Toggle
								label={ field.label }
								checked={ value }
								onChange={ change }
							/>
						);
						break;
					case 'date':
						control = (
							<Text
								type="date"
								label={ field.label }
								value={ ( value || '' ).slice( 0, 10 ) }
								onChange={ change }
							/>
						);
						break;
					default:
						control = (
							<Text
								label={ field.label }
								value={ value }
								onChange={ change }
							/>
						);
				}
				return <Field key={ key }>{ control }</Field>;
			} ) }
		</Grid>
	);
}

function App( { input } ) {
	const [ values, setValues ] = useState( () => {
		try {
			return JSON.parse( input.value ) || {};
		} catch ( e ) {
			return {};
		}
	} );
	const [ thumbs, setThumbs ] = useState( config.media || {} );
	const [ agents, setAgents ] = useState( config.agents || [] );

	useEffect( () => {
		input.value = JSON.stringify( values );
	}, [ values, input ] );

	const set = ( changes ) =>
		setValues( ( current ) => ( { ...current, ...changes } ) );
	const addThumbs = ( more ) =>
		setThumbs( ( current ) => ( { ...current, ...more } ) );
	const props = { values, set };

	const tabs = [
		{ name: 'listing', title: __( 'Listing', 'wp-smart-property' ) },
		{ name: 'rooms', title: __( 'Rooms & size', 'wp-smart-property' ) },
		{ name: 'location', title: __( 'Location', 'wp-smart-property' ) },
		{ name: 'media', title: __( 'Photos & media', 'wp-smart-property' ) },
		{ name: 'viewings', title: __( 'Open houses', 'wp-smart-property' ) },
		{ name: 'agents', title: __( 'Agents', 'wp-smart-property' ) },
		{ name: 'notes', title: __( 'Private notes', 'wp-smart-property' ) },
	];
	if ( config.custom.length || config.canManage ) {
		tabs.push( {
			name: 'custom',
			title: __( 'More details', 'wp-smart-property' ),
		} );
	}

	return (
		<TabPanel className="estatecraft-editor__tabs" tabs={ tabs }>
			{ ( tab ) => (
				<div className="estatecraft-editor__panel">
					{ tab.name === 'listing' && <Listing { ...props } /> }
					{ tab.name === 'rooms' && <Rooms { ...props } /> }
					{ tab.name === 'location' && <Location { ...props } /> }
					{ tab.name === 'media' && (
						<Media
							{ ...props }
							thumbs={ thumbs }
							addThumbs={ addThumbs }
						/>
					) }
					{ tab.name === 'viewings' && <Viewings { ...props } /> }
					{ tab.name === 'agents' && (
						<Agents
							ids={ values.agents || [] }
							known={ agents }
							rest={ config.agentRest }
							onKnown={ ( agent ) =>
								setAgents( ( list ) => [ ...list, agent ] )
							}
							onChange={ ( ids ) => set( { agents: ids } ) }
						/>
					) }
					{ tab.name === 'notes' && (
						<TextArea
							label={ __( 'Private notes', 'wp-smart-property' ) }
							help={ __(
								'Only people who can edit this property see these notes. They are never shown on the site.',
								'wp-smart-property'
							) }
							rows={ 6 }
							value={ values.private_notes }
							onChange={ ( notes ) =>
								set( { private_notes: notes } )
							}
						/>
					) }
					{ tab.name === 'custom' && <CustomFields { ...props } /> }
				</div>
			) }
		</TabPanel>
	);
}

const root = document.getElementById( 'estatecraft-property-editor' );
const input = document.getElementById( 'estatecraft-details-input' );
if ( root && input ) {
	createRoot( root ).render( <App input={ input } /> );
}
