/**
 * Pick the default map centre and zoom by moving a map.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Button } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

export default function MapDefaults( { value, set } ) {
	const element = useRef();
	const map = useRef();
	const layer = useRef();
	const [ view, setView ] = useState( null );

	useEffect( () => {
		map.current = L.map( element.current, {
			center: [ value.lat, value.lng ],
			zoom: value.zoom,
			scrollWheelZoom: false,
		} );
		const update = () => {
			const center = map.current.getCenter();
			setView( {
				lat: Math.round( center.lat * 1e4 ) / 1e4,
				lng: Math.round( center.lng * 1e4 ) / 1e4,
				zoom: map.current.getZoom(),
			} );
		};
		map.current.on( 'moveend', update );
		update();
		return () => {
			map.current.remove();
			map.current = null;
			layer.current = null;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// Show the tile URL being edited.
	useEffect( () => {
		if ( layer.current ) {
			layer.current.remove();
		}
		layer.current = L.tileLayer( value.tiles, {
			attribution: value.attribution,
			maxZoom: 19,
		} ).addTo( map.current );
	}, [ value.tiles, value.attribution ] );

	const same =
		view &&
		view.lat === value.lat &&
		view.lng === value.lng &&
		view.zoom === value.zoom;

	return (
		<>
			<div ref={ element } className="estatecraft-settings__map" />
			<div className="estatecraft-settings__map-tools">
				<Button
					variant="secondary"
					disabled={ ! view || same }
					onClick={ () => set( view ) }
				>
					{ __( 'Use this view', 'wp-smart-property' ) }
				</Button>
				<span>
					{ sprintf(
						/* translators: 1: latitude, 2: longitude, 3: zoom level */
						__(
							'Saved: %1$s, %2$s, zoom %3$d',
							'wp-smart-property'
						),
						value.lat,
						value.lng,
						value.zoom
					) }
				</span>
			</div>
		</>
	);
}
