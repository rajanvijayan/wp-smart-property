/**
 * Choose the agents shown on a listing.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, ComboboxControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { decodeEntities as decode } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

export default function Agents( { ids, known, rest, onChange, onKnown } ) {
	const [ search, setSearch ] = useState( '' );
	const [ found, setFound ] = useState( [] );

	useEffect( () => {
		let current = true;
		const timer = setTimeout( () => {
			apiFetch( {
				path: addQueryArgs( `/wp/v2/${ rest }`, {
					search,
					per_page: 20,
					_fields: 'id,title',
					context: 'view',
				} ),
			} )
				.then( ( agents ) => {
					if ( current ) {
						setFound(
							agents.map( ( agent ) => ( {
								id: agent.id,
								title:
									agent.title?.rendered ||
									__( '(no name)', 'wp-smart-property' ),
							} ) )
						);
					}
				} )
				.catch( () => current && setFound( [] ) );
		}, 250 );
		return () => {
			current = false;
			clearTimeout( timer );
		};
	}, [ search, rest ] );

	const titleOf = ( id ) =>
		known.find( ( agent ) => agent.id === id )?.title || `#${ id }`;
	const options = found
		.filter( ( agent ) => ! ids.includes( agent.id ) )
		.map( ( agent ) => ( {
			value: String( agent.id ),
			label: decode( agent.title ),
		} ) );

	return (
		<div className="estatecraft-agents">
			{ ids.length > 0 ? (
				<ul className="estatecraft-agents__list">
					{ ids.map( ( id ) => (
						<li key={ id }>
							<span>{ decode( titleOf( id ) ) }</span>
							<Button
								size="small"
								icon="no-alt"
								isDestructive
								label={ __(
									'Remove agent',
									'wp-smart-property'
								) }
								onClick={ () =>
									onChange( ids.filter( ( i ) => i !== id ) )
								}
							/>
						</li>
					) ) }
				</ul>
			) : (
				<p className="description">
					{ __(
						'No agent yet. Inquiries go to the site’s email address until you add one.',
						'wp-smart-property'
					) }
				</p>
			) }
			<ComboboxControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Add an agent', 'wp-smart-property' ) }
				value={ null }
				options={ options }
				onFilterValueChange={ setSearch }
				onChange={ ( value ) => {
					const id = Number( value );
					if ( ! id || ids.includes( id ) ) {
						return;
					}
					const agent = found.find( ( a ) => a.id === id );
					if ( agent ) {
						onKnown( agent );
					}
					onChange( [ ...ids, id ] );
				} }
			/>
		</div>
	);
}
