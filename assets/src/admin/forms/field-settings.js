import { speak } from '@wordpress/a11y';
import {
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { WIDTHS } from './layout';
import { AUTOCOMPLETE_OPTIONS, hasOptions, hasValue, typeLabel } from './types';

const controlProps = {
	__nextHasNoMarginBottom: true,
	__next40pxDefaultSize: true,
};

const WIDTH_LABELS = () => ( {
	full: __( '100 %', 'formwerk' ),
	half: __( '50 %', 'formwerk' ),
	third: __( '33 %', 'formwerk' ),
	quarter: __( '25 %', 'formwerk' ),
} );

// A row of four choices that look like a toggle group, built from radio buttons:
// arrow keys move between them, and screen readers announce "1 of 4".
function WidthChoice( { id, value, onChange } ) {
	const labels = WIDTH_LABELS();
	return (
		<fieldset
			className="formwerk-width"
			aria-describedby={ `${ id }-help` }
		>
			<legend className="formwerk-width__legend">
				{ __( 'Width', 'formwerk' ) }
			</legend>
			<div className="formwerk-width__options">
				{ WIDTHS.map( ( width ) => (
					<label
						key={ width }
						htmlFor={ `${ id }-${ width }` }
						className={ `formwerk-width__option${
							value === width ? ' is-checked' : ''
						}` }
					>
						<input
							type="radio"
							name={ id }
							id={ `${ id }-${ width }` }
							value={ width }
							checked={ value === width }
							onChange={ () => onChange( width ) }
						/>
						<span>{ labels[ width ] }</span>
					</label>
				) ) }
			</div>
			<p className="formwerk-width__help" id={ `${ id }-help` }>
				{ __(
					'Fields follow each other in rows; a field that no longer fits starts the next row. On small screens every field takes the full width.',
					'formwerk'
				) }
			</p>
		</fieldset>
	);
}

export default function FieldSettings( {
	field,
	error,
	steps = null,
	onChange,
} ) {
	if ( ! field ) {
		return (
			<p className="formwerk-intro">
				{ __(
					'Select a field in the list to change its settings.',
					'formwerk'
				) }
			</p>
		);
	}

	const set = ( key ) => ( value ) =>
		onChange( { ...field, [ key ]: value } );
	const isChoice = hasOptions( field.type );
	const isText = [ 'text', 'email', 'tel', 'number', 'textarea' ].includes(
		field.type
	);
	const labelError = error?.code === 'label_required';
	const optionsError = error?.code === 'options_required';

	const errorHelp = ( message ) => (
		<span className="formwerk-field-error">
			<span>
				{ __( 'Error:', 'formwerk' ) } { message }
			</span>
		</span>
	);

	let requiredHelp = __(
		'The form can only be sent when this field is filled in.',
		'formwerk'
	);
	if ( field.type === 'consent' ) {
		requiredHelp = __(
			'The form can only be sent when the box is ticked.',
			'formwerk'
		);
	} else if ( isChoice ) {
		requiredHelp = __(
			'The form can only be sent when an option is chosen.',
			'formwerk'
		);
	}

	let labelHelp;
	if ( field.type === 'consent' ) {
		labelHelp = __(
			'The sentence visitors agree to, shown next to the checkbox.',
			'formwerk'
		);
	} else if ( field.type === 'heading' ) {
		labelHelp = __(
			'Shown as a heading between the fields. May stay empty if you only need the text below.',
			'formwerk'
		);
	}

	return (
		<div className="formwerk-fields formwerk-field-settings">
			<p className="formwerk-field-settings__type">
				{ typeLabel( field.type ) }
			</p>
			<TextControl
				{ ...controlProps }
				id={ `formwerk-field-${ field.id }-label` }
				label={
					field.type === 'heading'
						? __( 'Heading', 'formwerk' )
						: __( 'Label', 'formwerk' )
				}
				value={ field.label }
				onChange={ set( 'label' ) }
				className={ labelError ? 'formwerk-has-error' : undefined }
				aria-invalid={ labelError ? 'true' : undefined }
				help={ labelError ? errorHelp( error.message ) : labelHelp }
			/>
			{ field.type === 'heading' && (
				<TextareaControl
					{ ...controlProps }
					label={ __( 'Text', 'formwerk' ) }
					value={ field.content }
					onChange={ set( 'content' ) }
					help={ __(
						'Optional note for visitors, for example how to fill in the following fields.',
						'formwerk'
					) }
					rows={ 4 }
				/>
			) }
			{ isChoice && (
				<TextareaControl
					{ ...controlProps }
					id={ `formwerk-field-${ field.id }-options` }
					label={ __( 'Options', 'formwerk' ) }
					value={ field.options.join( '\n' ) }
					onChange={ ( value ) =>
						onChange( { ...field, options: value.split( '\n' ) } )
					}
					className={
						optionsError ? 'formwerk-has-error' : undefined
					}
					aria-invalid={ optionsError ? 'true' : undefined }
					help={
						optionsError
							? errorHelp( error.message )
							: __( 'One option per line.', 'formwerk' )
					}
					rows={ 5 }
				/>
			) }
			{ hasValue( field.type ) && (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Required', 'formwerk' ) }
					checked={ field.required }
					onChange={ set( 'required' ) }
					help={ requiredHelp }
				/>
			) }
			{ hasValue( field.type ) && (
				<TextControl
					{ ...controlProps }
					label={ __( 'Help text', 'formwerk' ) }
					value={ field.help }
					onChange={ set( 'help' ) }
					help={ __(
						'Optional. Shown between label and field, for example what the answer is used for.',
						'formwerk'
					) }
				/>
			) }
			{ isText && (
				<TextControl
					{ ...controlProps }
					label={ __( 'Example in the empty field', 'formwerk' ) }
					value={ field.placeholder }
					onChange={ set( 'placeholder' ) }
					help={ __(
						'Optional. Disappears when someone starts typing, so do not put instructions here.',
						'formwerk'
					) }
				/>
			) }
			{ steps && steps.length > 1 && (
				<SelectControl
					{ ...controlProps }
					label={ __( 'Step', 'formwerk' ) }
					value={ field.step }
					onChange={ ( step ) => {
						onChange( { ...field, step } );
						speak(
							sprintf(
								/* translators: 1: field label, 2: step title. */
								__(
									'“%1$s” moved to step “%2$s”.',
									'formwerk'
								),
								field.label,
								steps.find( ( item ) => item.id === step )
									?.title ||
									sprintf(
										/* translators: %d: number of the step. */
										__( 'Step %d', 'formwerk' ),
										steps.findIndex(
											( item ) => item.id === step
										) + 1
									)
							)
						);
					} }
					options={ steps.map( ( step, index ) => ( {
						value: step.id,
						label: step.title
							? sprintf(
									/* translators: 1: number of the step, 2: step title. */
									__( 'Step %1$d: %2$s', 'formwerk' ),
									index + 1,
									step.title
							  )
							: sprintf(
									/* translators: %d: number of the step. */
									__( 'Step %d', 'formwerk' ),
									index + 1
							  ),
					} ) ) }
					help={ __(
						'The field moves to the end of that step. You can also drag it there.',
						'formwerk'
					) }
				/>
			) }
			<WidthChoice
				id={ `formwerk-field-${ field.id }-width` }
				value={ field.width || 'full' }
				onChange={ ( width ) => {
					onChange( { ...field, width } );
					speak(
						sprintf(
							/* translators: 1: field label, 2: width, e.g. "50 %". */
							__( '“%1$s” now has a width of %2$s.', 'formwerk' ),
							field.label,
							WIDTH_LABELS()[ width ]
						)
					);
				} }
			/>
			{ [ 'text', 'email', 'tel' ].includes( field.type ) && (
				<SelectControl
					{ ...controlProps }
					label={ __( 'Browser suggestion', 'formwerk' ) }
					value={ field.autocomplete }
					onChange={ set( 'autocomplete' ) }
					options={ AUTOCOMPLETE_OPTIONS }
					help={ __(
						'Lets browsers fill in the field with saved details of the visitor.',
						'formwerk'
					) }
				/>
			) }
			{ hasValue( field.type ) && field.name && (
				<p className="formwerk-hint">
					{ __(
						'To put this answer into the email text, use:',
						'formwerk'
					) }{ ' ' }
					<code>{ `{field:${ field.name }}` }</code>
				</p>
			) }
		</div>
	);
}
