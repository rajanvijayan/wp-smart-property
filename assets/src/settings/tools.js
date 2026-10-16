/**
 * Status, search index rebuild and the 2.0 upgrade report.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

export default function Tools() {
	const [ status, setStatus ] = useState( null );
	const [ rebuilding, setRebuilding ] = useState( null );
	const [ error, setError ] = useState( null );

	const load = () =>
		apiFetch( { path: '/estatecraft/v1/status' } )
			.then( setStatus )
			.catch( ( e ) => setError( e.message ) );

	useEffect( () => {
		load();
	}, [] );

	const rebuild = async () => {
		setError( null );
		let offset = 0;
		setRebuilding( 0 );
		try {
			for (;;) {
				const batch = await apiFetch( {
					path: '/estatecraft/v1/index/rebuild',
					method: 'POST',
					data: { offset },
				} );
				offset = batch.next;
				setRebuilding( offset );
				if ( batch.complete ) {
					break;
				}
			}
			await load();
		} catch ( e ) {
			setError( e.message );
		}
		setRebuilding( null );
	};

	if ( ! status ) {
		return error ? (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		) : (
			<Spinner />
		);
	}
	const report = status.migration.report;

	return (
		<>
			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }
			<section className="estatecraft-settings__section">
				<h2>{ __( 'Search index', 'wp-smart-property' ) }</h2>
				<p>
					{ sprintf(
						/* translators: %d: number of properties */
						_n(
							'%d property is in the search index.',
							'%d properties are in the search index.',
							status.indexed,
							'wp-smart-property'
						),
						status.indexed
					) }
				</p>
				<p className="description">
					{ __(
						'The index is updated whenever a property changes. Rebuild it after importing properties directly into the database.',
						'wp-smart-property'
					) }
				</p>
				<Button
					variant="secondary"
					isBusy={ rebuilding !== null }
					disabled={ rebuilding !== null }
					onClick={ rebuild }
				>
					{ rebuilding !== null
						? sprintf(
								/* translators: %d: properties done so far */
								__(
									'Rebuilding… %d done',
									'wp-smart-property'
								),
								rebuilding
						  )
						: __( 'Rebuild search index', 'wp-smart-property' ) }
				</Button>
			</section>
			<section className="estatecraft-settings__section" id="tools">
				<h2>
					{ __(
						'Upgrade from WP Smart Property 2.0',
						'wp-smart-property'
					) }
				</h2>
				{ ! status.migration.done && (
					<Notice status="info" isDismissible={ false }>
						{ __(
							'The upgrade is still running in the background.',
							'wp-smart-property'
						) }
					</Notice>
				) }
				{ report.properties === 0 && report.terms === 0 ? (
					<p>
						{ __(
							'There was no 2.0 data to move on this site.',
							'wp-smart-property'
						) }
					</p>
				) : (
					<>
						<table className="widefat striped estatecraft-settings__report">
							<tbody>
								<tr>
									<th>
										{ __(
											'Properties updated',
											'wp-smart-property'
										) }
									</th>
									<td>{ report.properties }</td>
								</tr>
								<tr>
									<th>
										{ __(
											'Types, categories and locations moved',
											'wp-smart-property'
										) }
									</th>
									<td>{ report.terms }</td>
								</tr>
								<tr>
									<th>
										{ __(
											'Bedroom numbers set',
											'wp-smart-property'
										) }
									</th>
									<td>{ report.bedrooms }</td>
								</tr>
								<tr>
									<th>
										{ __(
											'Bathroom numbers set',
											'wp-smart-property'
										) }
									</th>
									<td>{ report.bathrooms }</td>
								</tr>
								<tr>
									<th>
										{ __(
											'Addresses queued for the map',
											'wp-smart-property'
										) }
									</th>
									<td>{ report.addresses }</td>
								</tr>
							</tbody>
						</table>
						{ report.features.length > 0 && (
							<p>
								{ __(
									'These Bed Room and Bath Room names were not numbers, so they became features:',
									'wp-smart-property'
								) }{ ' ' }
								<strong>
									{ report.features.join( ', ' ) }
								</strong>
							</p>
						) }
						<p className="description">
							{ __(
								'The 2.0 data is kept unchanged, and old city, country, bedroom and bathroom links redirect to the new pages.',
								'wp-smart-property'
							) }
						</p>
					</>
				) }
			</section>
		</>
	);
}
