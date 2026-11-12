import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	TextControl,
} from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { describeError } from './errors';

const HINT_ID = 'formwerk-test-hint';

function CopyButton( { text } ) {
	const [ copied, setCopied ] = useState( false );
	const ref = useCopyToClipboard( text, () => setCopied( true ) );

	useEffect( () => {
		if ( ! copied ) {
			return undefined;
		}
		const timer = window.setTimeout( () => setCopied( false ), 4000 );
		return () => window.clearTimeout( timer );
	}, [ copied ] );

	return (
		<>
			<Button variant="secondary" ref={ ref } __next40pxDefaultSize>
				{ __( 'Copy details', 'formwerk' ) }
			</Button>
			<span className="formwerk-status" role="status">
				{ copied && __( 'Copied.', 'formwerk' ) }
			</span>
		</>
	);
}

export default function TestMail( { status, isDirty, onDone } ) {
	const [ to, setTo ] = useState( window.formwerkAdmin?.userEmail || '' );
	const [ isSending, setIsSending ] = useState( false );
	const [ result, setResult ] = useState( null );

	// A result belongs to the settings it was made with.
	useEffect( () => {
		if ( isDirty ) {
			setResult( null );
		}
	}, [ isDirty ] );

	const send = ( event ) => {
		event.preventDefault();
		if ( isSending || isDirty || to.trim() === '' ) {
			return;
		}
		setIsSending( true );
		setResult( null );

		apiFetch( {
			path: '/formwerk/v1/smtp/test',
			method: 'POST',
			data: { to },
		} )
			.then( ( response ) =>
				setResult( {
					status: response.sent ? 'success' : 'error',
					message: response.message,
					details: response.sent ? '' : response.details,
					debug: response.debug,
				} )
			)
			.catch( ( error ) =>
				setResult( {
					status: 'error',
					message:
						error?.code === 'formwerk_invalid_recipient'
							? error.message
							: describeError(
									error,
									__(
										'The test could not be started.',
										'formwerk'
									)
							  ),
					details: '',
					debug: '',
				} )
			)
			.finally( () => {
				setIsSending( false );
				onDone();
			} );
	};

	let intro = __(
		'No mail server is set. The test is sent by the web server, the way WordPress sends email by default.',
		'formwerk'
	);
	if ( status === 'active' ) {
		intro = __(
			'Sends a short email with the saved settings, so you can check that it arrives.',
			'formwerk'
		);
	} else if ( status === 'broken' ) {
		intro = __(
			'The mail server settings above cannot be used yet. Until they are corrected, the test is sent by the web server and does not check the mail server.',
			'formwerk'
		);
	}

	const technical = [ result?.details, result?.debug ]
		.filter( Boolean )
		.join( '\n\n' );

	return (
		<Card className="formwerk-card">
			<CardHeader>
				<h2>{ __( 'Send a test email', 'formwerk' ) }</h2>
			</CardHeader>
			<CardBody className="formwerk-fields">
				<p className="formwerk-intro">{ intro }</p>
				{ isDirty && (
					<p className="formwerk-hint" id={ HINT_ID }>
						{ __(
							'Save your changes first. The test always uses the saved settings.',
							'formwerk'
						) }
					</p>
				) }
				<form onSubmit={ send } className="formwerk-inline-form">
					<TextControl
						label={ __( 'Send test to', 'formwerk' ) }
						type="email"
						value={ to }
						onChange={ setTo }
						required
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<Button
						variant="secondary"
						type="submit"
						isBusy={ isSending }
						disabled={ isSending || isDirty || to.trim() === '' }
						accessibleWhenDisabled
						aria-describedby={ isDirty ? HINT_ID : undefined }
						__next40pxDefaultSize
					>
						{ isSending
							? __( 'Sending…', 'formwerk' )
							: __( 'Send test email', 'formwerk' ) }
					</Button>
				</form>
				{ result && (
					<Notice
						status={ result.status }
						isDismissible={ false }
						spokenMessage={ result.message }
					>
						<p>{ result.message }</p>
						{ technical && (
							<details className="formwerk-debug">
								<summary>
									{ __(
										'Conversation with the mail server (technical details)',
										'formwerk'
									) }
								</summary>
								<p>
									{ __(
										'Passwords and the email text are hidden. You can send these details to your email provider or developer.',
										'formwerk'
									) }
								</p>
								<pre
									// eslint-disable-next-line jsx-a11y/no-noninteractive-tabindex -- Scrollable region must be reachable by keyboard.
									tabIndex={ 0 }
									role="region"
									aria-label={ __(
										'Conversation with the mail server',
										'formwerk'
									) }
								>
									{ result.details &&
										sprintf(
											/* translators: %s: raw error message. */
											__( 'Error: %s', 'formwerk' ),
											result.details
										) + '\n\n' }
									{ result.debug }
								</pre>
								<div className="formwerk-actions">
									<CopyButton text={ technical } />
								</div>
							</details>
						) }
					</Notice>
				) }
			</CardBody>
		</Card>
	);
}
