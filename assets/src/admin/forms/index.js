import { Button, Modal } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Builder from './builder';
import FormList from './form-list';

function readRoute() {
	const value = new URLSearchParams( window.location.search ).get( 'form' );
	if ( value === 'new' ) {
		return 0;
	}
	return value && /^\d+$/.test( value ) ? Number( value ) : null;
}

function urlFor( formId ) {
	const url = new URL( window.location.href );
	if ( formId === null ) {
		url.searchParams.delete( 'form' );
	} else {
		url.searchParams.set( 'form', formId === 0 ? 'new' : String( formId ) );
	}
	return url.toString();
}

/**
 * Forms screen: the list, or the builder for one form. The open form is part
 * of the address, so reload, back button and bookmarks work. Leaving a form with
 * unsaved changes always asks first, also with the browser's back button.
 */
export default function FormsScreen() {
	const [ route, setRoute ] = useState( () => ( {
		formId: readRoute(),
		session: 0,
		lastFormId: null,
		navigated: false,
	} ) );
	const [ pending, setPending ] = useState( undefined );
	const dirty = useRef( false );

	const go = useCallback(
		( formId, { replace = false, fromHistory = false } = {} ) => {
			if ( ! fromHistory ) {
				window.history[ replace ? 'replaceState' : 'pushState' ](
					{},
					'',
					urlFor( formId )
				);
			}
			setRoute( ( current ) => ( {
				formId,
				// A new session gives the builder a fresh state. Saving a new form keeps the session.
				session: replace ? current.session : current.session + 1,
				lastFormId: current.formId,
				navigated: true,
			} ) );
			if ( ! replace ) {
				dirty.current = false;
			}
		},
		[]
	);

	const request = useCallback(
		( formId ) => {
			if ( dirty.current && formId !== route.formId ) {
				setPending( formId );
				return;
			}
			go( formId );
		},
		[ go, route.formId ]
	);

	useEffect( () => {
		const onPop = () => {
			const target = readRoute();
			if ( dirty.current && target !== route.formId ) {
				// Put the address back and ask; the browser has moved already.
				window.history.pushState( {}, '', urlFor( route.formId ) );
				setPending( target );
				return;
			}
			go( target, { fromHistory: true } );
		};
		window.addEventListener( 'popstate', onPop );
		return () => window.removeEventListener( 'popstate', onPop );
	}, [ go, route.formId ] );

	return (
		<>
			{ route.formId === null ? (
				<FormList
					onOpen={ request }
					focusFormId={ route.navigated ? route.lastFormId : null }
				/>
			) : (
				<Builder
					key={ route.session }
					formId={ route.formId }
					focusTitle={ route.navigated }
					onDirtyChange={ ( value ) => {
						dirty.current = value;
					} }
					onBack={ () => request( null ) }
					onCreated={ ( id ) => go( id, { replace: true } ) }
				/>
			) }
			{ pending !== undefined && (
				<Modal
					title={ __( 'Leave without saving?', 'formwerk' ) }
					onRequestClose={ () => setPending( undefined ) }
					size="small"
				>
					<p>
						{ __(
							'Your changes to this form have not been saved and will be lost.',
							'formwerk'
						) }
					</p>
					<div className="formwerk-modal-actions">
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => {
								const target = pending;
								setPending( undefined );
								dirty.current = false;
								go( target );
							} }
							__next40pxDefaultSize
						>
							{ __( 'Discard changes', 'formwerk' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => setPending( undefined ) }
							__next40pxDefaultSize
						>
							{ __( 'Keep editing', 'formwerk' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
}
