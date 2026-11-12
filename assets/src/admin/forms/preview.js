import { __ } from '@wordpress/i18n';

import { hasOptions } from './types';

function Field( { field } ) {
	const classes = `formwerk-field formwerk-field--${ field.type } formwerk-field--${ field.width }`;
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
export default function Preview( { fields, submitLabel } ) {
	if ( fields.length === 0 ) {
		return null;
	}
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
				<div className="formwerk-form__fields">
					{ fields.map( ( field ) => (
						<Field key={ field.id } field={ field } />
					) ) }
				</div>
				<div className="formwerk-form__actions">
					<button
						type="button"
						className="button formwerk-form__submit"
					>
						{ submitLabel }
					</button>
				</div>
			</div>
		</section>
	);
}
