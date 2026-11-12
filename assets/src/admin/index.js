/**
 * Admin entry point. Each admin screen renders a root element with a
 * `data-formwerk-screen` attribute; the matching component is mounted into it.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import SmtpScreen from './smtp';
import './admin.scss';

const screens = {
	smtp: SmtpScreen,
};

domReady( () => {
	document.querySelectorAll( '[data-formwerk-screen]' ).forEach( ( node ) => {
		const Screen = screens[ node.dataset.formwerkScreen ];
		if ( Screen ) {
			createRoot( node ).render( <Screen /> );
		}
	} );
} );
