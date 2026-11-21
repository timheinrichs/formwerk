import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { isHalf } from './layout';
import { hasOptions } from './types';

function Field( { field, half } ) {
	const classes = `formwerk-field formwerk-field--${
		field.type
	} formwerk-field--${ half ? 'half' : 'full' }`;
	const id = `formwerk-preview-${ field.id }`;
	const required = field.required && (
		<span className="formwerk-field__required">
			{ ' ' }
			{ __( '(required)', 'formwerk' ) }
		</span>
	);
	const help = field.help && (
		<p className="formwerk-field__help">{ field.help }</p>
	);
	const options = field.options.filter( ( option ) => option.trim() );

	if ( field.type === 'heading' ) {
		return (
			<div className={ classes }>
				{ field.label && (
					<h3 className="formwerk-field__heading">{ field.label }</h3>
				) }
				{ field.content && (
					<p className="formwerk-field__text">{ field.content }</p>
				) }
			</div>
		);
	}

	if ( field.type === 'consent' ) {
		return (
			<div className={ classes }>
				<div className="formwerk-field__option">
					<input type="checkbox" id={ id } />
					<label htmlFor={ id }>
						{ field.label }
						{ required }
					</label>
				</div>
				{ help }
			</div>
		);
	}

	if ( field.type === 'radio' || field.type === 'checkboxes' ) {
		return (
			<fieldset className={ classes }>
				<legend className="formwerk-field__label">
					{ field.label }
					{ required }
				</legend>
				{ help }
				{ options.map( ( option, index ) => (
					<div className="formwerk-field__option" key={ index }>
						<input
							type={
								field.type === 'radio' ? 'radio' : 'checkbox'
							}
							id={ `${ id }-${ index }` }
							name={ id }
						/>
						<label htmlFor={ `${ id }-${ index }` }>
							{ option }
						</label>
					</div>
				) ) }
			</fieldset>
		);
	}

	let control;
	if ( field.type === 'textarea' ) {
		control = (
			<textarea id={ id } rows={ 4 } placeholder={ field.placeholder } />
		);
	} else if ( hasOptions( field.type ) ) {
		control = (
			<select id={ id }>
				<option value="">{ __( 'Please choose', 'formwerk' ) }</option>
				{ options.map( ( option, index ) => (
					<option key={ index }>{ option }</option>
				) ) }
			</select>
		);
	} else {
		control = (
			<input
				id={ id }
				type={
					[ 'email', 'tel', 'date' ].includes( field.type )
						? field.type
						: 'text'
				}
				placeholder={ field.placeholder }
			/>
		);
	}

	return (
		<div className={ classes }>
			<label className="formwerk-field__label" htmlFor={ id }>
				{ field.label }
				{ required }
			</label>
			{ help }
			{ control }
		</div>
	);
}

// Shows the fields the way visitors get them. Uses the same markup and layout
// rules as the real form; fonts and colours on the website come from its theme.
// A form in steps shows one step at a time with working Back and Next.
export default function Preview( {
	fields,
	steps = null,
	messages,
	submitLabel,
} ) {
	const [ current, setCurrent ] = useState( 0 );
	if ( fields.length === 0 ) {
		return null;
	}
	const inSteps = Array.isArray( steps ) && steps.length > 1;
	const index = inSteps ? Math.min( current, steps.length - 1 ) : 0;
	const step = inSteps ? steps[ index ] : null;
	const shown = inSteps
		? fields.filter( ( field ) => field.step === step.id )
		: fields;
	const last = ! inSteps || index === steps.length - 1;

	return (
		<section
			className="formwerk-preview"
			aria-label={ __( 'Preview', 'formwerk' ) }
		>
			<h3 className="formwerk-preview__title">
				{ __( 'Preview', 'formwerk' ) }
			</h3>
			<p className="formwerk-hint">
				{ __(
					'Layout and wording as visitors see them. Fonts and colours follow your website. You can type here to try it; nothing is sent.',
					'formwerk'
				) }
			</p>
			<div className="formwerk-form formwerk-preview__form">
				{ inSteps && (
					<p className="formwerk-steps__label" aria-live="polite">
						{ sprintf(
							/* translators: 1: number of the current step, 2: number of steps, 3: title of the step. */
							__( 'Step %1$s of %2$s: %3$s', 'formwerk' ),
							index + 1,
							steps.length,
							step.title ||
								sprintf(
									/* translators: %d: number of the step. */
									__( 'Step %d', 'formwerk' ),
									index + 1
								)
						) }
					</p>
				) }
				<div className="formwerk-form__fields">
					{ shown.map( ( field ) => (
						<Field
							key={ field.id }
							field={ field }
							half={ isHalf( fields, fields.indexOf( field ) ) }
						/>
					) ) }
				</div>
				<div className="formwerk-form__actions">
					{ inSteps && index > 0 && (
						<button
							type="button"
							className="button"
							onClick={ () => setCurrent( index - 1 ) }
						>
							{ messages?.back || __( 'Back', 'formwerk' ) }
						</button>
					) }
					{ last ? (
						<button
							type="button"
							className="button formwerk-form__submit"
						>
							{ submitLabel }
						</button>
					) : (
						<button
							type="button"
							className="button button-primary"
							onClick={ () => setCurrent( index + 1 ) }
						>
							{ messages?.next || __( 'Next', 'formwerk' ) }
						</button>
					) }
				</div>
			</div>
		</section>
	);
}
