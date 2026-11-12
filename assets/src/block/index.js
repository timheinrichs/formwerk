/**
 * Block "Formwerk form": choose a form, see it in the editor, render it on the server.
 */
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import {
	Disabled,
	PanelBody,
	Placeholder,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

function Edit( { attributes, setAttributes } ) {
	const [ forms, setForms ] = useState( null );
	const [ error, setError ] = useState( '' );
	const blockProps = useBlockProps();

	useEffect( () => {
		apiFetch( { path: '/formwerk/v1/forms' } )
			.then( ( response ) => setForms( response.forms ) )
			.catch( ( failure ) =>
				setError(
					failure?.code === 'rest_forbidden'
						? __(
								'You are not allowed to choose a form. Ask an administrator to choose it for you.',
								'formwerk'
						  )
						: __(
								'The forms could not be loaded. Reload the page to try again.',
								'formwerk'
						  )
				)
			);
	}, [] );

	const select = (
		<SelectControl
			label={ __( 'Form', 'formwerk' ) }
			value={ String( attributes.formId ) }
			onChange={ ( value ) =>
				setAttributes( { formId: Number( value ) } )
			}
			options={ [
				{ value: '0', label: __( 'Choose a form', 'formwerk' ) },
				...( forms || [] ).map( ( form ) => ( {
					value: String( form.id ),
					label: form.title,
				} ) ),
			] }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);

	const preview = (
		<Disabled>
			<ServerSideRender block="formwerk/form" attributes={ attributes } />
		</Disabled>
	);
	const isDeleted =
		forms !== null &&
		attributes.formId > 0 &&
		! forms.some( ( form ) => form.id === attributes.formId );

	let content;
	if ( error ) {
		// Without the right to list forms, a chosen form is still shown; only choosing is not possible.
		content = attributes.formId ? (
			preview
		) : (
			<Placeholder
				icon="feedback"
				label={ __( 'Formwerk form', 'formwerk' ) }
			>
				<p>{ error }</p>
			</Placeholder>
		);
	} else if ( ! forms ) {
		content = (
			<Placeholder
				icon="feedback"
				label={ __( 'Formwerk form', 'formwerk' ) }
			>
				<Spinner />
			</Placeholder>
		);
	} else if ( ! attributes.formId || isDeleted ) {
		let instructions = __( 'Choose the form to show here.', 'formwerk' );
		if ( isDeleted ) {
			instructions = __(
				'The form chosen here was deleted. Choose another form, or remove this block.',
				'formwerk'
			);
		} else if ( ! forms.length ) {
			instructions = __(
				'There are no forms yet. Create one under Formwerk → Forms, then come back.',
				'formwerk'
			);
		}
		content = (
			<Placeholder
				icon="feedback"
				label={ __( 'Formwerk form', 'formwerk' ) }
				instructions={ instructions }
			>
				{ forms.length > 0 && select }
			</Placeholder>
		);
	} else {
		content = preview;
	}

	return (
		<div { ...blockProps }>
			{ forms && forms.length > 0 && (
				<InspectorControls>
					<PanelBody title={ __( 'Form', 'formwerk' ) }>
						{ select }
					</PanelBody>
				</InspectorControls>
			) }
			{ content }
		</div>
	);
}

registerBlockType( 'formwerk/form', {
	apiVersion: 3,
	title: __( 'Formwerk form', 'formwerk' ),
	description: __( 'Shows a form you built with Formwerk.', 'formwerk' ),
	icon: 'feedback',
	category: 'widgets',
	keywords: [ __( 'contact', 'formwerk' ), __( 'registration', 'formwerk' ) ],
	attributes: { formId: { type: 'number', default: 0 } },
	supports: { html: false, align: [ 'wide', 'full' ] },
	edit: Edit,
	save: () => null,
} );
