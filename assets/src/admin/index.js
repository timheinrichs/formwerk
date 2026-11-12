/**
 * Admin entry point. Each admin screen renders a root element with a
 * `data-formwerk-screen` attribute; screens register themselves here.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

const screens = {};

domReady( () => {
	document.querySelectorAll( '[data-formwerk-screen]' ).forEach( ( node ) => {
		const Screen = screens[ node.dataset.formwerkScreen ];
		if ( Screen ) {
			createRoot( node ).render( <Screen /> );
		}
	} );
} );
