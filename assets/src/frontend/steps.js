/**
 * Forms "in steps": shows one step at a time with Back and Next. The server prints
 * every step, one below the other, so without this script the form still works as
 * one page. All texts come from data attributes the server writes in the site's
 * language.
 */

function button( label, className ) {
	const node = document.createElement( 'button' );
	node.type = 'button';
	node.className = className;
	node.textContent = label;
	return node;
}

function format( template, values ) {
	// A function as replacement: "$" in a step title stays as it is.
	return values.reduce(
		( result, value, index ) =>
			result.replace( `%${ index + 1 }$s`, () => String( value ) ),
		template
	);
}

/**
 * Adds the step navigation to a form.
 *
 * @param {HTMLFormElement} form          Form with `.formwerk-step` groups.
 * @param {Object}          helpers       Functions shared with the form script.
 * @param {Function}        helpers.check Asks the server about one step; resolves to its errors, or null when it cannot answer.
 * @param {Function}        helpers.show  Shows errors next to their fields and in the summary.
 * @param {Function}        helpers.clear Removes the errors of the given field names.
 * @return {Object|null} Controls for the form script, or null when there is nothing to step through.
 */
export function setupSteps( form, { check, show, clear } ) {
	const steps = [ ...form.querySelectorAll( '.formwerk-step' ) ];
	if ( steps.length < 2 ) {
		return null;
	}
	const data = form.dataset;
	const submit = form.querySelector( '.formwerk-form__submit' );
	const actions = form.querySelector( '.formwerk-form__actions' );
	const back = button(
		data.formwerkBack,
		'formwerk-form__back wp-element-button'
	);
	const next = button(
		data.formwerkNext,
		'formwerk-form__next wp-element-button'
	);
	actions.prepend( back );
	actions.insertBefore( next, submit );

	// "Step 2 of 3: Course", read out and focused when the step changes.
	const progress = document.createElement( 'div' );
	progress.className = 'formwerk-steps';
	const label = document.createElement( 'p' );
	label.className = 'formwerk-steps__label';
	label.tabIndex = -1;
	const bar = document.createElement( 'div' );
	bar.className = 'formwerk-steps__bar';
	bar.setAttribute( 'aria-hidden', 'true' );
	const fill = document.createElement( 'span' );
	bar.append( fill );
	progress.append( label, bar );
	form.querySelector( '.formwerk-form__fields' ).before( progress );
	form.classList.add( 'formwerk-form--stepping' );

	let current = 0;
	let checking = false;

	const namesOf = ( step ) =>
		[ ...step.querySelectorAll( '[data-formwerk-name]' ) ].map(
			( field ) => field.dataset.formwerkName
		);

	const status = form.querySelector( '.formwerk-form__status' );

	const go = ( index, { focus = true, keepStatus = false } = {} ) => {
		const target = Math.max( 0, Math.min( steps.length - 1, index ) );
		if ( target !== current && ! keepStatus && status ) {
			// The summary belongs to the step it was shown for; the marks at the fields stay.
			status.hidden = true;
		}
		current = target;
		steps.forEach( ( step, position ) => {
			step.hidden = position !== current;
		} );
		const last = current === steps.length - 1;
		back.hidden = current === 0;
		next.hidden = last;
		submit.hidden = ! last;
		const title =
			steps[ current ].querySelector( '.formwerk-step__title' )
				?.textContent || '';
		label.textContent = format( data.formwerkProgress, [
			current + 1,
			steps.length,
			title,
		] );
		fill.style.width = `${ ( ( current + 1 ) / steps.length ) * 100 }%`;
		if ( focus ) {
			label.focus();
		}
	};

	const forward = async () => {
		if ( checking ) {
			return;
		}
		checking = true;
		const from = current;
		next.setAttribute( 'aria-disabled', 'true' );
		back.setAttribute( 'aria-disabled', 'true' );
		next.textContent = data.formwerkChecking || data.formwerkNext;
		const step = steps[ from ];
		const errors = await check( step.dataset.formwerkStep );
		checking = false;
		next.removeAttribute( 'aria-disabled' );
		back.removeAttribute( 'aria-disabled' );
		next.textContent = data.formwerkNext;
		if ( current !== from ) {
			// The person moved on in the meantime; this answer is about another step.
			return;
		}

		clear( namesOf( step ) );
		if ( errors && Object.keys( errors ).length ) {
			show( errors );
			return;
		}
		// No answer (offline, server error): go on anyway. The check when sending counts.
		go( from + 1 );
	};

	back.addEventListener( 'click', () => {
		if ( ! checking ) {
			go( current - 1 );
		}
	} );
	next.addEventListener( 'click', forward );

	// Links in a summary the server printed (after sending without the script) may point
	// into another step: open it first, then the browser jumps to the field.
	form.addEventListener( 'click', ( event ) => {
		const link = event.target.closest?.(
			'.formwerk-form__status a[href^="#"]'
		);
		const target = link
			? document.getElementById( link.hash.slice( 1 ) )
			: null;
		const index = target
			? steps.findIndex( ( item ) => item.contains( target ) )
			: -1;
		if ( index >= 0 && index !== current ) {
			go( index, { focus: false, keepStatus: true } );
		}
	} );

	// After a request without the script, start where the first error is.
	const firstError = form.querySelector( '.formwerk-field--error' );
	const start = firstError
		? steps.findIndex( ( step ) => step.contains( firstError ) )
		: 0;
	go( Math.max( 0, start ), { focus: false, keepStatus: true } );

	return {
		isLast: () => current === steps.length - 1,
		forward,
		// Shows the step that holds an element, e.g. a field named in the error summary.
		reveal( element ) {
			const index = steps.findIndex( ( step ) =>
				step.contains( element )
			);
			if ( index >= 0 && index !== current ) {
				go( index, { focus: false, keepStatus: true } );
			}
		},
	};
}
