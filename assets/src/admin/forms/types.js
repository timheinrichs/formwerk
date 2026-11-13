import { __ } from '@wordpress/i18n';

// Order of the "Add field" menu: the most common fields first.
export const FIELD_TYPES = [
	{ type: 'text', label: __( 'Text (one line)', 'formwerk' ) },
	{ type: 'email', label: __( 'Email address', 'formwerk' ) },
	{ type: 'tel', label: __( 'Phone number', 'formwerk' ) },
	{ type: 'textarea', label: __( 'Text (several lines)', 'formwerk' ) },
	{ type: 'select', label: __( 'Dropdown (choose one)', 'formwerk' ) },
	{ type: 'radio', label: __( 'Options (choose one)', 'formwerk' ) },
	{
		type: 'checkboxes',
		label: __( 'Checkboxes (choose several)', 'formwerk' ),
	},
	{ type: 'consent', label: __( 'Consent checkbox', 'formwerk' ) },
	{ type: 'number', label: __( 'Number', 'formwerk' ) },
	{ type: 'date', label: __( 'Date', 'formwerk' ) },
	{ type: 'heading', label: __( 'Heading or note', 'formwerk' ) },
];

const DEFAULT_LABELS = {
	text: __( 'Name', 'formwerk' ),
	email: __( 'Email address', 'formwerk' ),
	tel: __( 'Phone number', 'formwerk' ),
	textarea: __( 'Message', 'formwerk' ),
	select: __( 'Your choice', 'formwerk' ),
	radio: __( 'Your choice', 'formwerk' ),
	checkboxes: __( 'Your choices', 'formwerk' ),
	consent: __( 'I have read the privacy policy and agree.', 'formwerk' ),
	number: __( 'Number', 'formwerk' ),
	date: __( 'Date', 'formwerk' ),
	heading: __( 'Heading', 'formwerk' ),
};

const DEFAULT_AUTOCOMPLETE = { email: 'email', tel: 'tel' };

export const AUTOCOMPLETE_OPTIONS = [
	{ value: '', label: __( 'No suggestion', 'formwerk' ) },
	{ value: 'name', label: __( 'Full name', 'formwerk' ) },
	{ value: 'given-name', label: __( 'First name', 'formwerk' ) },
	{ value: 'family-name', label: __( 'Last name', 'formwerk' ) },
	{ value: 'email', label: __( 'Email address', 'formwerk' ) },
	{ value: 'tel', label: __( 'Phone number', 'formwerk' ) },
	{
		value: 'organization',
		label: __( 'Company or organisation', 'formwerk' ),
	},
	{ value: 'street-address', label: __( 'Street and number', 'formwerk' ) },
	{ value: 'postal-code', label: __( 'Postcode', 'formwerk' ) },
	{ value: 'address-level2', label: __( 'Town or city', 'formwerk' ) },
	{ value: 'country-name', label: __( 'Country', 'formwerk' ) },
];

export const hasOptions = ( type ) =>
	[ 'select', 'radio', 'checkboxes' ].includes( type );

export const hasValue = ( type ) => type !== 'heading';

export const typeLabel = ( type ) =>
	FIELD_TYPES.find( ( item ) => item.type === type )?.label || type;

let counter = 0;

export function createField( type ) {
	counter += 1;
	return {
		id: `n${ Date.now().toString( 36 ) }${ counter }`,
		type,
		label: DEFAULT_LABELS[ type ] || '',
		name: '',
		required: type === 'consent' || type === 'email',
		placeholder: '',
		help: '',
		content: '',
		options: hasOptions( type )
			? [ __( 'Option 1', 'formwerk' ), __( 'Option 2', 'formwerk' ) ]
			: [],
		beside: false,
		autocomplete: DEFAULT_AUTOCOMPLETE[ type ] || '',
	};
}
