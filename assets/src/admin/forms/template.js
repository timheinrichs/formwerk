import { Button } from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { hasValue } from './types';

export const controlProps = {
	__nextHasNoMarginBottom: true,
	__next40pxDefaultSize: true,
};

/**
 * Help text of a control, or its error when there is one.
 *
 * @param {string|undefined} error Error message.
 * @param {string}           text  Normal help text.
 * @return {*} Help content.
 */
export function helpOrError( error, text ) {
	return error ? (
		<span className="formwerk-field-error">
			<span>
				{ __( 'Error:', 'formwerk' ) } { error }
			</span>
		</span>
	) : (
		text
	);
}

/**
 * Props that mark a control as invalid.
 *
 * @param {string|undefined} error Error message.
 * @return {Object} Props.
 */
export function errorState( error ) {
	return {
		className: error ? 'formwerk-has-error' : undefined,
		'aria-invalid': error ? 'true' : undefined,
	};
}

/**
 * Inserting placeholders into a subject and a text: the one used last receives them.
 *
 * @param {Object}   options           Options.
 * @param {string}   options.subjectId ID of the subject input.
 * @param {string}   options.bodyId    ID of the text area.
 * @param {Object}   options.values    Current { subject, body }.
 * @param {Function} options.onChange  Called with ( key, value ).
 * @return {Object} { insert, focusSubject, focusBody }.
 */
export function useTemplateTarget( { subjectId, bodyId, values, onChange } ) {
	const target = useRef( bodyId );

	const insert = ( placeholder ) => {
		const element = document.getElementById( target.current );
		const key = target.current === subjectId ? 'subject' : 'body';
		const value = values[ key ];
		const start = element?.selectionStart ?? value.length;
		const end = element?.selectionEnd ?? value.length;

		onChange(
			key,
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

	return {
		insert,
		focusSubject: () => {
			target.current = subjectId;
		},
		focusBody: () => {
			target.current = bodyId;
		},
	};
}

/**
 * Buttons that insert a placeholder, one per field plus the general ones.
 *
 * @param {Object}   props          Props.
 * @param {Array}    props.fields   Fields of the form.
 * @param {Function} props.onInsert Called with the placeholder.
 * @return {*} Element.
 */
export function PlaceholderBar( { fields, onInsert } ) {
	const named = fields.filter(
		( field ) => hasValue( field.type ) && field.name
	);
	const unnamed = fields.some(
		( field ) => hasValue( field.type ) && ! field.name
	);
	const general = [
		[ '{form}', __( 'Form name', 'formwerk' ) ],
		[ '{page}', __( 'Page address', 'formwerk' ) ],
		[ '{date}', __( 'Date and time', 'formwerk' ) ],
		[ '{site}', __( 'Website name', 'formwerk' ) ],
	];

	return (
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
					onClick={ () => onInsert( '{all_fields}' ) }
				>
					{ __( 'All fields', 'formwerk' ) }
				</Button>
				{ named.map( ( field ) => (
					<Button
						key={ field.id }
						variant="secondary"
						size="compact"
						onClick={ () => onInsert( `{field:${ field.name }}` ) }
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
				{ general.map( ( [ placeholder, label ] ) => (
					<Button
						key={ placeholder }
						variant="secondary"
						size="compact"
						onClick={ () => onInsert( placeholder ) }
					>
						{ label }
					</Button>
				) ) }
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
	);
}
