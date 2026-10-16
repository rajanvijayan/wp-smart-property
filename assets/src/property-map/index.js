/**
 * Property location maps. Leaflet is loaded only when a map scrolls into view.
 */
import 'leaflet/dist/leaflet.css';
import './index.scss';

function draw( L, element ) {
	let config;
	try {
		config = JSON.parse( element.dataset.estatecraftMap );
	} catch ( e ) {
		return;
	}
	const point = [ config.lat, config.lng ];
	const map = L.map( element, {
		center: point,
		zoom: config.zoom,
		scrollWheelZoom: false,
	} );
	L.tileLayer( config.tiles, {
		attribution: config.attribution,
		maxZoom: 19,
	} ).addTo( map );
	if ( config.approximate ) {
		L.circle( point, {
			radius: config.radius,
			color: 'currentColor',
			weight: 2,
			fillOpacity: 0.15,
			className: 'estatecraft-map__area',
		} ).addTo( map );
	} else {
		L.marker( point, {
			icon: L.divIcon( {
				className: 'estatecraft-map__pin',
				html: '<span></span>',
				iconSize: [ 28, 38 ],
				iconAnchor: [ 14, 38 ],
			} ),
			keyboard: false,
		} ).addTo( map );
	}
}

function load( element ) {
	import( /* webpackChunkName: "leaflet" */ 'leaflet' ).then( ( module ) =>
		draw( module.default || module, element )
	);
}

function init() {
	const maps = document.querySelectorAll( '[data-estatecraft-map]' );
	if ( ! ( 'IntersectionObserver' in window ) ) {
		maps.forEach( load );
		return;
	}
	const observer = new window.IntersectionObserver(
		( entries ) =>
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					observer.unobserve( entry.target );
					load( entry.target );
				}
			} ),
		{ rootMargin: '200px' }
	);
	maps.forEach( ( map ) => observer.observe( map ) );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
