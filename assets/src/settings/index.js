/**
 * EstateCraft settings page.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner, TabPanel } from '@wordpress/components';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { formatPrice } from '../common/format';
import {
	NumberInput,
	Select,
	Text,
	TextArea,
	Toggle,
} from '../common/controls';
import CustomFields from './custom-fields';
import MapDefaults from './map-defaults';
import Tools from './tools';
import './index.scss';

const config = window.estateCraftSettings || { pages: [] };

const ALL_TABS = [
	{ name: 'general', title: __( 'General', 'wp-smart-property' ) },
	{ name: 'pages', title: __( 'Pages', 'wp-smart-property' ) },
	{ name: 'maps', title: __( 'Maps', 'wp-smart-property' ) },
	{ name: 'emails', title: __( 'Emails', 'wp-smart-property' ) },
	{ name: 'portal', title: __( 'Agent portal', 'wp-smart-property' ) },
	{ name: 'privacy', title: __( 'Privacy', 'wp-smart-property' ) },
	{ name: 'fields', title: __( 'Custom fields', 'wp-smart-property' ) },
	{ name: 'tools', title: __( 'Tools', 'wp-smart-property' ) },
];

// Sections switch on as the features behind them ship (emails, portal and privacy come with
// inquiries and the agent portal).
const sections = config.sections || [
	'general',
	'pages',
	'maps',
	'fields',
	'tools',
];
const TABS = ALL_TABS.filter( ( tab ) => sections.includes( tab.name ) );

const SEPARATORS = [
	{ value: ',', label: __( 'Comma (,)', 'wp-smart-property' ) },
	{ value: '.', label: __( 'Point (.)', 'wp-smart-property' ) },
	{ value: ' ', label: __( 'Space', 'wp-smart-property' ) },
	{ value: "'", label: __( "Apostrophe (')", 'wp-smart-property' ) },
	{ value: '', label: __( 'None', 'wp-smart-property' ) },
];

function Section( { title, children } ) {
	return (
		<section className="estatecraft-settings__section">
			{ title && <h2>{ title }</h2> }
			{ children }
		</section>
	);
}

function General( { value, set } ) {
	return (
		<Section title={ __( 'Prices and areas', 'wp-smart-property' ) }>
			<div className="estatecraft-settings__grid">
				<Text
					label={ __( 'Currency code', 'wp-smart-property' ) }
					help={ __(
						'Three letters, e.g. USD, EUR, GBP.',
						'wp-smart-property'
					) }
					maxLength={ 3 }
					value={ value.currency }
					onChange={ ( currency ) =>
						set( { currency: currency.toUpperCase() } )
					}
				/>
				<Text
					label={ __( 'Currency symbol', 'wp-smart-property' ) }
					value={ value.symbol }
					onChange={ ( symbol ) => set( { symbol } ) }
				/>
				<Select
					label={ __( 'Thousands separator', 'wp-smart-property' ) }
					value={ value.thousands }
					options={ SEPARATORS }
					onChange={ ( thousands ) => set( { thousands } ) }
				/>
				<Select
					label={ __( 'Decimal point', 'wp-smart-property' ) }
					value={ value.decimal_point }
					options={ SEPARATORS.filter( ( s ) =>
						[ ',', '.' ].includes( s.value )
					) }
					onChange={ ( point ) => set( { decimal_point: point } ) }
				/>
				<NumberInput
					label={ __( 'Decimals', 'wp-smart-property' ) }
					max={ 4 }
					placeholder="0"
					value={ value.decimals }
					onChange={ ( decimals ) =>
						set( { decimals: Number( decimals ) } )
					}
				/>
				<Select
					label={ __( 'Area unit', 'wp-smart-property' ) }
					value={ value.area_unit }
					options={ [
						{
							value: 'sqft',
							label: __(
								'Square feet (sq ft)',
								'wp-smart-property'
							),
						},
						{
							value: 'm2',
							label: __(
								'Square metres (m²)',
								'wp-smart-property'
							),
						},
					] }
					onChange={ ( unit ) => set( { area_unit: unit } ) }
				/>
			</div>
			<Toggle
				label={ __( 'Symbol after the price', 'wp-smart-property' ) }
				checked={ value.symbol_after }
				onChange={ ( after ) => set( { symbol_after: after } ) }
			/>
			<p className="estatecraft-settings__preview">
				{ __( 'Preview:', 'wp-smart-property' ) }{ ' ' }
				<strong>{ formatPrice( 1250000, '', false, value ) }</strong>
				{ ' · ' }
				<strong>
					{ formatPrice( 1450.5, 'month', false, value ) }
				</strong>
			</p>
		</Section>
	);
}

function Pages( { value, set } ) {
	const options = [
		{ value: 0, label: __( '— Not set —', 'wp-smart-property' ) },
		...config.pages.map( ( page ) => ( {
			value: page.id,
			label: page.title || '#' + page.id,
		} ) ),
	];
	const all = [
		[
			'search',
			__( 'Property search', 'wp-smart-property' ),
			__(
				'Search forms send visitors here. Without a page, the property archive is used.',
				'wp-smart-property'
			),
		],
		[
			'dashboard',
			__( 'Agent dashboard', 'wp-smart-property' ),
			__( 'Where agents manage their listings.', 'wp-smart-property' ),
		],
		[
			'submit',
			__( 'Submit a property', 'wp-smart-property' ),
			__( 'The listing submission form.', 'wp-smart-property' ),
		],
	];
	const rows = all.filter(
		( [ key ] ) => key === 'search' || sections.includes( 'portal' )
	);
	return (
		<Section>
			<div className="estatecraft-settings__stack">
				{ rows.map( ( [ key, label, help ] ) => (
					<Select
						key={ key }
						label={ label }
						help={ help }
						value={ value[ key ] }
						options={ options }
						onChange={ ( id ) => set( { [ key ]: Number( id ) } ) }
					/>
				) ) }
			</div>
		</Section>
	);
}

function Maps( { value, set } ) {
	return (
		<>
			<Section title={ __( 'Map tiles', 'wp-smart-property' ) }>
				<div className="estatecraft-settings__stack">
					<Text
						label={ __( 'Tile URL', 'wp-smart-property' ) }
						help={ __(
							'An https URL with {z}, {x} and {y}. The default uses the OpenStreetMap tile servers, which suit low-traffic sites; busy sites should use a tile provider.',
							'wp-smart-property'
						) }
						value={ value.tiles }
						onChange={ ( tiles ) => set( { tiles } ) }
					/>
					<Text
						label={ __( 'Attribution', 'wp-smart-property' ) }
						help={ __(
							'Shown on every map, as the tile provider requires. Links are allowed.',
							'wp-smart-property'
						) }
						value={ value.attribution }
						onChange={ ( attribution ) => set( { attribution } ) }
					/>
				</div>
				<Toggle
					label={ __( 'Group nearby pins', 'wp-smart-property' ) }
					help={ __(
						'Pins close together are shown as one numbered circle until you zoom in.',
						'wp-smart-property'
					) }
					checked={ value.cluster }
					onChange={ ( cluster ) => set( { cluster } ) }
				/>
				<Toggle
					label={ __( 'Address lookup', 'wp-smart-property' ) }
					help={ __(
						'Find the map pin from the address, using OpenStreetMap Nominatim. Addresses are sent to nominatim.openstreetmap.org.',
						'wp-smart-property'
					) }
					checked={ value.geocoding }
					onChange={ ( geocoding ) => set( { geocoding } ) }
				/>
			</Section>
			<Section title={ __( 'Starting view', 'wp-smart-property' ) }>
				<p className="description">
					{ __(
						'Maps without properties, and the pin picker for new properties, start here. Move the map, then use the button.',
						'wp-smart-property'
					) }
				</p>
				<MapDefaults value={ value } set={ set } />
			</Section>
		</>
	);
}

function Emails( { value, set } ) {
	const tags = '{property} {property_url} {name} {email} {phone} {message}';
	return (
		<>
			<Section title={ __( 'Sender', 'wp-smart-property' ) }>
				<div className="estatecraft-settings__grid">
					<Text
						label={ __( 'From name', 'wp-smart-property' ) }
						help={ __(
							'Empty: the site title.',
							'wp-smart-property'
						) }
						value={ value.from_name }
						onChange={ ( name ) => set( { from_name: name } ) }
					/>
					<Text
						type="email"
						label={ __( 'From email', 'wp-smart-property' ) }
						help={ __(
							'Empty: the WordPress default. Use an address on your own domain.',
							'wp-smart-property'
						) }
						value={ value.from_email }
						onChange={ ( email ) => set( { from_email: email } ) }
					/>
				</div>
			</Section>
			<Section
				title={ __(
					'New inquiry (to the agent)',
					'wp-smart-property'
				) }
			>
				<div className="estatecraft-settings__stack">
					<Text
						label={ __( 'Subject', 'wp-smart-property' ) }
						value={ value.inquiry_subject }
						onChange={ ( subject ) =>
							set( { inquiry_subject: subject } )
						}
					/>
					<TextArea
						label={ __( 'Message', 'wp-smart-property' ) }
						help={ __( 'Tags:', 'wp-smart-property' ) + ' ' + tags }
						rows={ 6 }
						value={ value.inquiry_body }
						onChange={ ( body ) => set( { inquiry_body: body } ) }
					/>
				</div>
			</Section>
			<Section
				title={ __(
					'Listing approved (to the agent)',
					'wp-smart-property'
				) }
			>
				<div className="estatecraft-settings__stack">
					<Text
						label={ __( 'Subject', 'wp-smart-property' ) }
						value={ value.approved_subject }
						onChange={ ( subject ) =>
							set( { approved_subject: subject } )
						}
					/>
					<TextArea
						label={ __( 'Message', 'wp-smart-property' ) }
						help={
							__( 'Tags:', 'wp-smart-property' ) +
							' {property} {property_url}'
						}
						rows={ 5 }
						value={ value.approved_body }
						onChange={ ( body ) => set( { approved_body: body } ) }
					/>
				</div>
			</Section>
		</>
	);
}

function Portal( { value, set } ) {
	return (
		<Section>
			<Toggle
				label={ __( 'Agents can register', 'wp-smart-property' ) }
				help={ __(
					'Shows a registration form on the agent dashboard page.',
					'wp-smart-property'
				) }
				checked={ value.registration }
				onChange={ ( registration ) => set( { registration } ) }
			/>
			<Toggle
				label={ __( 'Approve new agents', 'wp-smart-property' ) }
				help={ __(
					'New agent accounts wait for a manager before they can submit listings.',
					'wp-smart-property'
				) }
				checked={ value.approve_agents }
				onChange={ ( approve ) => set( { approve_agents: approve } ) }
			/>
			<Toggle
				label={ __(
					'Agents publish without review',
					'wp-smart-property'
				) }
				help={ __(
					'Off: submitted listings wait for review.',
					'wp-smart-property'
				) }
				checked={ value.agents_publish }
				onChange={ ( publish ) => set( { agents_publish: publish } ) }
			/>
			<div className="estatecraft-settings__grid">
				<NumberInput
					label={ __(
						'Listing duration (days)',
						'wp-smart-property'
					) }
					help={ __(
						'Listings submitted by agents expire after this. 0: never.',
						'wp-smart-property'
					) }
					max={ 3650 }
					value={ value.duration }
					onChange={ ( days ) => set( { duration: Number( days ) } ) }
				/>
				<NumberInput
					label={ __( 'Photos per listing', 'wp-smart-property' ) }
					min={ 1 }
					max={ 100 }
					value={ value.max_images }
					onChange={ ( max ) => set( { max_images: Number( max ) } ) }
				/>
			</div>
		</Section>
	);
}

function Privacy( { value, set } ) {
	return (
		<Section>
			<div className="estatecraft-settings__stack">
				<TextArea
					label={ __( 'Consent text', 'wp-smart-property' ) }
					help={ __(
						'Shown next to the checkbox on inquiry forms. Empty: no checkbox.',
						'wp-smart-property'
					) }
					rows={ 3 }
					value={ value.consent }
					onChange={ ( consent ) => set( { consent } ) }
				/>
				<NumberInput
					label={ __(
						'Delete inquiries after (days)',
						'wp-smart-property'
					) }
					help={ __(
						'Inquiries older than this are deleted. 0: keep them.',
						'wp-smart-property'
					) }
					max={ 3650 }
					value={ value.retention_days }
					onChange={ ( days ) =>
						set( { retention_days: Number( days ) } )
					}
				/>
			</div>
		</Section>
	);
}

const initialTab = () => {
	const hash = window.location.hash.replace( '#', '' );
	return TABS.some( ( tab ) => tab.name === hash ) ? hash : 'general';
};

function App() {
	const [ settings, setSettings ] = useState( null );
	const [ saved, setSaved ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/estatecraft/v1/settings' } )
			.then( ( data ) => {
				setSettings( data );
				setSaved( JSON.stringify( data ) );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error.message } )
			);
	}, [] );

	const dirty = settings && JSON.stringify( settings ) !== saved;

	useEffect( () => {
		const warn = ( event ) => {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	if ( ! settings ) {
		return notice ? (
			<Notice status="error" isDismissible={ false }>
				{ notice.text }
			</Notice>
		) : (
			<Spinner />
		);
	}

	const section = ( name ) => ( {
		value: settings[ name ],
		set: ( changes ) =>
			setSettings( ( current ) => ( {
				...current,
				[ name ]: { ...current[ name ], ...changes },
			} ) ),
	} );

	const save = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/estatecraft/v1/settings',
			method: 'POST',
			data: settings,
		} )
			.then( ( data ) => {
				setSettings( data );
				setSaved( JSON.stringify( data ) );
				setNotice( {
					status: 'success',
					text: __( 'Settings saved.', 'wp-smart-property' ),
				} );
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', text: error.message } )
			)
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="estatecraft-settings">
			<div className="estatecraft-settings__header">
				<h1>{ __( 'EstateCraft settings', 'wp-smart-property' ) }</h1>
				<span className="estatecraft-settings__version">
					{ config.version }
				</span>
			</div>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			<TabPanel
				className="estatecraft-settings__tabs"
				tabs={ TABS }
				initialTabName={ initialTab() }
				onSelect={ ( name ) =>
					window.history.replaceState( null, '', '#' + name )
				}
			>
				{ ( tab ) => (
					<div className="estatecraft-settings__panel">
						{ tab.name === 'general' && (
							<General { ...section( 'general' ) } />
						) }
						{ tab.name === 'pages' && (
							<Pages { ...section( 'pages' ) } />
						) }
						{ tab.name === 'maps' && (
							<Maps { ...section( 'maps' ) } />
						) }
						{ tab.name === 'emails' && (
							<Emails { ...section( 'emails' ) } />
						) }
						{ tab.name === 'portal' && (
							<Portal { ...section( 'portal' ) } />
						) }
						{ tab.name === 'privacy' && (
							<Privacy { ...section( 'privacy' ) } />
						) }
						{ tab.name === 'fields' && (
							<CustomFields
								fields={ settings.fields }
								savedKeys={ JSON.parse( saved ).fields.map(
									( field ) => field.key
								) }
								onChange={ ( fields ) =>
									setSettings( ( current ) => ( {
										...current,
										fields,
									} ) )
								}
							/>
						) }
						{ tab.name === 'tools' && <Tools /> }
					</div>
				) }
			</TabPanel>
			<div className="estatecraft-settings__footer">
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving || ! dirty }
					onClick={ save }
				>
					{ __( 'Save settings', 'wp-smart-property' ) }
				</Button>
				{ dirty && (
					<span className="estatecraft-settings__unsaved">
						{ __( 'Unsaved changes', 'wp-smart-property' ) }
					</span>
				) }
			</div>
		</div>
	);
}

const root = document.getElementById( 'estatecraft-settings' );
if ( root ) {
	createRoot( root ).render( <App /> );
}
