import { Button, TextControl, TextareaControl } from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { hasValue } from './types';

const controlProps = {
	__nextHasNoMarginBottom: true,
	__next40pxDefaultSize: true,
};

const SUBJECT_ID = 'formwerk-mail-subject';
const BODY_ID = 'formwerk-mail-body';

export default function MailTab( { form, errors, onChange } ) {
	// Placeholders go into the field that was used last; the text by default.
	const target = useRef( BODY_ID );
	const mail = form.mail;
	const set = ( key ) => ( value ) =>
		onChange( { ...form, mail: { ...mail, [ key ]: value } } );

	const insert = ( placeholder ) => {
		const element = document.getElementById( target.current );
		const key = target.current === SUBJECT_ID ? 'subject' : 'body';
		const value = mail[ key ];
		const start = element?.selectionStart ?? value.length;
		const end = element?.selectionEnd ?? value.length;

		set( key )(
			value.slice( 0, start ) + placeholder + value.slice( end )
		);
		window.setTimeout( () => {
			element?.focus();
			element?.setSelectionRange(
				start + placeholder.length,
				start + placeholder.length
			);
		}, 0 );
	};

	const help = ( key, text ) =>
		errors[ key ] ? (
			<span className="formwerk-field-error">
				<span>
					{ __( 'Error:', 'formwerk' ) } { errors[ key ] }
				</span>
			</span>
		) : (
			text
		);
	const state = ( key ) => ( {
		className: errors[ key ] ? 'formwerk-has-error' : undefined,
		'aria-invalid': errors[ key ] ? 'true' : undefined,
	} );

	const named = form.fields.filter(
		( field ) => hasValue( field.type ) && field.name
	);
	const unnamed = form.fields.some(
		( field ) => hasValue( field.type ) && ! field.name
	);

	return (
		<div className="formwerk-fields formwerk-mail">
			<p className="formwerk-intro">
				{ __(
					'Every submission is saved under “Submissions” and also sent to you by email. If the visitor entered an email address, a reply to that email goes straight to them.',
					'formwerk'
				) }
			</p>
			<TextControl
				{ ...controlProps }
				{ ...state( 'mail.to' ) }
				id="formwerk-mail-to"
				label={ __( 'Send to', 'formwerk' ) }
				value={ mail.to }
				onChange={ set( 'to' ) }
				type="text"
				inputMode="email"
				autoComplete="off"
				spellCheck={ false }
				help={ help(
					'mail.to',
					__(
						'One or more email addresses, separated by commas.',
						'formwerk'
					)
				) }
			/>
			<TextControl
				{ ...controlProps }
				{ ...state( 'mail.subject' ) }
				id={ SUBJECT_ID }
				label={ __( 'Subject', 'formwerk' ) }
				value={ mail.subject }
				onChange={ set( 'subject' ) }
				onFocus={ () => {
					target.current = SUBJECT_ID;
				} }
				help={ help( 'mail.subject' ) }
			/>
			<TextareaControl
				{ ...controlProps }
				{ ...state( 'mail.body' ) }
				id={ BODY_ID }
				label={ __( 'Email text', 'formwerk' ) }
				value={ mail.body }
				onChange={ set( 'body' ) }
				onFocus={ () => {
					target.current = BODY_ID;
				} }
				rows={ 10 }
				help={ help(
					'mail.body',
					__(
						'{all_fields} lists every field with its label. Keep it in the text and no answer can be missing from the email.',
						'formwerk'
					)
				) }
			/>
			<div
				className="formwerk-placeholders"
				role="group"
				aria-label={ __( 'Insert placeholder', 'formwerk' ) }
			>
				<p className="formwerk-placeholders__title">
					{ __(
						'Insert a placeholder into subject or text',
						'formwerk'
					) }
				</p>
				<div className="formwerk-placeholders__list">
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => insert( '{all_fields}' ) }
					>
						{ __( 'All fields', 'formwerk' ) }
					</Button>
					{ named.map( ( field ) => (
						<Button
							key={ field.id }
							variant="secondary"
							size="compact"
							onClick={ () =>
								insert( `{field:${ field.name }}` )
							}
							aria-label={ sprintf(
								/* translators: %s: field label. */
								__( 'Insert the answer to “%s”', 'formwerk' ),
								field.label
							) }
						>
							{ field.label.length > 30
								? field.label.slice( 0, 29 ) + '…'
								: field.label }
						</Button>
					) ) }
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => insert( '{form}' ) }
					>
						{ __( 'Form name', 'formwerk' ) }
					</Button>
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => insert( '{page}' ) }
					>
						{ __( 'Page address', 'formwerk' ) }
					</Button>
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => insert( '{date}' ) }
					>
						{ __( 'Date and time', 'formwerk' ) }
					</Button>
					<Button
						variant="secondary"
						size="compact"
						onClick={ () => insert( '{site}' ) }
					>
						{ __( 'Website name', 'formwerk' ) }
					</Button>
				</div>
				{ unnamed && (
					<p className="formwerk-hint">
						{ __(
							'New fields get their own placeholder once the form is saved. “All fields” already includes them.',
							'formwerk'
						) }
					</p>
				) }
			</div>
		</div>
	);
}
