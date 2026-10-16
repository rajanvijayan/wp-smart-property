/**
 * Property search in place: the filter form, sorting, page links and views update the results
 * without a page load, the URL stays shareable, and Back/Forward work. The results map loads
 * Leaflet only when it is shown.
 */
import { __, sprintf } from '@wordpress/i18n';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import './index.scss';

const LISTS = [ 'type', 'status', 'location', 'feature' ];

/**
 * Search parameters from a form: empty values dropped, lists comma separated.
 *
 * @param {HTMLFormElement} form Form.
 * @return {URLSearchParams} Parameters.
 */
export function formParams( form ) {
	const out = new URLSearchParams();
	const lists = {};
	new window.FormData( form ).forEach( ( value, name ) => {
		if ( typeof value !== 'string' || value.trim() === '' ) {
			return;
		}
		const key = name.replace( /\[\]$/, '' );
		if ( LISTS.includes( key ) ) {
			( lists[ key ] = lists[ key ] || [] ).push( value );
		} else {
			out.set( key, value.trim() );
		}
	} );
	Object.entries( lists ).forEach( ( [ key, values ] ) =>
		out.set( key, values.join( ',' ) )
	);
	return out;
}

/**
 * Put parameters back into a form (Back/Forward).
 *
 * @param {HTMLFormElement} form   Form.
 * @param {URLSearchParams} params Parameters.
 */
export function fillForm( form, params ) {
	Array.from( form.elements ).forEach( ( field ) => {
		if ( ! field.name || field.type === 'submit' ) {
			return;
		}
		const key = field.name.replace( /\[\]$/, '' );
		const value = params.get( key ) || '';
		if ( field.type === 'checkbox' ) {
			field.checked = LISTS.includes( key )
				? value.split( ',' ).includes( field.value )
				: value === field.value;
		} else if (
			field.type !== 'hidden' ||
			[ 'sort', 'view' ].includes( key )
		) {
			field.value = LISTS.includes( key )
				? value.split( ',' )[ 0 ]
				: value;
		}
	} );
}

function text( tag, className, content ) {
	const element = document.createElement( tag );
	element.className = className;
	element.textContent = content;
	return element;
}

/**
 * Map popup, built from text nodes (marker data is never parsed as HTML).
 *
 * @param {Object} marker Marker data.
 * @return {HTMLElement} Popup content.
 */
function popup( marker ) {
	const link = document.createElement( 'a' );
	link.className = 'estatecraft-popup';
	link.href = marker.url;
	if ( marker.image ) {
		const img = document.createElement( 'img' );
		img.src = marker.image;
		img.alt = '';
		img.loading = 'lazy';
		link.appendChild( img );
	}
	link.appendChild(
		text( 'strong', 'estatecraft-popup__price', marker.price )
	);
	link.appendChild(
		text( 'span', 'estatecraft-popup__title', marker.title )
	);
	if ( marker.facts ) {
		link.appendChild(
			text( 'span', 'estatecraft-popup__facts', marker.facts )
		);
	}
	return link;
}

class Results {
	constructor( root ) {
		this.root = root;
		this.config = JSON.parse( root.dataset.estatecraftResults );
		this.form = document.querySelector( '[data-estatecraft-search]' );
		this.list = root.querySelector( '.estatecraft-listings__list' );
		this.pages = root.querySelector( '.estatecraft-results__pages' );
		this.summary = root.querySelector( '.estatecraft-results__summary' );
		this.sort = root.querySelector( '.estatecraft-results__sort select' );
		this.canvas = root.querySelector( '.estatecraft-results__canvas' );
		this.bounds = root.querySelector( '[data-estatecraft-bounds]' );
		this.note = root.querySelector( '.estatecraft-results__note' );
		this.params = new URLSearchParams( window.location.search );
		this.request = null;
		this.markerRequest = null;
		this.map = null;
		this.byId = {};

		root.querySelectorAll( '[data-estatecraft-nojs]' ).forEach(
			( element ) => ( element.hidden = true )
		);
		this.bounds.closest( 'label' ).hidden = false;
		this.bind();
		this.showView( root.dataset.view, false );
		window.history.replaceState(
			{ estatecraft: this.params.toString() },
			'',
			window.location.href
		);
	}

	bind() {
		if ( this.form ) {
			this.form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				const params = formParams( this.form );
				[ 'bounds', 'sort', 'view' ].forEach( ( key ) => {
					if ( this.params.has( key ) && ! params.has( key ) ) {
						params.set( key, this.params.get( key ) );
					}
				} );
				params.delete( 'pg' );
				// Searching from another archive (a location page) moves to the results page.
				this.config.base = this.form.action;
				this.search( params );
			} );
		}
		this.sort.addEventListener( 'change', () => {
			const params = new URLSearchParams( this.params );
			if ( this.sort.value === 'featured' ) {
				params.delete( 'sort' );
			} else {
				params.set( 'sort', this.sort.value );
			}
			params.delete( 'pg' );
			this.search( params );
		} );
		this.pages.addEventListener( 'click', ( event ) => {
			const link = event.target.closest( 'a' );
			if ( ! link ) {
				return;
			}
			event.preventDefault();
			this.search( new URL( link.href ).searchParams, { scroll: true } );
		} );
		this.root
			.querySelectorAll( '.estatecraft-results__view' )
			.forEach( ( link ) =>
				link.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					this.showView( link.dataset.view, true );
				} )
			);
		this.bounds.addEventListener( 'change', () => {
			const params = new URLSearchParams( this.params );
			params.delete( 'pg' );
			if ( this.bounds.checked && this.map ) {
				params.set( 'bounds', this.boxOf( this.map ) );
			} else {
				params.delete( 'bounds' );
			}
			this.search( params );
		} );
		// Highlight a card's pin.
		[ 'mouseover', 'focusin' ].forEach( ( type ) =>
			this.list.addEventListener( type, ( event ) =>
				this.highlight( event.target.closest( '[data-id]' ) )
			)
		);
		this.list.addEventListener( 'mouseleave', () =>
			this.highlight( null )
		);
		window.addEventListener( 'popstate', ( event ) => {
			if ( ! event.state || event.state.estatecraft === undefined ) {
				return;
			}
			const params = new URLSearchParams( event.state.estatecraft );
			if ( this.form ) {
				fillForm( this.form, params );
			}
			this.search( params, { push: false } );
		} );
	}

	url( params ) {
		const query = params.toString();
		return this.config.base + ( query ? '?' + query : '' );
	}

	async search( params, { push = true, scroll = false } = {} ) {
		this.params = new URLSearchParams( params );
		this.sort.value = this.params.get( 'sort' ) || 'featured';
		this.bounds.checked = this.params.has( 'bounds' );
		if ( push ) {
			window.history.pushState(
				{ estatecraft: this.params.toString() },
				'',
				this.url( this.params )
			);
		}
		this.updateViewLinks();
		if ( this.request ) {
			this.request.abort();
		}
		this.request = new window.AbortController();
		const query = new URLSearchParams( this.params );
		query.set( 'fields', 'results' );
		query.set( 'base', this.config.base );
		query.set( 'layout', this.config.layout );
		this.root.setAttribute( 'aria-busy', 'true' );
		try {
			const response = await window.fetch(
				this.config.endpoint +
					( this.config.endpoint.includes( '?' ) ? '&' : '?' ) +
					query,
				{ signal: this.request.signal }
			);
			if ( ! response.ok ) {
				throw new Error( response.statusText );
			}
			const data = await response.json();
			this.list.innerHTML = data.html;
			this.pages.innerHTML = data.pagination;
			this.summary.textContent = data.summary;
			if ( scroll ) {
				this.root.scrollIntoView( {
					behavior: 'smooth',
					block: 'start',
				} );
			}
		} catch ( error ) {
			if ( error.name === 'AbortError' ) {
				return;
			}
			this.summary.textContent = __(
				'The results could not be loaded. Please try again.',
				'wp-smart-property'
			);
		} finally {
			this.root.removeAttribute( 'aria-busy' );
		}
		if ( this.map ) {
			this.loadMarkers();
		}
	}

	updateViewLinks() {
		this.root
			.querySelectorAll( '.estatecraft-results__view' )
			.forEach( ( link ) => {
				const params = new URLSearchParams( this.params );
				params.delete( 'pg' );
				params.set( 'view', link.dataset.view );
				link.href = this.url( params );
			} );
	}

	showView( view, remember ) {
		this.root.dataset.view = view;
		this.root.classList.remove(
			'is-view-list',
			'is-view-map',
			'is-view-split'
		);
		this.root.classList.add( 'is-view-' + view );
		this.root
			.querySelectorAll( '.estatecraft-results__view' )
			.forEach( ( link ) => {
				const active = link.dataset.view === view;
				link.classList.toggle( 'is-active', active );
				if ( active ) {
					link.setAttribute( 'aria-current', 'true' );
				} else {
					link.removeAttribute( 'aria-current' );
				}
			} );
		if ( remember ) {
			this.params.set( 'view', view );
			window.history.replaceState(
				{ estatecraft: this.params.toString() },
				'',
				this.url( this.params )
			);
			this.updateViewLinks();
		}
		if ( view !== 'list' ) {
			this.ensureMap();
		}
	}

	async ensureMap() {
		if ( this.map ) {
			this.map.invalidateSize();
			return;
		}
		if ( this.loading ) {
			return;
		}
		this.loading = true;
		const L = (
			await import( /* webpackChunkName: "leaflet" */ 'leaflet' )
		).default;
		window.L = L;
		await import(
			/* webpackChunkName: "leaflet-cluster" */ 'leaflet.markercluster'
		);
		this.L = L;
		this.map = L.map( this.canvas, {
			center: this.config.center,
			zoom: this.config.zoom,
			scrollWheelZoom: false,
		} );
		L.tileLayer( this.config.tiles, {
			attribution: this.config.attrib,
			maxZoom: 19,
		} ).addTo( this.map );
		this.layer = this.config.cluster
			? L.markerClusterGroup( {
					showCoverageOnHover: false,
					maxClusterRadius: 50,
			  } )
			: L.layerGroup();
		this.layer.addTo( this.map );
		let timer;
		this.map.on( 'moveend', () => {
			if ( this.moving || ! this.bounds.checked ) {
				return;
			}
			clearTimeout( timer );
			timer = setTimeout( () => {
				const params = new URLSearchParams( this.params );
				params.set( 'bounds', this.boxOf( this.map ) );
				params.delete( 'pg' );
				this.search( params );
			}, 400 );
		} );
		this.loadMarkers();
	}

	boxOf( map ) {
		const b = map.getBounds();
		return [ b.getSouth(), b.getWest(), b.getNorth(), b.getEast() ]
			.map( ( n ) => n.toFixed( 4 ) )
			.join( ',' );
	}

	async loadMarkers() {
		if ( this.markerRequest ) {
			this.markerRequest.abort();
		}
		this.markerRequest = new window.AbortController();
		const query = new URLSearchParams( this.params );
		query.delete( 'pg' );
		query.set( 'fields', 'markers' );
		let data;
		try {
			const response = await window.fetch(
				this.config.endpoint +
					( this.config.endpoint.includes( '?' ) ? '&' : '?' ) +
					query,
				{ signal: this.markerRequest.signal }
			);
			data = await response.json();
		} catch ( error ) {
			return;
		}
		const L = this.L;
		this.layer.clearLayers();
		this.byId = {};
		const points = [];
		( data.markers || [] ).forEach( ( marker ) => {
			const pin = L.marker( [ marker.lat, marker.lng ], {
				icon: L.divIcon( {
					className:
						'estatecraft-price-pin' +
						( marker.approximate ? ' is-approximate' : '' ),
					html: '<span></span>',
					iconSize: null,
				} ),
				title: marker.title,
				alt: marker.title,
			} );
			pin.on( 'add', () => {
				const span = pin.getElement()?.querySelector( 'span' );
				if ( span ) {
					span.textContent =
						marker.label || __( 'View', 'wp-smart-property' );
				}
			} );
			pin.bindPopup( () => popup( marker ), { minWidth: 200 } );
			this.layer.addLayer( pin );
			this.byId[ marker.id ] = pin;
			points.push( [ marker.lat, marker.lng ] );
		} );
		this.note.hidden = ! data.capped;
		this.note.textContent = data.capped
			? sprintf(
					/* translators: 1: properties on the map, 2: all matches */
					__(
						'The map shows %1$d of %2$d properties. Zoom in or add filters to see the rest.',
						'wp-smart-property'
					),
					data.markers.length,
					data.total
			  )
			: '';
		// Fit the results, except while the visitor is choosing the area themselves.
		if ( points.length && ! this.bounds.checked ) {
			this.moving = true;
			this.map.fitBounds( points, { padding: [ 30, 30 ], maxZoom: 15 } );
			setTimeout( () => ( this.moving = false ), 500 );
		}
	}

	highlight( item ) {
		Object.values( this.byId ).forEach( ( pin ) =>
			pin.getElement()?.classList.remove( 'is-active' )
		);
		const pin = item && this.byId[ item.dataset.id ];
		if ( pin && pin.getElement() ) {
			pin.getElement().classList.add( 'is-active' );
		}
	}
}

function init() {
	document
		.querySelectorAll( '[data-estatecraft-results]' )
		.forEach( ( root ) => new Results( root ) );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
