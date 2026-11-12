import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Modal,
	Notice,
} from '@wordpress/components';
import { dateI18n, getSettings } from '@wordpress/date';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { describeError } from './errors';

const INITIAL_ROWS = 10;
const LONG_TEXT = 160;

function formatTime( timestamp ) {
	const { formats } = getSettings();
	return dateI18n( formats.datetime, timestamp * 1000 );
}

function Recipients( { to } ) {
	if ( to.length === 0 ) {
		return <span aria-hidden="true">–</span>;
	}
	if ( to.length === 1 ) {
		return to[ 0 ];
	}
	return (
		<span title={ to.join( ', ' ) }>
			{ sprintf(
				/* translators: 1: first email address, 2: number of further recipients. */
				__( '%1$s and %2$d more', 'formwerk' ),
				to[ 0 ],
				to.length - 1
			) }
		</span>
	);
}

function Details( { text } ) {
	const [ isOpen, setIsOpen ] = useState( false );

	if ( text.length <= LONG_TEXT ) {
		return text;
	}
	return (
		<>
			{ isOpen ? text : text.slice( 0, LONG_TEXT ).trimEnd() + '…' }{ ' ' }
			<Button
				variant="link"
				onClick={ () => setIsOpen( ! isOpen ) }
				aria-expanded={ isOpen }
			>
				{ isOpen
					? __( 'Show less', 'formwerk' )
					: __( 'Show more', 'formwerk' ) }
			</Button>
		</>
	);
}

export default function MailLog( { entries, loadError, onRetry, onCleared } ) {
	const [ isClearing, setIsClearing ] = useState( false );
	const [ isConfirming, setIsConfirming ] = useState( false );
	const [ clearError, setClearError ] = useState( '' );
	const [ showAll, setShowAll ] = useState( false );

	const clear = () => {
		setIsConfirming( false );
		setIsClearing( true );
		setClearError( '' );
		apiFetch( { path: '/formwerk/v1/smtp/log', method: 'DELETE' } )
			.then( () => {
				setShowAll( false );
				onCleared();
			} )
			.catch( ( error ) =>
				setClearError(
					describeError(
						error,
						__( 'The log could not be cleared.', 'formwerk' )
					)
				)
			)
			.finally( () => setIsClearing( false ) );
	};

	const visible = showAll ? entries : entries.slice( 0, INITIAL_ROWS );

	let body;
	if ( loadError ) {
		body = (
			<Notice
				status="error"
				isDismissible={ false }
				className="formwerk-load-error"
			>
				<p>{ loadError }</p>
				<Button
					variant="secondary"
					onClick={ onRetry }
					__next40pxDefaultSize
				>
					{ __( 'Try again', 'formwerk' ) }
				</Button>
			</Notice>
		);
	} else if ( entries.length === 0 ) {
		body = (
			<p className="formwerk-intro">
				{ __(
					'No failed emails so far. If an email from this website cannot be sent, it appears here with the reason. Test emails are listed too. Entries are removed after 30 days.',
					'formwerk'
				) }
			</p>
		);
	} else {
		body = (
			<>
				<div
					className="formwerk-table-wrap"
					// eslint-disable-next-line jsx-a11y/no-noninteractive-tabindex -- Scrollable region must be reachable by keyboard.
					tabIndex={ 0 }
					role="region"
					aria-label={ __( 'Email log', 'formwerk' ) }
				>
					<table className="widefat striped formwerk-log">
						<caption className="screen-reader-text">
							{ __(
								'Failed emails and test results, newest first',
								'formwerk'
							) }
						</caption>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Time', 'formwerk' ) }
								</th>
								<th scope="col">
									{ __( 'Result', 'formwerk' ) }
								</th>
								<th scope="col">
									{ __( 'Recipient', 'formwerk' ) }
								</th>
								<th scope="col">
									{ __( 'Subject', 'formwerk' ) }
								</th>
								<th scope="col">
									{ __( 'Details', 'formwerk' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ visible.map( ( entry, index ) => (
								<tr key={ `${ entry.time }-${ index }` }>
									<td className="formwerk-nowrap">
										{ formatTime( entry.time ) }
									</td>
									<td className="formwerk-nowrap">
										<span
											className={ `formwerk-result is-${ entry.status }` }
										>
											{ entry.status === 'sent'
												? __( 'Sent', 'formwerk' )
												: __( 'Failed', 'formwerk' ) }
										</span>
									</td>
									<td>
										<Recipients to={ entry.to } />
									</td>
									<td>{ entry.subject }</td>
									<td>
										<Details text={ entry.message } />
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
				{ entries.length > INITIAL_ROWS && ! showAll && (
					<Button
						variant="secondary"
						onClick={ () => setShowAll( true ) }
						className="formwerk-show-all"
						__next40pxDefaultSize
					>
						{ sprintf(
							/* translators: %d: number of log entries. */
							__( 'Show all %d entries', 'formwerk' ),
							entries.length
						) }
					</Button>
				) }
			</>
		);
	}

	return (
		<Card className="formwerk-card">
			<CardHeader>
				<h2>{ __( 'Email log', 'formwerk' ) }</h2>
				{ entries.length > 0 && ! loadError && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () => setIsConfirming( true ) }
						isBusy={ isClearing }
						disabled={ isClearing }
						accessibleWhenDisabled
						__next40pxDefaultSize
					>
						{ __( 'Clear log', 'formwerk' ) }
					</Button>
				) }
			</CardHeader>
			<CardBody>
				{ clearError && (
					<Notice
						status="error"
						onRemove={ () => setClearError( '' ) }
						className="formwerk-inline-notice"
					>
						{ clearError }
					</Notice>
				) }
				{ body }
			</CardBody>
			{ isConfirming && (
				<Modal
					title={ __( 'Delete all log entries?', 'formwerk' ) }
					onRequestClose={ () => setIsConfirming( false ) }
					size="small"
				>
					<p>{ __( 'This cannot be undone.', 'formwerk' ) }</p>
					<div className="formwerk-modal-actions">
						<Button
							variant="tertiary"
							onClick={ () => setIsConfirming( false ) }
							__next40pxDefaultSize
						>
							{ __( 'Cancel', 'formwerk' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ clear }
							__next40pxDefaultSize
						>
							{ __( 'Delete entries', 'formwerk' ) }
						</Button>
					</div>
				</Modal>
			) }
		</Card>
	);
}
