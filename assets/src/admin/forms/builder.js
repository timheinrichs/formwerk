import apiFetch from '@wordpress/api-fetch';
import { speak } from '@wordpress/a11y';
import {
	Button,
	Card,
	CardBody,
	Notice,
	Snackbar,
	Spinner,
	TabPanel,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { describeError } from '../smtp/errors';
import FieldList from './field-list';
import FieldSettings from './field-settings';
import { Shortcode } from './form-list';
import ConfirmationTab from './confirmation-tab';
import MailTab from './mail-tab';
import Preview from './preview';
import { canBeBeside, detach, normalizeRows } from './layout';
import { createField, typeLabel } from './types';

const controlProps = {
	__nextHasNoMarginBottom: true,
	__next40pxDefaultSize: true,
};

const NARROW = '(max-width: 782px)';

// Which tab an error belongs to, by the start of its path.
const tabOf = ( path ) => {
	if ( path.startsWith( 'mail' ) ) {
		return 'mail';
	}
	if ( path.startsWith( 'confirm' ) ) {
		return 'confirm';
	}
	return path.startsWith( 'fields' ) ? 'fields' : null;
};

// Errors in the order of the page: name, fields from top to bottom, then the email.
const PATH_ORDER = [
	'title',
	'fields',
	'mail.to',
	'mail.subject',
	'mail.body',
	'confirm.to_field',
	'confirm.subject',
	'confirm.body',
	'form',
];
const sortErrors = ( list, fields ) =>
	[ ...list ].sort( ( a, b ) => {
		const byPath =
			PATH_ORDER.indexOf( a.path ) - PATH_ORDER.indexOf( b.path );
		if ( byPath !== 0 ) {
			return byPath;
		}
		const index = ( error ) =>
			fields.findIndex( ( field ) => field.id === error.field );
		return index( a ) - index( b );
	} );

const focusById = ( id, delay = 60 ) =>
	window.setTimeout( () => document.getElementById( id )?.focus(), delay );

export default function Builder( {
	formId,
	focusTitle,
	onDirtyChange,
	onBack,
	onCreated,
} ) {
	const [ form, setForm ] = useState( null );
	const [ saved, setSaved ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ attempt, setAttempt ] = useState( 0 );
	const [ errors, setErrors ] = useState( [] );
	const [ notice, setNotice ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ selectedId, setSelectedId ] = useState( null );
	const [ tab, setTab ] = useState( { name: 'fields', key: 0 } );
	const [ panel, setPanel ] = useState( 'settings' );
	const [ removed, setRemoved ] = useState( null );
	const loaded = useRef( null );
	const sent = useRef( null );
	const noticeRef = useRef();

	const isDirty =
		form !== null && JSON.stringify( form ) !== JSON.stringify( saved );

	useEffect( () => {
		// Load once per attempt. When a new form gets its ID after saving, nothing is reloaded.
		if ( loaded.current === attempt ) {
			return;
		}
		loaded.current = attempt;
		setLoadError( '' );
		apiFetch( { path: `/formwerk/v1/forms/${ formId }` } )
			.then( ( response ) => {
				setForm( response );
				setSaved( response );
				setSelectedId( response.fields[ 0 ]?.id || null );
				if ( focusTitle ) {
					focusById( 'formwerk-form-title' );
				}
			} )
			.catch( ( error ) =>
				setLoadError(
					error?.code === 'formwerk_form_missing'
						? error.message
						: describeError(
								error,
								__(
									'The form could not be loaded.',
									'formwerk'
								)
						  )
				)
			);
	}, [ formId, attempt, focusTitle ] );

	useEffect( () => onDirtyChange( isDirty ), [ isDirty, onDirtyChange ] );

	useEffect( () => {
		if ( ! isDirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ isDirty ] );

	// The page heading says what is being done.
	useEffect( () => {
		const heading = document.querySelector( '.wrap > h1' );
		if ( ! heading ) {
			return undefined;
		}
		const original = heading.textContent;
		heading.textContent = form?.id
			? __( 'Edit form', 'formwerk' )
			: __( 'Add form', 'formwerk' );
		return () => {
			heading.textContent = original;
		};
	}, [ form?.id ] );

	if ( loadError ) {
		return (
			<Notice
				status="error"
				isDismissible={ false }
				className="formwerk-load-error"
			>
				<p>{ loadError }</p>
				<div className="formwerk-modal-actions formwerk-modal-actions--start">
					<Button
						variant="secondary"
						onClick={ () => setAttempt( ( value ) => value + 1 ) }
						__next40pxDefaultSize
					>
						{ __( 'Try again', 'formwerk' ) }
					</Button>
					<Button
						variant="tertiary"
						onClick={ onBack }
						__next40pxDefaultSize
					>
						{ __( 'Back to all forms', 'formwerk' ) }
					</Button>
				</div>
			</Notice>
		);
	}
	if ( ! form ) {
		return (
			<p className="formwerk-loading" role="status">
				<Spinner />
				{ __( 'Loading form…', 'formwerk' ) }
			</p>
		);
	}

	const errorAt = ( path ) =>
		errors.find( ( error ) => error.path === path && ! error.field )
			?.message;
	const fieldError = ( id ) => errors.find( ( error ) => error.field === id );
	const errorIds = errors.map( ( error ) => error.field ).filter( Boolean );
	const mailErrors = {
		'mail.to': errorAt( 'mail.to' ),
		'mail.subject': errorAt( 'mail.subject' ),
		'mail.body': errorAt( 'mail.body' ),
		'confirm.to_field': errorAt( 'confirm.to_field' ),
		'confirm.subject': errorAt( 'confirm.subject' ),
		'confirm.body': errorAt( 'confirm.body' ),
	};
	const tabHasError = ( name ) =>
		errors.some( ( error ) => tabOf( error.path ) === name );

	const dropErrors = ( predicate ) =>
		setErrors( ( current ) =>
			current.filter( ( error ) => ! predicate( error ) )
		);

	const updateForm = ( next ) => {
		// A changed part is assumed corrected; saving checks it again.
		if ( next.title !== form.title ) {
			dropErrors( ( error ) => error.path === 'title' );
		}
		[ 'to', 'subject', 'body' ].forEach( ( key ) => {
			if ( next.mail[ key ] !== form.mail[ key ] ) {
				dropErrors( ( error ) => error.path === `mail.${ key }` );
			}
		} );
		[ 'enabled', 'to_field', 'subject', 'body' ].forEach( ( key ) => {
			if ( next.confirm[ key ] !== form.confirm[ key ] ) {
				dropErrors( ( error ) =>
					key === 'enabled'
						? error.path.startsWith( 'confirm.' )
						: error.path === `confirm.${ key }`
				);
			}
		} );
		setForm( next );
	};

	const setFields = ( fields ) =>
		setForm( ( current ) => ( {
			...current,
			fields: normalizeRows( fields ),
		} ) );

	const showTab = ( name ) =>
		setTab( ( current ) => ( { name, key: current.key + 1 } ) );

	const selectField = ( id, { reveal = false } = {} ) => {
		setSelectedId( id );
		setPanel( 'settings' );
		// On narrow screens the settings are below the whole list: take the person there.
		if ( reveal && window.matchMedia( NARROW ).matches ) {
			window.setTimeout( () => {
				const heading = document.getElementById(
					'formwerk-settings-heading'
				);
				heading?.scrollIntoView( {
					block: 'start',
					behavior: 'smooth',
				} );
				heading?.focus( { preventScroll: true } );
			}, 50 );
		}
	};

	const addField = ( type ) => {
		const field = createField( type );
		setFields( [ ...form.fields, field ] );
		selectField( field.id );
		dropErrors( ( error ) => error.code === 'fields_required' );
		// Put the cursor into the label, so the default can be overwritten at once.
		window.setTimeout( () => {
			const input = document.getElementById(
				`formwerk-field-${ field.id }-label`
			);
			input?.focus();
			input?.select();
		}, 80 );
	};

	const removeField = ( id ) => {
		const index = form.fields.findIndex( ( field ) => field.id === id );
		const field = form.fields[ index ];
		// The field next to it, if any, keeps its place but gets a row of its own.
		const fields = detach( form.fields, index ).filter(
			( item ) => item.id !== id
		);
		setFields( fields );
		dropErrors( ( error ) => error.field === id );
		setRemoved( { field, index } );
		const next = fields[ index ] || fields[ index - 1 ];
		if ( selectedId === id ) {
			setSelectedId( next?.id || null );
		}
		focusById( next ? `formwerk-row-${ next.id }` : 'formwerk-add-field' );
		speak(
			sprintf(
				/* translators: %s: field label. */
				__( 'Field “%s” removed.', 'formwerk' ),
				field.label || __( '(no label)', 'formwerk' )
			)
		);
	};

	const undoRemove = () => {
		if ( ! removed ) {
			return;
		}
		const fields = [ ...form.fields ];
		fields.splice( removed.index, 0, removed.field );
		setFields( fields );
		selectField( removed.field.id );
		focusById( `formwerk-row-${ removed.field.id }` );
		setRemoved( null );
	};

	const updateField = ( field ) => {
		setFields(
			form.fields.map( ( item ) =>
				item.id === field.id ? field : item
			)
		);
		dropErrors( ( error ) => error.field === field.id );
	};

	const goToError = ( error ) => ( event ) => {
		event.preventDefault();
		const name = tabOf( error.path );
		if ( name ) {
			showTab( name );
		}
		let target = 'formwerk-form-title';
		if ( error.field ) {
			selectField( error.field );
			target = `formwerk-field-${ error.field }-${
				error.code === 'options_required' ? 'options' : 'label'
			}`;
		} else if ( error.path === 'fields' ) {
			target = 'formwerk-add-field';
		} else if ( error.path.startsWith( 'mail.' ) ) {
			target = `formwerk-mail-${ error.path.slice( 5 ) }`;
		} else if ( error.path.startsWith( 'confirm.' ) ) {
			target = `formwerk-confirm-${ error.path.slice( 8 ) }`;
		}
		focusById( target, 120 );
	};

	const errorLabel = ( error ) => {
		if ( error.field ) {
			const field = form.fields.find(
				( item ) => item.id === error.field
			);
			return sprintf(
				/* translators: 1: field label, 2: field type. */
				__( 'Field “%1$s” (%2$s)', 'formwerk' ),
				field?.label || __( '(no label)', 'formwerk' ),
				typeLabel( field?.type )
			);
		}
		return (
			{
				title: __( 'Name of the form', 'formwerk' ),
				fields: __( 'Fields', 'formwerk' ),
				'mail.to': __( 'Send to', 'formwerk' ),
				'mail.subject': __( 'Subject', 'formwerk' ),
				'mail.body': __( 'Email text', 'formwerk' ),
				'confirm.to_field': __( 'Confirmation: recipient', 'formwerk' ),
				'confirm.subject': __( 'Confirmation: subject', 'formwerk' ),
				'confirm.body': __( 'Confirmation: text', 'formwerk' ),
			}[ error.path ] || __( 'Form', 'formwerk' )
		);
	};

	const save = ( event ) => {
		event?.preventDefault();
		if ( isSaving ) {
			return;
		}
		setIsSaving( true );
		setNotice( null );
		setRemoved( null );
		sent.current = JSON.stringify( form );

		apiFetch( {
			path: form.id
				? `/formwerk/v1/forms/${ form.id }`
				: '/formwerk/v1/forms',
			method: 'POST',
			data: {
				title: form.title,
				fields: form.fields.map( ( field ) => ( {
					...field,
					options: field.options
						.map( ( option ) => option.trim() )
						.filter( Boolean ),
				} ) ),
				mail: form.mail,
				confirm: form.confirm,
				messages: form.messages,
				settings: form.settings,
			},
		} )
			.then( ( response ) => {
				const isNew = ! form.id;
				setSaved( response );
				setErrors( [] );
				// Keep what was typed while saving; only take over the names and the ID from the server.
				setForm( ( current ) => {
					if ( JSON.stringify( current ) === sent.current ) {
						return response;
					}
					return {
						...current,
						id: response.id,
						fields: current.fields.map( ( field ) => {
							const match = response.fields.find(
								( item ) => item.id === field.id
							);
							return match && ! field.name
								? { ...field, name: match.name }
								: field;
						} ),
					};
				} );
				setNotice( {
					status: 'success',
					message: isNew
						? __(
								'Form saved. To show it on your website, add the “Formwerk form” block to a page, or paste this shortcode:',
								'formwerk'
						  )
						: __( 'Form saved.', 'formwerk' ),
					shortcode: isNew ? response.id : null,
				} );
				if ( isNew ) {
					onCreated( response.id );
				}
			} )
			.catch( ( error ) => {
				const list = sortErrors(
					error?.data?.errors || [],
					form.fields
				);
				setErrors( list );
				setNotice( {
					status: 'error',
					hadErrors: list.length > 0,
					message: list.length
						? error.message
						: describeError(
								error,
								__(
									'The form was not saved. Your changes are still here.',
									'formwerk'
								)
						  ),
				} );
				focusById( 'formwerk-builder-notice', 0 );
			} )
			.finally( () => setIsSaving( false ) );
	};

	const selectedIndex = form.fields.findIndex(
		( field ) => field.id === selectedId
	);
	const selected = form.fields[ selectedIndex ];
	const previousLabelOf = ( index ) =>
		canBeBeside( form.fields, index )
			? form.fields[ index - 1 ].label || __( '(no label)', 'formwerk' )
			: '';
	const mark = ( title, name ) =>
		tabHasError( name )
			? sprintf(
					/* translators: %s: tab title. */
					__( '%s (needs attention)', 'formwerk' ),
					title
			  )
			: title;

	let status = '';
	if ( isDirty ) {
		status = __( 'Unsaved changes', 'formwerk' );
	} else if ( form.id > 0 ) {
		status = __( 'All changes saved', 'formwerk' );
	} else {
		status = __( 'Not saved yet', 'formwerk' );
	}

	// All listed points are corrected: say so instead of keeping a red box with nothing in it.
	const allCorrected =
		notice?.status === 'error' && notice.hadErrors && errors.length === 0;

	return (
		<form className="formwerk-builder" onSubmit={ save } noValidate>
			<div className="formwerk-builder__bar">
				<Button variant="link" onClick={ onBack }>
					← { __( 'All forms', 'formwerk' ) }
				</Button>
				<div className="formwerk-builder__save">
					<span className="formwerk-status" role="status">
						{ status }
					</span>
					<Button
						variant="primary"
						type="submit"
						isBusy={ isSaving }
						disabled={ isSaving || ( ! isDirty && form.id > 0 ) }
						accessibleWhenDisabled
						__next40pxDefaultSize
					>
						{ isSaving
							? __( 'Saving…', 'formwerk' )
							: __( 'Save form', 'formwerk' ) }
					</Button>
				</div>
			</div>

			<TextControl
				{ ...controlProps }
				id="formwerk-form-title"
				className={ `formwerk-builder__title${
					errorAt( 'title' ) ? ' formwerk-has-error' : ''
				}` }
				label={ __( 'Name of the form', 'formwerk' ) }
				value={ form.title }
				onChange={ ( title ) => updateForm( { ...form, title } ) }
				placeholder={ __(
					'For example “Contact” or “Course registration”',
					'formwerk'
				) }
				aria-invalid={ errorAt( 'title' ) ? 'true' : undefined }
				help={
					errorAt( 'title' ) ? (
						<span className="formwerk-field-error">
							<span>
								{ __( 'Error:', 'formwerk' ) }{ ' ' }
								{ errorAt( 'title' ) }
							</span>
						</span>
					) : (
						__(
							'Only you see this name. It also appears in the notification email.',
							'formwerk'
						)
					)
				}
			/>

			{ notice && (
				<div
					ref={ noticeRef }
					id="formwerk-builder-notice"
					tabIndex={ -1 }
					className="formwerk-notice"
				>
					<Notice
						status={ allCorrected ? 'info' : notice.status }
						onRemove={ () => setNotice( null ) }
						spokenMessage={ notice.message }
					>
						<p>
							{ allCorrected
								? __(
										'All points are corrected. Save the form now.',
										'formwerk'
								  )
								: notice.message }
						</p>
						{ notice.shortcode && (
							<p>
								<Shortcode id={ notice.shortcode } />
							</p>
						) }
						{ notice.status === 'error' && errors.length > 0 && (
							<ul>
								{ errors.map( ( error, index ) => (
									<li key={ index }>
										<a
											href="#formwerk-form-title"
											onClick={ goToError( error ) }
										>
											{ errorLabel( error ) }
										</a>
										{ ': ' }
										{ error.message }
									</li>
								) ) }
							</ul>
						) }
					</Notice>
				</div>
			) }

			<TabPanel
				key={ tab.key }
				className="formwerk-tabs"
				initialTabName={ tab.name }
				onSelect={ ( name ) =>
					setTab( ( current ) => ( { ...current, name } ) )
				}
				tabs={ [
					{
						name: 'fields',
						title: mark( __( 'Fields', 'formwerk' ), 'fields' ),
					},
					{
						name: 'mail',
						title: mark( __( 'Notification', 'formwerk' ), 'mail' ),
					},
					{
						name: 'confirm',
						title: mark(
							__( 'Confirmation', 'formwerk' ),
							'confirm'
						),
					},
					{
						name: 'messages',
						title: __( 'Messages and spam protection', 'formwerk' ),
					},
				] }
			>
				{ ( current ) => (
					<Card className="formwerk-card formwerk-tab">
						<CardBody>
							{ current.name === 'fields' && (
								<div className="formwerk-builder__columns">
									<div>
										<h2>{ __( 'Fields', 'formwerk' ) }</h2>
										<FieldList
											fields={ form.fields }
											selectedId={ selectedId }
											errorIds={ errorIds }
											onSelect={ ( id ) =>
												selectField( id, {
													reveal: true,
												} )
											}
											onChange={ setFields }
											onAdd={ addField }
											onRemove={ removeField }
										/>
									</div>
									<div className="formwerk-builder__side">
										<div
											className="formwerk-switch"
											role="group"
											aria-label={ __(
												'Show',
												'formwerk'
											) }
										>
											<Button
												variant={
													panel === 'settings'
														? 'primary'
														: 'secondary'
												}
												aria-pressed={
													panel === 'settings'
												}
												onClick={ () =>
													setPanel( 'settings' )
												}
												__next40pxDefaultSize
											>
												{ __(
													'Field settings',
													'formwerk'
												) }
											</Button>
											<Button
												variant={
													panel === 'preview'
														? 'primary'
														: 'secondary'
												}
												aria-pressed={
													panel === 'preview'
												}
												onClick={ () =>
													setPanel( 'preview' )
												}
												__next40pxDefaultSize
											>
												{ __( 'Preview', 'formwerk' ) }
											</Button>
										</div>
										{ panel === 'settings' ? (
											<>
												<h2
													id="formwerk-settings-heading"
													tabIndex={ -1 }
												>
													{ __(
														'Field settings',
														'formwerk'
													) }
												</h2>
												<FieldSettings
													field={ selected }
													previousLabel={ previousLabelOf(
														selectedIndex
													) }
													error={
														selected &&
														fieldError(
															selected.id
														)
													}
													onChange={ updateField }
												/>
											</>
										) : (
											<Preview
												fields={ form.fields }
												submitLabel={
													form.messages.submit
												}
											/>
										) }
									</div>
								</div>
							) }
							{ current.name === 'mail' && (
								<MailTab
									form={ form }
									errors={ mailErrors }
									onChange={ updateForm }
								/>
							) }
							{ current.name === 'confirm' && (
								<ConfirmationTab
									form={ form }
									errors={ mailErrors }
									onChange={ updateForm }
								/>
							) }
							{ current.name === 'messages' && (
								<div className="formwerk-fields formwerk-messages">
									<TextControl
										{ ...controlProps }
										label={ __(
											'Text on the send button',
											'formwerk'
										) }
										value={ form.messages.submit }
										onChange={ ( submit ) =>
											setForm( {
												...form,
												messages: {
													...form.messages,
													submit,
												},
											} )
										}
									/>
									<TextareaControl
										{ ...controlProps }
										label={ __(
											'Message after sending',
											'formwerk'
										) }
										value={ form.messages.success }
										onChange={ ( success ) =>
											setForm( {
												...form,
												messages: {
													...form.messages,
													success,
												},
											} )
										}
										help={ __(
											'Replaces the form once a submission was saved. Say what happens next, for example when you will reply.',
											'formwerk'
										) }
										rows={ 3 }
									/>
									<h3>
										{ __( 'Spam protection', 'formwerk' ) }
									</h3>
									<TextControl
										{ ...controlProps }
										label={ __(
											'Links allowed per submission',
											'formwerk'
										) }
										type="number"
										min={ 0 }
										max={ 50 }
										value={ String(
											form.settings.max_links
										) }
										onChange={ ( value ) =>
											setForm( {
												...form,
												settings: {
													...form.settings,
													max_links: Math.max(
														0,
														Math.trunc(
															Number( value )
														) || 0
													),
												},
											} )
										}
										help={ __(
											'Spam often contains many links. Submissions with more links than this are rejected with a note to the sender. 0 means no limit.',
											'formwerk'
										) }
									/>
								</div>
							) }
						</CardBody>
					</Card>
				) }
			</TabPanel>

			{ removed && (
				<div className="formwerk-snackbar">
					<Snackbar
						onRemove={ () => setRemoved( null ) }
						actions={ [
							{
								label: __( 'Undo', 'formwerk' ),
								onClick: undoRemove,
							},
						] }
					>
						{ sprintf(
							/* translators: %s: field label. */
							__( 'Field “%s” removed.', 'formwerk' ),
							removed.field.label ||
								__( '(no label)', 'formwerk' )
						) }
					</Snackbar>
				</div>
			) }
		</form>
	);
}
