import { TextControl, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	PlaceholderBar,
	controlProps,
	errorState,
	helpOrError,
	useTemplateTarget,
} from './template';

const SUBJECT_ID = 'formwerk-mail-subject';
const BODY_ID = 'formwerk-mail-body';

export default function MailTab( { form, errors, onChange } ) {
	const mail = form.mail;
	const setKey = ( key, value ) =>
		onChange( { ...form, mail: { ...mail, [ key ]: value } } );
	const set = ( key ) => ( value ) => setKey( key, value );
	const { insert, focusSubject, focusBody } = useTemplateTarget( {
		subjectId: SUBJECT_ID,
		bodyId: BODY_ID,
		values: mail,
		onChange: setKey,
	} );

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
				{ ...errorState( errors[ 'mail.to' ] ) }
				id="formwerk-mail-to"
				label={ __( 'Send to', 'formwerk' ) }
				value={ mail.to }
				onChange={ set( 'to' ) }
				type="text"
				inputMode="email"
				autoComplete="off"
				spellCheck={ false }
				help={ helpOrError(
					errors[ 'mail.to' ],
					__(
						'One or more email addresses, separated by commas.',
						'formwerk'
					)
				) }
			/>
			<TextControl
				{ ...controlProps }
				{ ...errorState( errors[ 'mail.subject' ] ) }
				id={ SUBJECT_ID }
				label={ __( 'Subject', 'formwerk' ) }
				value={ mail.subject }
				onChange={ set( 'subject' ) }
				onFocus={ focusSubject }
				help={ helpOrError( errors[ 'mail.subject' ] ) }
			/>
			<TextareaControl
				{ ...controlProps }
				{ ...errorState( errors[ 'mail.body' ] ) }
				id={ BODY_ID }
				label={ __( 'Email text', 'formwerk' ) }
				value={ mail.body }
				onChange={ set( 'body' ) }
				onFocus={ focusBody }
				rows={ 10 }
				help={ helpOrError(
					errors[ 'mail.body' ],
					__(
						'{all_fields} lists every field with its label. Keep it in the text and no answer can be missing from the email.',
						'formwerk'
					)
				) }
			/>
			<PlaceholderBar fields={ form.fields } onInsert={ insert } />
		</div>
	);
}
