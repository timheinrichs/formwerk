import {
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	PlaceholderBar,
	controlProps,
	errorState,
	helpOrError,
	useTemplateTarget,
} from './template';

const SUBJECT_ID = 'formwerk-confirm-subject';
const BODY_ID = 'formwerk-confirm-body';

/**
 * Settings of the email that goes to the person who sent the form.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.form     Form being edited.
 * @param {Object}   props.errors   Errors by path.
 * @param {Function} props.onChange Called with the changed form.
 * @return {*} Element.
 */
export default function ConfirmationTab( { form, errors, onChange } ) {
	const confirm = form.confirm;
	const setKey = ( key, value ) =>
		onChange( { ...form, confirm: { ...confirm, [ key ]: value } } );
	const set = ( key ) => ( value ) => setKey( key, value );
	const { insert, focusSubject, focusBody } = useTemplateTarget( {
		subjectId: SUBJECT_ID,
		bodyId: BODY_ID,
		values: confirm,
		onChange: setKey,
	} );

	// New email fields have no name before saving; they are chosen by their ID until then.
	const emailFields = form.fields.filter(
		( field ) => field.type === 'email'
	);
	const options = emailFields.map( ( field ) => ( {
		value: field.name || field.id,
		label: field.label || __( '(no label)', 'formwerk' ),
	} ) );
	const chosen = options.some(
		( option ) => option.value === confirm.to_field
	)
		? confirm.to_field
		: '';
	const chosenField = emailFields.find(
		( field ) => ( field.name || field.id ) === chosen
	);

	return (
		<div className="formwerk-fields formwerk-mail">
			<p className="formwerk-intro">
				{ __(
					'After sending the form, the person who filled it in gets an email from you, for example to confirm a registration. Replies to it reach the addresses under “Notification”.',
					'formwerk'
				) }
			</p>
			<ToggleControl
				__nextHasNoMarginBottom
				id="formwerk-confirm-enabled"
				label={ __( 'Send a confirmation', 'formwerk' ) }
				checked={ confirm.enabled }
				onChange={ ( enabled ) =>
					onChange( {
						...form,
						confirm: {
							...confirm,
							enabled,
							// Most forms have one email field: take it, so there is nothing more to choose.
							to_field:
								enabled && ! chosen && options.length
									? options[ 0 ].value
									: confirm.to_field,
						},
					} )
				}
			/>
			{ confirm.enabled && (
				<>
					{ emailFields.length === 0 ? (
						<p
							id="formwerk-confirm-to_field"
							tabIndex={ -1 }
							className={
								errors[ 'confirm.to_field' ]
									? 'formwerk-field-error'
									: 'formwerk-hint'
							}
						>
							{ errors[ 'confirm.to_field' ] ||
								__(
									'The form has no email field yet. Add one under “Fields”, so the confirmation knows where to go.',
									'formwerk'
								) }
						</p>
					) : (
						<SelectControl
							{ ...controlProps }
							{ ...errorState( errors[ 'confirm.to_field' ] ) }
							id="formwerk-confirm-to_field"
							label={ __(
								'Email address from field',
								'formwerk'
							) }
							value={ chosen }
							onChange={ set( 'to_field' ) }
							options={ [
								{
									value: '',
									label: __(
										'Choose an email field',
										'formwerk'
									),
									disabled: true,
								},
								...options,
							] }
							help={ helpOrError(
								errors[ 'confirm.to_field' ],
								chosenField && ! chosenField.required
									? __(
											'This field is optional. When it is left empty, no confirmation is sent.',
											'formwerk'
									  )
									: undefined
							) }
						/>
					) }
					<TextControl
						{ ...controlProps }
						{ ...errorState( errors[ 'confirm.subject' ] ) }
						id={ SUBJECT_ID }
						label={ __( 'Subject', 'formwerk' ) }
						value={ confirm.subject }
						onChange={ set( 'subject' ) }
						onFocus={ focusSubject }
						help={ helpOrError( errors[ 'confirm.subject' ] ) }
					/>
					<TextareaControl
						{ ...controlProps }
						{ ...errorState( errors[ 'confirm.body' ] ) }
						id={ BODY_ID }
						label={ __( 'Email text', 'formwerk' ) }
						value={ confirm.body }
						onChange={ set( 'body' ) }
						onFocus={ focusBody }
						rows={ 10 }
						help={ helpOrError(
							errors[ 'confirm.body' ],
							__(
								'“All fields” adds a copy of everything the person entered.',
								'formwerk'
							)
						) }
					/>
					<PlaceholderBar
						fields={ form.fields }
						onInsert={ insert }
					/>
				</>
			) }
		</div>
	);
}
