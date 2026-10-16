/**
 * Property pages: full-screen photo viewer, share and print buttons. Without this script the photos
 * are plain links to the full images and the buttons stay hidden.
 */
import { __, sprintf } from '@wordpress/i18n';
import './index.scss';

let viewer;

function createViewer() {
	const dialog = document.createElement( 'dialog' );
	dialog.className = 'estatecraft-viewer';
	dialog.innerHTML = `
		<div class="estatecraft-viewer__bar">
			<p class="estatecraft-viewer__count" aria-live="polite"></p>
			<button type="button" class="estatecraft-viewer__close"></button>
		</div>
		<figure class="estatecraft-viewer__stage">
			<img alt="" />
			<figcaption></figcaption>
		</figure>
		<button type="button" class="estatecraft-viewer__nav is-prev"><span aria-hidden="true">‹</span></button>
		<button type="button" class="estatecraft-viewer__nav is-next"><span aria-hidden="true">›</span></button>`;
	const close = dialog.querySelector( '.estatecraft-viewer__close' );
	close.textContent = __( 'Close', 'wp-smart-property' );
	const prev = dialog.querySelector( '.is-prev' );
	const next = dialog.querySelector( '.is-next' );
	prev.setAttribute(
		'aria-label',
		__( 'Previous photo', 'wp-smart-property' )
	);
	next.setAttribute( 'aria-label', __( 'Next photo', 'wp-smart-property' ) );

	const state = { items: [], index: 0, title: '' };
	const img = dialog.querySelector( 'img' );
	const caption = dialog.querySelector( 'figcaption' );
	const count = dialog.querySelector( '.estatecraft-viewer__count' );

	const show = ( index ) => {
		const total = state.items.length;
		state.index = ( index + total ) % total;
		const item = state.items[ state.index ];
		img.src = item.src;
		img.srcset = item.srcset || '';
		img.sizes = '100vw';
		img.alt = item.alt || state.title;
		caption.textContent = item.caption || '';
		count.textContent = sprintf(
			/* translators: 1: photo number, 2: number of photos */
			__( '%1$d of %2$d', 'wp-smart-property' ),
			state.index + 1,
			total
		);
		prev.hidden = next.hidden = total < 2;
		// Load the neighbours so moving through the photos is instant.
		[ 1, -1 ].forEach( ( step ) => {
			const near = state.items[ ( state.index + step + total ) % total ];
			if ( near ) {
				new window.Image().src = near.src;
			}
		} );
	};

	prev.addEventListener( 'click', () => show( state.index - 1 ) );
	next.addEventListener( 'click', () => show( state.index + 1 ) );
	close.addEventListener( 'click', () => dialog.close() );
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'ArrowLeft' ) {
			show( state.index - 1 );
		} else if ( event.key === 'ArrowRight' ) {
			show( state.index + 1 );
		}
	} );
	// Swipe on touch screens.
	let startX = null;
	dialog.addEventListener(
		'touchstart',
		( event ) => ( startX = event.touches[ 0 ].clientX ),
		{ passive: true }
	);
	dialog.addEventListener( 'touchend', ( event ) => {
		if ( startX === null ) {
			return;
		}
		const delta = event.changedTouches[ 0 ].clientX - startX;
		if ( Math.abs( delta ) > 50 ) {
			show( state.index + ( delta < 0 ? 1 : -1 ) );
		}
		startX = null;
	} );
	// A click on the backdrop closes the viewer.
	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );
	document.body.appendChild( dialog );

	return {
		open( items, index, title, opener ) {
			state.items = items;
			state.title = title;
			dialog.setAttribute(
				'aria-label',
				sprintf(
					/* translators: %s: property title */
					__( 'Photos of %s', 'wp-smart-property' ),
					title
				)
			);
			show( index );
			dialog.showModal();
			dialog.addEventListener( 'close', () => opener?.focus(), {
				once: true,
			} );
		},
	};
}

function initGallery( root ) {
	let items;
	try {
		items = JSON.parse( root.dataset.estatecraftGallery || '[]' );
	} catch ( e ) {
		return;
	}
	if ( ! items.length || typeof window.HTMLDialogElement === 'undefined' ) {
		return;
	}
	const open = ( index, opener ) => {
		viewer = viewer || createViewer();
		viewer.open( items, index, root.dataset.title || '', opener );
	};
	root.querySelectorAll( '[data-index]' ).forEach( ( element ) => {
		element.hidden = false;
		element.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			open( Number( element.dataset.index ) || 0, element );
		} );
	} );
}

function initActions() {
	document
		.querySelectorAll( '[data-estatecraft-share]' )
		.forEach( ( button ) => {
			const { url, title } = button.dataset;
			if ( ! navigator.share && ! navigator.clipboard ) {
				return;
			}
			button.hidden = false;
			const status = button.parentElement.querySelector(
				'.estatecraft-actions__status'
			);
			button.addEventListener( 'click', async () => {
				if ( navigator.share ) {
					try {
						await navigator.share( { url, title } );
					} catch ( e ) {
						// The visitor closed the share sheet.
					}
					return;
				}
				try {
					await navigator.clipboard.writeText( url );
					status.textContent = __(
						'Link copied',
						'wp-smart-property'
					);
				} catch ( e ) {
					status.textContent = url;
				}
			} );
		} );
	document
		.querySelectorAll( '[data-estatecraft-print]' )
		.forEach( ( button ) => {
			button.hidden = false;
			button.addEventListener( 'click', () => window.print() );
		} );
}

function init() {
	document
		.querySelectorAll( '[data-estatecraft-gallery]' )
		.forEach( initGallery );
	initActions();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
