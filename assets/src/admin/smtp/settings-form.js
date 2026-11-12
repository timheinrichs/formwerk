import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { describeError } from './errors';

const STANDARD_PORTS = { tls: 587, ssl: 465, none: 25 };

// In the order of the page; the error summary follows it.
const FIELD_LABELS = {
	host: __( 'Mail server (SMTP host)', 'formwerk' ),
	encryption: __( 'Encryption', 'formwerk' ),
	port: __( 'Port', 'formwerk' ),
	auth: __( 'Server requires login', 'formwerk' ),
	username: __( 'Username', 'formwerk' ),
	password: __( 'Password', 'formwerk' ),
	from_email: __( 'Sender address', 'formwerk' ),
	from_name: __( 'Sender name', 'formwerk' ),
	force_from: __( 'Use this sender for every email', 'formwerk' ),
};

const fieldId = ( field ) => `formwerk-smtp-${ field }`;

function toForm( settings ) {
	return { ...settings, port: String( settings.port ), password: '' };
}

function ErrorText( { children } ) {
	return (
		<span className="formwerk-field-error">
			<svg
				viewBox="0 0 24 24"
				width="16"
				height="16"
				aria-hidden="true"
				focusable="false"
			>
				<path
					fill="currentColor"
					d="M12 3 1.5 21h21L12 3Zm1 14.5h-2v-2h2v2Zm0-3.5h-2V9h2v5Z"
				/>
			</svg>
			<span>
				{ __( 'Error:', 'formwerk' ) } { children }
			</span>
		</span>
	);
}

export default function SettingsForm( {
	data,
	onSaved,
	onDirtyChange,
	testCount,
} ) {
	const [ form, setForm ] = useState( () => toForm( data.settings ) );
	const [ saved, setSaved ] = useState( () => toForm( data.settings ) );
	const [ errors, setErrors ] = useState( data.errors || {} );
	const [ notice, setNotice ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const noticeRef = useRef();

	const isDirty = JSON.stringify( form ) !== JSON.stringify( saved );
	const isLocked = ( field ) => data.locked.includes( field );
	const errorFields = Object.keys( FIELD_LABELS ).filter(
		( field ) => errors[ field ]
	);

	useEffect( () => onDirtyChange( isDirty ), [ isDirty, onDirtyChange ] );

	// A test result is newer than "Saved": do not leave both on the page.
	useEffect( () => {
		setNotice( ( current ) =>
			current?.status === 'success' ? null : current
		);
	}, [ testCount ] );

	// Warn before leaving the page with unsaved changes.
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

	const clearErrors = ( ...fields ) =>
		setErrors( ( current ) => {
			const next = { ...current };
			fields.forEach( ( field ) => delete next[ field ] );
			return next;
		} );

	const set = ( field ) => ( value ) => {
		setForm( ( current ) => ( { ...current, [ field ]: value } ) );
		// Switching the login off removes its fields, and with them their errors.
		clearErrors(
			...( field === 'auth'
				? [ 'auth', 'username', 'password' ]
				: [ field ] )
		);
	};

	const setEncryption = ( value ) => {
		setForm( ( current ) => {
			// Follow the usual port, unless a different one was chosen on purpose.
			const usesStandardPort =
				Number( current.port ) === STANDARD_PORTS[ current.encryption ];
			return {
				...current,
				encryption: value,
				port:
					usesStandardPort && ! isLocked( 'port' )
						? String( STANDARD_PORTS[ value ] )
						: current.port,
			};
		} );
		clearErrors( 'encryption', 'port' );
	};

	const help = ( field, text ) => {
		if ( errors[ field ] ) {
			return <ErrorText>{ errors[ field ] }</ErrorText>;
		}
		return isLocked( field )
			? __( 'Fixed in wp-config.php.', 'formwerk' )
			: text;
	};

	const classes = ( field ) =>
		[
			errors[ field ] && 'formwerk-has-error',
			isLocked( field ) && 'formwerk-is-locked',
		]
			.filter( Boolean )
			.join( ' ' ) || undefined;

	const textProps = ( field, text, label = FIELD_LABELS[ field ] ) => ( {
		id: fieldId( field ),
		label,
		value: form[ field ],
		onChange: set( field ),
		// Read-only instead of disabled: the value stays reachable by keyboard and screen reader.
		readOnly: isLocked( field ),
		help: help( field, text ),
		className: classes( field ),
		'aria-invalid': errors[ field ] ? 'true' : undefined,
		__nextHasNoMarginBottom: true,
		__next40pxDefaultSize: true,
	} );

	const required = ( field ) =>
		sprintf(
			/* translators: %s: field label. */
			__( '%s (required)', 'formwerk' ),
			FIELD_LABELS[ field ]
		);

	const showNotice = ( next ) => {
		setNotice( next );
		if ( next.status !== 'success' ) {
			// Wait for the notice to render, then move focus to it.
			window.setTimeout( () => noticeRef.current?.focus(), 0 );
		}
	};

	const save = ( event ) => {
		event.preventDefault();
		if ( isSaving ) {
			return;
		}
		setIsSaving( true );
		setNotice( null );

		const { has_password: hasPassword, ...payload } = form;
		payload.port = Math.trunc( Number( form.port ) ) || 0;

		apiFetch( { path: '/formwerk/v1/smtp', method: 'POST', data: payload } )
			.then( ( response ) => {
				const next = toForm( response.settings );
				setForm( next );
				setSaved( next );
				setErrors( response.errors || {} );
				onSaved( response );

				if ( response.status === 'active' ) {
					showNotice( {
						status: 'success',
						message: __(
							'Saved. Now send a test email below to check that the mail server accepts it.',
							'formwerk'
						),
					} );
				} else if ( response.status === 'broken' ) {
					showNotice( {
						status: 'warning',
						hadFields: true,
						message: __(
							'Saved, but the mail server cannot be used yet. Emails are sent by the web server until this is corrected:',
							'formwerk'
						),
					} );
				} else {
					showNotice( {
						status: 'success',
						message: __(
							'Saved. No mail server is set, so emails are sent by the web server.',
							'formwerk'
						),
					} );
				}
			} )
			.catch( ( error ) => {
				const fields = error?.data?.fields;
				setErrors( fields || {} );
				showNotice( {
					status: 'error',
					hadFields: Boolean( fields ),
					message: fields
						? error.message
						: describeError(
								error,
								__(
									'The settings were not saved. Your entries are still here.',
									'formwerk'
								)
						  ),
				} );
			} )
			.finally( () => setIsSaving( false ) );
	};

	const focusField = ( field ) => ( event ) => {
		event.preventDefault();
		document.getElementById( fieldId( field ) )?.focus();
	};

	// An error notice whose fields were all corrected has nothing left to say.
	const noticeIsStale =
		notice?.hadFields && errorFields.length === 0 && ! isSaving;

	let statusLine;
	if ( data.status === 'active' ) {
		statusLine = (
			<p className="formwerk-state is-active">
				{ sprintf(
					/* translators: %s: host name of the mail server. */
					__(
						'Emails from this website are sent through %s.',
						'formwerk'
					),
					data.settings.host
				) }
			</p>
		);
	} else if ( data.status === 'broken' ) {
		statusLine = (
			<Notice status="error" isDismissible={ false }>
				{ __(
					'Emails are currently not sent through the mail server, because the settings below are incomplete or invalid. The web server sends them instead, which often ends in spam folders.',
					'formwerk'
				) }
			</Notice>
		);
	} else {
		statusLine = (
			<p className="formwerk-state">
				{ __(
					'No mail server is set. Emails are sent by the web server, which often ends in spam folders.',
					'formwerk'
				) }
			</p>
		);
	}

	return (
		<form onSubmit={ save } noValidate aria-busy={ isSaving }>
			{ data.locked.length > 0 && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Some settings are fixed by the person who maintains this website (in the file wp-config.php). They are shown here but cannot be changed.',
						'formwerk'
					) }
				</Notice>
			) }
			{ data.password_unreadable && (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'The saved mail password can no longer be read. This can happen after the website was moved. Enter the password again in the “Password” field and save.',
						'formwerk'
					) }
				</Notice>
			) }

			<Card className="formwerk-card">
				<CardHeader>
					<h2>{ __( 'Mail server', 'formwerk' ) }</h2>
				</CardHeader>
				<CardBody className="formwerk-fields">
					{ statusLine }
					<p className="formwerk-intro">
						{ __(
							'Emails sent through a real mailbox arrive more reliably and are less likely to land in spam. You get these details from your email provider.',
							'formwerk'
						) }
					</p>
					<TextControl
						{ ...textProps(
							'host',
							__(
								'For example smtp.example.com. Leave empty to send email without a mail server.',
								'formwerk'
							)
						) }
						autoComplete="off"
						spellCheck={ false }
					/>
					<div className="formwerk-row">
						<SelectControl
							id={ fieldId( 'encryption' ) }
							label={ FIELD_LABELS.encryption }
							value={ form.encryption }
							onChange={ setEncryption }
							disabled={ isLocked( 'encryption' ) }
							help={ help( 'encryption' ) }
							className={ classes( 'encryption' ) }
							options={ [
								{
									value: 'tls',
									label: __(
										'STARTTLS (usually port 587)',
										'formwerk'
									),
								},
								{
									value: 'ssl',
									label: __(
										'SSL/TLS (usually port 465)',
										'formwerk'
									),
								},
								{
									value: 'none',
									label: __(
										'None (not recommended)',
										'formwerk'
									),
								},
							] }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							{ ...textProps(
								'port',
								__(
									'Follows the encryption. Change it only if your provider names a different port.',
									'formwerk'
								)
							) }
							type="number"
							min={ 1 }
							max={ 65535 }
							step={ 1 }
							inputMode="numeric"
						/>
					</div>
					<ToggleControl
						id={ fieldId( 'auth' ) }
						label={ FIELD_LABELS.auth }
						checked={ form.auth }
						onChange={ set( 'auth' ) }
						disabled={ isLocked( 'auth' ) }
						help={ help(
							'auth',
							__( 'Almost every mail server does.', 'formwerk' )
						) }
						__nextHasNoMarginBottom
					/>
					{ form.auth && (
						<>
							<TextControl
								{ ...textProps(
									'username',
									__(
										'Often the full email address of the mailbox.',
										'formwerk'
									),
									required( 'username' )
								) }
								autoComplete="off"
								spellCheck={ false }
							/>
							{ isLocked( 'password' ) ? (
								<TextControl
									{ ...textProps( 'password' ) }
									value="••••••••"
									type="password"
								/>
							) : (
								<TextControl
									{ ...textProps(
										'password',
										form.has_password
											? __(
													'A password is saved. Leave empty to keep it.',
													'formwerk'
											  )
											: __(
													'Stored encrypted in the database.',
													'formwerk'
											  ),
										form.has_password
											? FIELD_LABELS.password
											: required( 'password' )
									) }
									type="password"
									autoComplete="new-password"
								/>
							) }
							{ data.key_in_database && (
								<p className="formwerk-hint">
									{ __(
										'Note for developers: this website has no security keys in wp-config.php, so the key that protects the password lies in the same database. Add the keys, or set the password with the constant FORMWERK_SMTP_PASSWORD.',
										'formwerk'
									) }
								</p>
							) }
						</>
					) }
				</CardBody>
			</Card>

			<Card className="formwerk-card">
				<CardHeader>
					<h2>{ __( 'Sender', 'formwerk' ) }</h2>
				</CardHeader>
				<CardBody className="formwerk-fields">
					<TextControl
						{ ...textProps(
							'from_email',
							__(
								'Use the address of the mailbox above. Other addresses are often rejected or marked as spam.',
								'formwerk'
							)
						) }
						type="email"
						autoComplete="off"
						spellCheck={ false }
					/>
					<TextControl
						{ ...textProps(
							'from_name',
							__(
								'Shown to recipients, for example the name of your website.',
								'formwerk'
							)
						) }
						autoComplete="off"
					/>
					<ToggleControl
						id={ fieldId( 'force_from' ) }
						label={ FIELD_LABELS.force_from }
						checked={ form.force_from }
						onChange={ set( 'force_from' ) }
						disabled={ isLocked( 'force_from' ) }
						help={ help(
							'force_from',
							__(
								'Also replaces senders that other plugins set. Switch this on if their emails are rejected.',
								'formwerk'
							)
						) }
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>

			<div className="formwerk-actions">
				<Button
					variant="primary"
					type="submit"
					isBusy={ isSaving }
					disabled={ isSaving || ! isDirty }
					accessibleWhenDisabled
					__next40pxDefaultSize
				>
					{ isSaving
						? __( 'Saving…', 'formwerk' )
						: __( 'Save settings', 'formwerk' ) }
				</Button>
				<span className="formwerk-status" role="status">
					{ isDirty && __( 'You have unsaved changes.', 'formwerk' ) }
				</span>
			</div>

			{ notice && ! noticeIsStale && (
				<div
					ref={ noticeRef }
					tabIndex={ -1 }
					className="formwerk-notice"
				>
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
						spokenMessage={ notice.message }
					>
						<p>{ notice.message }</p>
						{ notice.status !== 'success' &&
							errorFields.length > 0 && (
								<ul>
									{ errorFields.map( ( field ) => (
										<li key={ field }>
											<a
												href={ `#${ fieldId(
													field
												) }` }
												onClick={ focusField( field ) }
											>
												{ FIELD_LABELS[ field ] }
											</a>
											{ ': ' }
											{ errors[ field ] }
										</li>
									) ) }
								</ul>
							) }
					</Notice>
				</div>
			) }
		</form>
	);
}
