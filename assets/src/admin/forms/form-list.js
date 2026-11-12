import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { describeError } from '../smtp/errors';
import Appearance from './appearance';

export function Shortcode( { id } ) {
	const code = `[formwerk id="${ id }"]`;
	const [ copied, setCopied ] = useState( false );
	const ref = useCopyToClipboard( code, () => setCopied( true ) );

	useEffect( () => {
		if ( ! copied ) {
			return undefined;
		}
		const timer = window.setTimeout( () => setCopied( false ), 3000 );
		return () => window.clearTimeout( timer );
	}, [ copied ] );

	return (
		<span className="formwerk-shortcode">
			<code>{ code }</code>
			<Button
				variant="tertiary"
				size="small"
				ref={ ref }
				aria-label={
					copied
						? __( 'Shortcode copied', 'formwerk' )
						: sprintf(
								/* translators: %s: shortcode. */
								__( 'Copy shortcode %s', 'formwerk' ),
								code
						  )
				}
			>
				{ copied
					? __( 'Copied', 'formwerk' )
					: __( 'Copy', 'formwerk' ) }
			</Button>
			<span className="screen-reader-text" role="status">
				{ copied && __( 'Shortcode copied.', 'formwerk' ) }
			</span>
		</span>
	);
}

export default function FormList( { onOpen, focusFormId } ) {
	const [ forms, setForms ] = useState( null );
	const focused = useRef( false );

	// Coming back from a form: focus stays where the person left off.
	useEffect( () => {
		if ( ! forms || focused.current || focusFormId === undefined ) {
			return;
		}
		focused.current = true;
		const target =
			document.getElementById( `formwerk-edit-${ focusFormId }` ) ||
			document.getElementById( 'formwerk-add-form' );
		if ( focusFormId !== null ) {
			target?.focus();
		}
	}, [ forms, focusFormId ] );
	const [ error, setError ] = useState( '' );
	const [ toDelete, setToDelete ] = useState( null );
	const [ isDeleting, setIsDeleting ] = useState( false );

	const load = useCallback( () => {
		setError( '' );
		apiFetch( { path: '/formwerk/v1/forms' } )
			.then( ( response ) => setForms( response.forms ) )
			.catch( ( failure ) =>
				setError(
					describeError(
						failure,
						__( 'The forms could not be loaded.', 'formwerk' )
					)
				)
			);
	}, [] );

	useEffect( load, [ load ] );

	const remove = () => {
		setIsDeleting( true );
		apiFetch( {
			path: `/formwerk/v1/forms/${ toDelete.id }`,
			method: 'DELETE',
		} )
			.then( () => {
				setForms( ( current ) =>
					current.filter( ( form ) => form.id !== toDelete.id )
				);
				setToDelete( null );
			} )
			.catch( ( failure ) => {
				setToDelete( null );
				setError(
					describeError(
						failure,
						__( 'The form was not deleted.', 'formwerk' )
					)
				);
			} )
			.finally( () => setIsDeleting( false ) );
	};

	const addButton = (
		<Button
			id="formwerk-add-form"
			variant="primary"
			onClick={ () => onOpen( 0 ) }
			__next40pxDefaultSize
		>
			{ __( 'Add form', 'formwerk' ) }
		</Button>
	);

	return (
		<div className="formwerk-forms">
			{ error && (
				<Notice
					status="error"
					isDismissible={ false }
					className="formwerk-load-error"
				>
					<p>{ error }</p>
					<Button
						variant="secondary"
						onClick={ load }
						__next40pxDefaultSize
					>
						{ __( 'Try again', 'formwerk' ) }
					</Button>
				</Notice>
			) }

			{ ! forms && ! error && (
				<p className="formwerk-loading" role="status">
					<Spinner />
					{ __( 'Loading forms…', 'formwerk' ) }
				</p>
			) }

			{ forms && forms.length === 0 && (
				<div className="formwerk-empty">
					<h2>{ __( 'No forms yet', 'formwerk' ) }</h2>
					<p>
						{ __(
							'A form collects messages from visitors, for example a contact request or a registration. Every message is saved here and sent to you by email.',
							'formwerk'
						) }
					</p>
					{ addButton }
				</div>
			) }

			{ forms && forms.length > 0 && (
				<>
					<div className="formwerk-toolbar">{ addButton }</div>
					<div
						className="formwerk-table-wrap"
						// eslint-disable-next-line jsx-a11y/no-noninteractive-tabindex -- Scrollable region must be reachable by keyboard.
						tabIndex={ 0 }
						role="region"
						aria-label={ __( 'Forms', 'formwerk' ) }
					>
						<table className="widefat striped formwerk-form-table">
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Form', 'formwerk' ) }
									</th>
									<th scope="col">
										{ __( 'Shortcode', 'formwerk' ) }
									</th>
									<th scope="col">
										{ __(
											'Fields to fill in',
											'formwerk'
										) }
									</th>
									<th scope="col">
										{ __( 'Last changed', 'formwerk' ) }
									</th>
									<th scope="col">
										{ __( 'Actions', 'formwerk' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ forms.map( ( form ) => (
									<tr key={ form.id }>
										<th
											scope="row"
											data-colname={ __(
												'Form',
												'formwerk'
											) }
										>
											<Button
												variant="link"
												onClick={ () =>
													onOpen( form.id )
												}
											>
												{ form.title }
											</Button>
											{ form.spam > 0 && (
												<span className="formwerk-spam-count">
													{ sprintf(
														/* translators: %d: number of rejected spam attempts. */
														_n(
															'%d spam attempt rejected',
															'%d spam attempts rejected',
															form.spam,
															'formwerk'
														),
														form.spam
													) }
												</span>
											) }
										</th>
										<td
											data-colname={ __(
												'Shortcode',
												'formwerk'
											) }
										>
											<Shortcode id={ form.id } />
										</td>
										<td
											data-colname={ __(
												'Fields to fill in',
												'formwerk'
											) }
										>
											{ form.fields }
										</td>
										<td
											className="formwerk-nowrap"
											data-colname={ __(
												'Last changed',
												'formwerk'
											) }
										>
											{ form.changed }
										</td>
										<td className="formwerk-nowrap formwerk-row-actions">
											<Button
												id={ `formwerk-edit-${ form.id }` }
												variant="secondary"
												size="compact"
												onClick={ () =>
													onOpen( form.id )
												}
												aria-label={ sprintf(
													/* translators: %s: form name. */
													__(
														'Edit “%s”',
														'formwerk'
													),
													form.title
												) }
											>
												{ __( 'Edit', 'formwerk' ) }
											</Button>{ ' ' }
											<Button
												variant="tertiary"
												size="compact"
												isDestructive
												onClick={ () =>
													setToDelete( form )
												}
												aria-label={ sprintf(
													/* translators: %s: form name. */
													__(
														'Delete “%s”',
														'formwerk'
													),
													form.title
												) }
											>
												{ __( 'Delete', 'formwerk' ) }
											</Button>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
					<p className="formwerk-hint">
						{ __(
							'To show a form, paste its shortcode into a page, or add the “Formwerk form” block.',
							'formwerk'
						) }
					</p>
				</>
			) }

			{ forms && forms.length > 0 && <Appearance /> }

			{ toDelete && (
				<Modal
					title={ sprintf(
						/* translators: %s: form name. */
						__( 'Delete the form “%s”?', 'formwerk' ),
						toDelete.title
					) }
					onRequestClose={ () => setToDelete( null ) }
					size="small"
				>
					<p>
						{ __(
							'Pages that show this form will show nothing in its place. Submissions already received are kept. The form itself cannot be restored.',
							'formwerk'
						) }
					</p>
					<div className="formwerk-modal-actions">
						<Button
							variant="tertiary"
							onClick={ () => setToDelete( null ) }
							__next40pxDefaultSize
						>
							{ __( 'Cancel', 'formwerk' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							isBusy={ isDeleting }
							disabled={ isDeleting }
							accessibleWhenDisabled
							onClick={ remove }
							__next40pxDefaultSize
						>
							{ __( 'Delete form', 'formwerk' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
