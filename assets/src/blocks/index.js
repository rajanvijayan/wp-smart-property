/**
 * Editor side of the EstateCraft blocks. The blocks are rendered on the server; the editor shows
 * that rendering and adds settings in the sidebar.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	CheckboxControl,
	FormTokenField,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import './editor.scss';

const DETAIL_FIELDS = [
	[ 'reference', __( 'Reference', 'wp-smart-property' ) ],
	[ 'type', __( 'Type', 'wp-smart-property' ) ],
	[ 'status', __( 'Status', 'wp-smart-property' ) ],
	[ 'price', __( 'Price', 'wp-smart-property' ) ],
	[ 'bedrooms', __( 'Bedrooms', 'wp-smart-property' ) ],
	[ 'bathrooms', __( 'Bathrooms', 'wp-smart-property' ) ],
	[ 'garages', __( 'Parking spaces', 'wp-smart-property' ) ],
	[ 'area', __( 'Living area', 'wp-smart-property' ) ],
	[ 'land_area', __( 'Land area', 'wp-smart-property' ) ],
	[ 'year_built', __( 'Year built', 'wp-smart-property' ) ],
	[ 'energy_class', __( 'Energy class', 'wp-smart-property' ) ],
	[ 'category', __( 'Category', 'wp-smart-property' ) ],
];

const DEFAULT_HEADINGS = {
	'estatecraft/property-details': __( 'Details', 'wp-smart-property' ),
	'estatecraft/property-features': __( 'Features', 'wp-smart-property' ),
	'estatecraft/floor-plans': __( 'Floor plans', 'wp-smart-property' ),
	'estatecraft/property-video': __( 'Video and tour', 'wp-smart-property' ),
	'estatecraft/property-map': __( 'Location', 'wp-smart-property' ),
	'estatecraft/open-houses': __( 'Open house', 'wp-smart-property' ),
	'estatecraft/property-agents': __( 'Contact', 'wp-smart-property' ),
};

const common = { __nextHasNoMarginBottom: true };
const sized = { ...common, __next40pxDefaultSize: true };

/**
 * The post being edited, so previews show its data (not when editing a template).
 *
 * @param {Object} context Block context.
 * @return {number|undefined} Post ID.
 */
function usePostId( context ) {
	const editing = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return editor ? editor.getCurrentPostId() : undefined;
	}, [] );
	const id = context?.postId || editing;
	return typeof id === 'number' ? id : undefined;
}

function HeadingControls( { name, attributes, setAttributes } ) {
	return (
		<>
			<ToggleControl
				{ ...common }
				label={ __( 'Show heading', 'wp-smart-property' ) }
				checked={ attributes.showHeading }
				onChange={ ( showHeading ) => setAttributes( { showHeading } ) }
			/>
			{ attributes.showHeading && (
				<TextControl
					{ ...sized }
					label={ __( 'Heading', 'wp-smart-property' ) }
					placeholder={ DEFAULT_HEADINGS[ name ] }
					value={ attributes.heading }
					onChange={ ( heading ) => setAttributes( { heading } ) }
				/>
			) }
		</>
	);
}

/**
 * Pick terms of a taxonomy by slug.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.taxonomy Taxonomy.
 * @param {string}   props.label    Label.
 * @param {string[]} props.value    Slugs.
 * @param {Function} props.onChange Change handler.
 */
function TermPicker( { taxonomy, label, value, onChange } ) {
	const terms = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecords( 'taxonomy', taxonomy, {
				per_page: 100,
				_fields: 'id,name,slug',
				context: 'view',
			} ) || [],
		[ taxonomy ]
	);
	const bySlug = Object.fromEntries(
		terms.map( ( t ) => [ t.slug, t.name ] )
	);
	const byName = Object.fromEntries(
		terms.map( ( t ) => [ t.name, t.slug ] )
	);
	return (
		<FormTokenField
			{ ...sized }
			label={ label }
			value={ value.map( ( slug ) => bySlug[ slug ] || slug ) }
			suggestions={ terms.map( ( t ) => t.name ) }
			onChange={ ( names ) =>
				onChange(
					names
						.map( ( name ) => byName[ name ] || name )
						.filter( ( slug ) => bySlug[ slug ] )
				)
			}
			__experimentalExpandOnFocus
		/>
	);
}

function PostSelect( { postType, label, value, onChange, emptyLabel } ) {
	const posts = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecords( 'postType', postType, {
				per_page: 100,
				_fields: 'id,title',
				context: 'view',
			} ) || [],
		[ postType ]
	);
	return (
		<SelectControl
			{ ...sized }
			label={ label }
			value={ value }
			options={ [
				{ value: 0, label: emptyLabel },
				...posts.map( ( post ) => ( {
					value: post.id,
					label: post.title?.rendered || '#' + post.id,
				} ) ),
			] }
			onChange={ ( id ) => onChange( Number( id ) ) }
		/>
	);
}

function ListingsControls( { attributes, setAttributes } ) {
	const { source } = attributes;
	return (
		<>
			<SelectControl
				{ ...sized }
				label={ __( 'Show', 'wp-smart-property' ) }
				value={ source }
				options={ [
					{
						value: 'query',
						label: __( 'Properties I choose', 'wp-smart-property' ),
					},
					{
						value: 'main',
						label: __(
							'The archive being viewed (templates)',
							'wp-smart-property'
						),
					},
					{
						value: 'similar',
						label: __(
							'Properties like the one being viewed',
							'wp-smart-property'
						),
					},
					{
						value: 'agent',
						label: __(
							'Listings of the agent being viewed',
							'wp-smart-property'
						),
					},
					{
						value: 'agency',
						label: __(
							'Listings of the agency being viewed',
							'wp-smart-property'
						),
					},
				] }
				onChange={ ( next ) => setAttributes( { source: next } ) }
			/>
			{ source !== 'main' && (
				<RangeControl
					{ ...sized }
					label={ __( 'Number of properties', 'wp-smart-property' ) }
					min={ 1 }
					max={ 24 }
					value={ attributes.count }
					onChange={ ( count ) => setAttributes( { count } ) }
				/>
			) }
			{ source === 'query' && (
				<>
					<SelectControl
						{ ...sized }
						label={ __( 'Order', 'wp-smart-property' ) }
						value={ attributes.order }
						options={ [
							{
								value: 'featured',
								label: __(
									'Featured first, then newest',
									'wp-smart-property'
								),
							},
							{
								value: 'newest',
								label: __( 'Newest', 'wp-smart-property' ),
							},
							{
								value: 'price-asc',
								label: __(
									'Price, low to high',
									'wp-smart-property'
								),
							},
							{
								value: 'price-desc',
								label: __(
									'Price, high to low',
									'wp-smart-property'
								),
							},
						] }
						onChange={ ( order ) => setAttributes( { order } ) }
					/>
					<ToggleControl
						{ ...common }
						label={ __(
							'Only featured properties',
							'wp-smart-property'
						) }
						checked={ attributes.featuredOnly }
						onChange={ ( featuredOnly ) =>
							setAttributes( { featuredOnly } )
						}
					/>
					<TermPicker
						taxonomy="property_type"
						label={ __( 'Property types', 'wp-smart-property' ) }
						value={ attributes.types }
						onChange={ ( types ) => setAttributes( { types } ) }
					/>
					<TermPicker
						taxonomy="property_status"
						label={ __( 'Statuses', 'wp-smart-property' ) }
						value={ attributes.statuses }
						onChange={ ( statuses ) =>
							setAttributes( { statuses } )
						}
					/>
					<TermPicker
						taxonomy="property_location"
						label={ __( 'Locations', 'wp-smart-property' ) }
						value={ attributes.locations }
						onChange={ ( locations ) =>
							setAttributes( { locations } )
						}
					/>
				</>
			) }
		</>
	);
}

function LayoutControls( { attributes, setAttributes, withList = true } ) {
	return (
		<>
			{ withList && (
				<SelectControl
					{ ...sized }
					label={ __( 'Layout', 'wp-smart-property' ) }
					value={ attributes.layout }
					options={ [
						{
							value: 'grid',
							label: __( 'Grid', 'wp-smart-property' ),
						},
						{
							value: 'list',
							label: __( 'List', 'wp-smart-property' ),
						},
					] }
					onChange={ ( layout ) => setAttributes( { layout } ) }
				/>
			) }
			{ ( ! withList || attributes.layout === 'grid' ) && (
				<RangeControl
					{ ...sized }
					label={ __( 'Columns', 'wp-smart-property' ) }
					min={ 1 }
					max={ 4 }
					value={ attributes.columns }
					onChange={ ( columns ) => setAttributes( { columns } ) }
				/>
			) }
			<TextControl
				{ ...sized }
				label={ __( 'Heading', 'wp-smart-property' ) }
				help={ __( 'Optional.', 'wp-smart-property' ) }
				value={ attributes.heading }
				onChange={ ( heading ) => setAttributes( { heading } ) }
			/>
		</>
	);
}

/**
 * Sidebar settings by block.
 */
const CONTROLS = {
	'estatecraft/property-gallery': ( { attributes, setAttributes } ) => (
		<SelectControl
			{ ...sized }
			label={ __( 'Layout', 'wp-smart-property' ) }
			value={ attributes.layout }
			options={ [
				{
					value: 'mosaic',
					label: __(
						'Large photo with four more',
						'wp-smart-property'
					),
				},
				{
					value: 'single',
					label: __( 'One large photo', 'wp-smart-property' ),
				},
			] }
			onChange={ ( layout ) => setAttributes( { layout } ) }
		/>
	),
	'estatecraft/property-price': ( { attributes, setAttributes } ) => (
		<>
			<ToggleControl
				{ ...common }
				label={ __( 'Show status', 'wp-smart-property' ) }
				checked={ attributes.showStatus }
				onChange={ ( showStatus ) => setAttributes( { showStatus } ) }
			/>
			<ToggleControl
				{ ...common }
				label={ __( 'Show reference', 'wp-smart-property' ) }
				checked={ attributes.showReference }
				onChange={ ( showReference ) =>
					setAttributes( { showReference } )
				}
			/>
		</>
	),
	'estatecraft/property-details': ( props ) => {
		const { attributes, setAttributes } = props;
		const all = ! attributes.fields.length;
		return (
			<>
				<HeadingControls { ...props } />
				<ToggleControl
					{ ...common }
					label={ __( 'All details', 'wp-smart-property' ) }
					help={ __(
						'Includes the custom fields shown on property pages.',
						'wp-smart-property'
					) }
					checked={ all }
					onChange={ ( on ) =>
						setAttributes( {
							fields: on
								? []
								: DETAIL_FIELDS.map( ( [ key ] ) => key ),
						} )
					}
				/>
				{ ! all &&
					DETAIL_FIELDS.map( ( [ key, label ] ) => (
						<CheckboxControl
							{ ...common }
							key={ key }
							label={ label }
							checked={ attributes.fields.includes( key ) }
							onChange={ ( on ) =>
								setAttributes( {
									fields: on
										? [ ...attributes.fields, key ]
										: attributes.fields.filter(
												( k ) => k !== key
										  ),
								} )
							}
						/>
					) ) }
			</>
		);
	},
	'estatecraft/property-map': ( props ) => (
		<>
			<HeadingControls { ...props } />
			<RangeControl
				{ ...sized }
				label={ __( 'Height (px)', 'wp-smart-property' ) }
				min={ 200 }
				max={ 800 }
				step={ 20 }
				value={ props.attributes.height }
				onChange={ ( height ) => props.setAttributes( { height } ) }
			/>
			<RangeControl
				{ ...sized }
				label={ __( 'Zoom', 'wp-smart-property' ) }
				min={ 3 }
				max={ 19 }
				value={ props.attributes.zoom }
				onChange={ ( zoom ) => props.setAttributes( { zoom } ) }
			/>
		</>
	),
	'estatecraft/property-listings': ( props ) => (
		<>
			<ListingsControls { ...props } />
			<LayoutControls { ...props } />
			{ props.attributes.source === 'main' && (
				<ToggleControl
					{ ...common }
					label={ __( 'Page links', 'wp-smart-property' ) }
					checked={ props.attributes.pagination }
					onChange={ ( pagination ) =>
						props.setAttributes( { pagination } )
					}
				/>
			) }
		</>
	),
	'estatecraft/featured-property': ( { attributes, setAttributes } ) => (
		<PostSelect
			postType="property"
			label={ __( 'Property', 'wp-smart-property' ) }
			emptyLabel={ __(
				'The latest featured property',
				'wp-smart-property'
			) }
			value={ attributes.propertyId }
			onChange={ ( propertyId ) => setAttributes( { propertyId } ) }
		/>
	),
	'estatecraft/agents': ( props ) => {
		const { attributes, setAttributes } = props;
		return (
			<>
				<SelectControl
					{ ...sized }
					label={ __( 'Show', 'wp-smart-property' ) }
					value={ attributes.source }
					options={ [
						{
							value: 'all',
							label: __( 'All agents', 'wp-smart-property' ),
						},
						{
							value: 'agency',
							label: __(
								'Agents of an agency',
								'wp-smart-property'
							),
						},
						{
							value: 'main',
							label: __(
								'The archive being viewed (templates)',
								'wp-smart-property'
							),
						},
					] }
					onChange={ ( source ) => setAttributes( { source } ) }
				/>
				{ attributes.source === 'agency' && (
					<PostSelect
						postType="estate_agency"
						label={ __( 'Agency', 'wp-smart-property' ) }
						emptyLabel={ __(
							'The agency being viewed',
							'wp-smart-property'
						) }
						value={ attributes.agencyId }
						onChange={ ( agencyId ) =>
							setAttributes( { agencyId } )
						}
					/>
				) }
				{ attributes.source !== 'main' && (
					<RangeControl
						{ ...sized }
						label={ __( 'Number of agents', 'wp-smart-property' ) }
						min={ 1 }
						max={ 24 }
						value={ attributes.count }
						onChange={ ( count ) => setAttributes( { count } ) }
					/>
				) }
				<LayoutControls { ...props } withList={ false } />
			</>
		);
	},
};

function Edit( props ) {
	const { name, attributes, context } = props;
	const postId = usePostId( context );
	const blockProps = useBlockProps( {
		className: 'estatecraft-block-preview',
	} );
	const Controls =
		CONTROLS[ name ] ||
		( DEFAULT_HEADINGS[ name ] ? HeadingControls : null );
	return (
		<div { ...blockProps }>
			{ Controls && (
				<InspectorControls>
					<PanelBody title={ __( 'Settings', 'wp-smart-property' ) }>
						<Controls { ...props } />
					</PanelBody>
				</InspectorControls>
			) }
			<ServerSideRender
				block={ name }
				attributes={ attributes }
				urlQueryArgs={ postId ? { post_id: postId } : {} }
				skipBlockSupportAttributes
			/>
		</div>
	);
}

[
	'property-gallery',
	'property-price',
	'property-address',
	'property-facts',
	'property-details',
	'property-features',
	'floor-plans',
	'property-video',
	'property-map',
	'open-houses',
	'property-agents',
	'property-actions',
	'property-listings',
	'featured-property',
	'agents',
	'agent-profile',
].forEach( ( name ) =>
	registerBlockType( 'estatecraft/' + name, {
		edit: Edit,
		save: () => null,
	} )
);
