import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { describeError } from './errors';
import MailLog from './mail-log';
import SettingsForm from './settings-form';
import TestMail from './test-mail';

const PATH = '/formwerk/v1/smtp';

export default function SmtpScreen() {
	const [ data, setData ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ isDirty, setIsDirty ] = useState( false );
	const [ log, setLog ] = useState( { entries: [], error: '' } );
	const [ testCount, setTestCount ] = useState( 0 );

	const loadSettings = useCallback( () => {
		setLoadError( '' );
		apiFetch( { path: PATH } )
			.then( setData )
			.catch( ( error ) =>
				setLoadError(
					describeError(
						error,
						__( 'The settings could not be loaded.', 'formwerk' )
					)
				)
			);
	}, [] );

	const loadLog = useCallback( () => {
		apiFetch( { path: `${ PATH }/log` } )
			.then( ( response ) =>
				setLog( { entries: response.entries, error: '' } )
			)
			.catch( ( error ) =>
				setLog( ( current ) => ( {
					...current,
					error: describeError(
						error,
						__( 'The log could not be loaded.', 'formwerk' )
					),
				} ) )
			);
	}, [] );

	useEffect( () => {
		loadSettings();
		loadLog();
	}, [ loadSettings, loadLog ] );

	if ( loadError ) {
		return (
			<Notice
				status="error"
				isDismissible={ false }
				className="formwerk-load-error"
			>
				<p>{ loadError }</p>
				<Button
					variant="secondary"
					onClick={ loadSettings }
					__next40pxDefaultSize
				>
					{ __( 'Try again', 'formwerk' ) }
				</Button>
			</Notice>
		);
	}

	if ( ! data ) {
		return (
			<p className="formwerk-loading" role="status">
				<Spinner />
				{ __( 'Loading settings…', 'formwerk' ) }
			</p>
		);
	}

	return (
		<div className="formwerk-smtp">
			<SettingsForm
				data={ data }
				onSaved={ setData }
				onDirtyChange={ setIsDirty }
				testCount={ testCount }
			/>
			<TestMail
				status={ data.status }
				isDirty={ isDirty }
				onDone={ () => {
					setTestCount( ( count ) => count + 1 );
					loadLog();
				} }
			/>
			<MailLog
				entries={ log.entries }
				loadError={ log.error }
				onRetry={ loadLog }
				onCleared={ () => setLog( { entries: [], error: '' } ) }
			/>
		</div>
	);
}
