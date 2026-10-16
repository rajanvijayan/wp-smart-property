/**
 * A Leaflet map with one draggable pin. Clicking the map moves the pin there.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const pin = L.divIcon( {
	className: 'estatecraft-pin',
	html: '<span></span>',
	iconSize: [ 28, 38 ],
	iconAnchor: [ 14, 38 ],
} );

const hasPoint = ( lat, lng ) => !! ( Number( lat ) || Number( lng ) );

export default function MapPicker( { lat, lng, maps, onChange } ) {
	const element = useRef();
	const map = useRef();
	const marker = useRef();
	const change = useRef( onChange );
	change.current = onChange;

	// Create the map once.
	useEffect( () => {
		const start = hasPoint( lat, lng )
			? [ Number( lat ), Number( lng ) ]
			: [ maps.lat, maps.lng ];
		map.current = L.map( element.current, {
			center: start,
			zoom: hasPoint( lat, lng ) ? 16 : maps.zoom,
			scrollWheelZoom: false,
		} );
		L.tileLayer( maps.tiles, {
			attribution: maps.attribution,
			maxZoom: 19,
		} ).addTo( map.current );
		map.current.on( 'click', ( event ) =>
			change.current( event.latlng.lat, event.latlng.lng )
		);
		// The meta box may still be resizing when the map is created.
		const timer = setTimeout( () => map.current.invalidateSize(), 200 );
		return () => {
			clearTimeout( timer );
			map.current.remove();
			map.current = null;
			marker.current = null;
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	// Keep the pin in step with the coordinates.
	useEffect( () => {
		if ( ! hasPoint( lat, lng ) ) {
			if ( marker.current ) {
				marker.current.remove();
				marker.current = null;
			}
			return;
		}
		const point = [ Number( lat ), Number( lng ) ];
		if ( ! marker.current ) {
			marker.current = L.marker( point, {
				draggable: true,
				icon: pin,
				title: __( 'Drag to move the pin', 'wp-smart-property' ),
				keyboard: true,
			} ).addTo( map.current );
			marker.current.on( 'dragend', () => {
				const moved = marker.current.getLatLng();
				change.current( moved.lat, moved.lng );
			} );
		} else {
			marker.current.setLatLng( point );
		}
		if ( ! map.current.getBounds().contains( point ) ) {
			map.current.setView( point, Math.max( map.current.getZoom(), 15 ) );
		}
	}, [ lat, lng ] );

	return (
		<div
			ref={ element }
			className="estatecraft-map"
			role="application"
			aria-label={ __(
				'Map. Click to place the property pin.',
				'wp-smart-property'
			) }
		/>
	);
}

export { hasPoint };
