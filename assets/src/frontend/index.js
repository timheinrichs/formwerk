/**
 * Progressive enhancement for Formwerk forms: sends the form without a page reload
 * and shows errors next to their fields. Without this script the form still works
 * through a normal page request. Plain DOM code, no dependencies.
 */
import './frontend.scss';

// A page token older than this is replaced as soon as someone starts filling in the form.
// Younger tokens stay: replacing them would restart the minimum time a person needs.
const TOKEN_REFRESH_AGE = 3600;

function text( tag, content, className ) {
	const node = document.createElement( tag );
	node.textContent = content;
	if ( className ) {
		node.className = className;
	}
	return node;
}

function randomId() {
	if ( window.crypto?.randomUUID ) {
		return window.crypto.randomUUID();
	}
	const bytes = new Uint8Array( 16 );
	window.crypto.getRandomValues( bytes );
	return Array.from( bytes, ( byte ) =>
		byte.toString( 16 ).padStart( 2, '0' )
	).join( '' );
}

// Field wrapper and the controls that belong to it, searched inside this form only:
// the same form can appear twice on a page.
function fieldParts( form, name ) {
	const field = form.querySelector(
		`[data-formwerk-name="${ window.CSS.escape( name ) }"]`
	);
	if ( ! field ) {
		return null;
	}
	return {
		field,
		controls: [ ...field.querySelectorAll( 'input, select, textarea' ) ],
		error: field.querySelector( '.formwerk-field__error' ),
		label: field.querySelector( '.formwerk-field__label, label' ),
	};
}

function describe( element, errorId, hasError ) {
	const help = element.dataset.formwerkHelp;
	if (
		! help &&
		! element.hasAttribute( 'aria-describedby' ) &&
		! hasError
	) {
		return;
	}
	const ids = [ help, hasError ? errorId : '' ].filter( Boolean ).join( ' ' );
	if ( ids ) {
		element.setAttribute( 'aria-describedby', ids );
	} else {
		element.removeAttribute( 'aria-describedby' );
	}
}

function setInvalid( element, hasError ) {
	if ( hasError ) {
		element.setAttribute( 'aria-invalid', 'true' );
	} else {
		element.removeAttribute( 'aria-invalid' );
	}
}

function markField( parts, message ) {
	const hasError = Boolean( message );
	const { field, controls, error } = parts;
	const isGroup = field.tagName === 'FIELDSET';

	field.classList.toggle( 'formwerk-field--error', hasError );
	if ( error ) {
		error.textContent = message || '';
		error.hidden = ! hasError;
	}
	if ( isGroup ) {
		if ( field.getAttribute( 'role' ) === 'radiogroup' ) {
			setInvalid( field, hasError );
		}
		describe( field, error?.id, hasError );
	}
	controls.forEach( ( control, index ) => {
		if ( control.type !== 'radio' ) {
			setInvalid( control, hasError );
		}
		// In a group only the first option carries the description, as on the server.
		if ( ! isGroup || index === 0 ) {
			describe( control, error?.id, hasError );
		}
	} );
}

function clearErrors( form ) {
	form.querySelectorAll( '[data-formwerk-name]' ).forEach( ( field ) => {
		const parts = fieldParts( form, field.dataset.formwerkName );
		if ( parts ) {
			markField( parts, '' );
		}
	} );
}

function showStatus( form, message, errors = {} ) {
	const status = form.querySelector( '.formwerk-form__status' );
	status.replaceChildren( text( 'p', message ) );

	const list = document.createElement( 'ul' );
	Object.keys( errors ).forEach( ( name ) => {
		const parts = fieldParts( form, name );
		if ( ! parts ) {
			return;
		}
		markField( parts, errors[ name ] );

		const target = parts.controls[ 0 ];
		const label = parts.label
			? parts.label.firstChild.textContent.replace( /[\s.:!?]+$/, '' )
			: name;
		const link = text( 'a', label );
		link.href = `#${ target.id }`;
		link.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			target.focus();
		} );
		const item = document.createElement( 'li' );
		item.append( link, `: ${ errors[ name ] }` );
		list.append( item );
	} );
	if ( list.children.length ) {
		status.append( list );
	}
	status.hidden = false;
	status.focus();
}

function tokenAge( form ) {
	const issued = Number(
		form.elements.formwerk_token.value.split( '.' )[ 0 ]
	);
	return issued ? Date.now() / 1000 - issued : Infinity;
}

async function refreshToken( form ) {
	try {
		const response = await fetch( `${ form.dataset.formwerkApi }/token`, {
			credentials: 'omit',
			cache: 'no-store',
		} );
		const data = await response.json();
		if ( data.token ) {
			form.elements.formwerk_token.value = data.token;
		}
	} catch ( error ) {
		// The token printed with the page stays in place.
	}
}

function showSuccess( form, message ) {
	const done = document.createElement( 'div' );
	done.className = `${ form.className } formwerk-form--sent`;
	done.id = form.id;
	const note = text( 'p', message, 'formwerk-form__success' );
	note.setAttribute( 'role', 'status' );
	note.tabIndex = -1;
	done.append( note );
	form.replaceWith( done );
	note.focus();
}

function enhance( form ) {
	const button = form.querySelector( '.formwerk-form__submit' );
	const buttonLabel = button.textContent;
	let isSending = false;

	// One ID per page view, so the server can tell a repeated request from a new message.
	form.elements.formwerk_sid.value = randomId();

	// Cached pages can carry an old token; replace it once someone starts on the form.
	let checked = false;
	const prepare = () => {
		if ( ! checked ) {
			checked = true;
			if ( tokenAge( form ) > TOKEN_REFRESH_AGE ) {
				refreshToken( form );
			}
		}
	};
	[ 'focusin', 'pointerdown', 'input' ].forEach( ( type ) =>
		form.addEventListener( type, prepare, { passive: true } )
	);

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		if ( isSending ) {
			return;
		}
		isSending = true;
		form.setAttribute( 'aria-busy', 'true' );
		button.setAttribute( 'aria-disabled', 'true' );
		button.textContent = form.dataset.formwerkSending || buttonLabel;

		let result = null;
		try {
			const response = await fetch(
				`${ form.dataset.formwerkApi }/submissions`,
				{
					method: 'POST',
					body: new window.FormData( form ),
					credentials: 'omit',
					headers: { Accept: 'application/json' },
				}
			);
			result = await response.json();
		} catch ( error ) {
			result = null;
		}

		isSending = false;
		form.removeAttribute( 'aria-busy' );
		button.removeAttribute( 'aria-disabled' );
		button.textContent = buttonLabel;

		if ( ! result || ! result.status ) {
			// Not a Formwerk answer (network down, server error, timeout): hand over to the
			// normal page request. It carries the same attempt ID, so a message the server
			// stored already is confirmed instead of stored twice.
			window.HTMLFormElement.prototype.submit.call( form );
			return;
		}
		if ( result.token ) {
			form.elements.formwerk_token.value = result.token;
		}
		clearErrors( form );

		if ( result.status === 'ok' ) {
			showSuccess( form, result.message );
			return;
		}
		showStatus( form, result.message, result.errors || {} );
	} );
}

function init() {
	document.querySelectorAll( 'form[data-formwerk-form]' ).forEach( enhance );

	// After a request without JavaScript the page shows the result; move focus to it.
	if ( window.location.hash.startsWith( '#formwerk-' ) ) {
		const copy = document.getElementById( window.location.hash.slice( 1 ) );
		copy?.querySelector(
			'.formwerk-form__success, .formwerk-form__status:not([hidden])'
		)?.focus();
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
