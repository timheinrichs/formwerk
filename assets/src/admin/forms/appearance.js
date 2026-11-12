import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	RadioControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { describeError } from '../smtp/errors';

const PATH = '/formwerk/v1/settings';

// One setting for all forms of the website. It changes every page, so it is only
// applied when the person presses "Save", never while moving through the options.
export default function Appearance() {
	const [ saved, setSaved ] = useState( null );
	const [ style, setStyle ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );

	useEffect( () => {
		apiFetch( { path: PATH } )
			.then( ( response ) => {
				setSaved( response.frontend_style );
				setStyle( response.frontend_style );
			} )
			.catch( ( error ) =>
				setLoadError(
					describeError(
						error,
						__(
							'The look of the forms could not be loaded.',
							'formwerk'
						)
					)
				)
			);
	}, [] );

	const save = () => {
		setIsSaving( true );
		setNotice( null );
		apiFetch( {
			path: PATH,
			method: 'POST',
			data: { frontend_style: style },
		} )
			.then( ( response ) => {
				setSaved( response.frontend_style );
				setNotice( {
					status: 'success',
					message: __(
						'Saved. The forms on your website now use this look.',
						'formwerk'
					),
				} );
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					message: describeError(
						error,
						__( 'The look was not changed.', 'formwerk' )
					),
				} )
			)
			.finally( () => setIsSaving( false ) );
	};

	return (
		<Card className="formwerk-card formwerk-appearance">
			<CardHeader>
				<h2>{ __( 'Look of the forms', 'formwerk' ) }</h2>
			</CardHeader>
			<CardBody>
				{ loadError && (
					<Notice status="error" isDismissible={ false }>
						{ loadError }
					</Notice>
				) }
				{ style !== null && (
					<>
						<RadioControl
							label={ __( 'Look of the forms', 'formwerk' ) }
							hideLabelFromVision
							selected={ style }
							onChange={ setStyle }
							options={ [
								{
									value: 'theme',
									label: __(
										'Like the rest of the website',
										'formwerk'
									),
									description: __(
										'Fields and buttons look the way your theme designs them. Choose this first.',
										'formwerk'
									),
								},
								{
									value: 'own',
									label: __(
										'Formwerk’s own look',
										'formwerk'
									),
									description: __(
										'Fields and buttons get a plain, complete design. Choose this if the forms look unfinished with your theme.',
										'formwerk'
									),
								},
							] }
						/>
						<div className="formwerk-actions formwerk-appearance__actions">
							<Button
								variant="secondary"
								onClick={ save }
								isBusy={ isSaving }
								disabled={ isSaving || style === saved }
								accessibleWhenDisabled
								__next40pxDefaultSize
							>
								{ __( 'Save look', 'formwerk' ) }
							</Button>
						</div>
					</>
				) }
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
						className="formwerk-inline-notice formwerk-appearance__notice"
					>
						{ notice.message }
					</Notice>
				) }
			</CardBody>
		</Card>
	);
}
