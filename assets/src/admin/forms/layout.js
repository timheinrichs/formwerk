/**
 * Rows of the form: a field with `beside` shares a row with the one before it.
 * The same rules as `Schema::normalize()` on the server: at most two fields per
 * row, headings, long text and consent always take a whole row, and a row never
 * spans two steps.
 */

const OWN_ROW_TYPES = [ 'heading', 'textarea', 'consent' ];

/**
 * Whether a field of this type may sit next to another one.
 *
 * @param {string} type Field type.
 * @return {boolean} Whether it can share a row.
 */
export function canShareRow( type ) {
	return ! OWN_ROW_TYPES.includes( type );
}

/**
 * Whether the field at this position may sit next to the previous one.
 *
 * @param {Array}   fields  Fields in order.
 * @param {number}  index   Position of the field.
 * @param {boolean} inSteps Whether the form is shown in steps: then a row ends at a step.
 * @return {boolean} Whether a pair is possible.
 */
export function canBeBeside( fields, index, inSteps = false ) {
	const field = fields[ index ];
	const previous = fields[ index - 1 ];
	return Boolean(
		field &&
			previous &&
			canShareRow( field.type ) &&
			canShareRow( previous.type ) &&
			( ! inSteps || ( previous.step || '' ) === ( field.step || '' ) ) &&
			! previous.beside
	);
}

/**
 * Puts the fields in step order, keeping the order within each step, as the server
 * does. Fields without a known step go to the first one.
 *
 * @param {Array} fields Fields.
 * @param {Array} steps  Steps of the form; empty for a simple form.
 * @return {Array} Fields in step order.
 */
export function orderBySteps( fields, steps ) {
	if ( ! steps?.length ) {
		return fields;
	}
	const order = new Map( steps.map( ( step, index ) => [ step.id, index ] ) );
	return fields
		.map( ( field, index ) => [
			order.has( field.step ) ? field : { ...field, step: steps[ 0 ].id },
			index,
		] )
		.sort(
			( [ a, i ], [ b, j ] ) =>
				order.get( a.step ) - order.get( b.step ) || i - j
		)
		.map( ( [ field ] ) => field );
}

/**
 * Moves a field one place up or down. At the edge of a step it goes into the
 * neighbouring step instead: to its end when moving up, to its start when moving down.
 *
 * @param {Array}  fields    Fields in step order.
 * @param {Array}  steps     Steps of the form.
 * @param {number} index     Position of the field.
 * @param {number} direction -1 (up) or 1 (down).
 * @return {{fields: Array, separated: Array<Array>, step: Object|null}|null} Result, with the step the field moved into; null when it cannot move.
 */
export function stepMove( fields, steps, index, direction ) {
	const field = fields[ index ];
	const neighbour = fields[ index + direction ];
	if ( ! field ) {
		return null;
	}
	if ( neighbour && ( neighbour.step || '' ) === ( field.step || '' ) ) {
		return {
			...moveField( fields, index, index + direction, true ),
			step: null,
		};
	}
	const position = steps.findIndex( ( step ) => step.id === field.step );
	const target = steps[ position + direction ];
	if ( ! target ) {
		return null;
	}
	const before = detach( fields, index );
	const moved = before.map( ( item, at ) =>
		at === index ? { ...item, step: target.id, beside: false } : item
	);
	const result = normalizeRows( orderBySteps( moved, steps ), true );
	const stillBeside = new Set(
		result.filter( ( item ) => item.beside ).map( ( item ) => item.id )
	);
	const separated = fields
		.map( ( item, at ) => [ fields[ at - 1 ], item ] )
		.filter(
			( [ previous, item ] ) =>
				item.beside && previous && ! stillBeside.has( item.id )
		);
	return { fields: result, separated, step: target };
}

/**
 * Puts a field into another step, at the end of it (dropped on the step's area).
 *
 * @param {Array}  fields Fields in step order.
 * @param {Array}  steps  Steps of the form.
 * @param {number} index  Position of the field.
 * @param {string} stepId Target step.
 * @return {Array} Fields.
 */
export function moveToStep( fields, steps, index, stepId ) {
	const field = {
		...detach( fields, index )[ index ],
		step: stepId,
		beside: false,
	};
	const rest = detach( fields, index ).filter( ( _, at ) => at !== index );
	return normalizeRows( orderBySteps( [ ...rest, field ], steps ), true );
}

/**
 * Drops pairs that the rules no longer allow, e.g. after a field moved.
 *
 * @param {Array}   fields  Fields in order.
 * @param {boolean} inSteps Whether the form is shown in steps.
 * @return {Array} Fields with valid pairs only.
 */
export function normalizeRows( fields, inSteps = false ) {
	const result = [];
	fields.forEach( ( field ) => {
		const index = result.length;
		const beside =
			Boolean( field.beside ) &&
			canBeBeside( [ ...result, field ], index, inSteps );
		result.push( field.beside === beside ? field : { ...field, beside } );
	} );
	return result;
}

/**
 * Releases the field that sat next to the given one, before that one moves or goes.
 * Without this, the follower would pair up with whatever field comes before it next.
 *
 * @param {Array}  fields Fields in order.
 * @param {number} index  Position of the field that moves or is removed.
 * @return {Array} Fields.
 */
export function detach( fields, index ) {
	return fields.map( ( field, position ) => {
		if ( position === index && field.beside ) {
			return { ...field, beside: false };
		}
		if ( position === index + 1 && field.beside ) {
			return { ...field, beside: false };
		}
		return field;
	} );
}

/**
 * Whether a field is one half of a row.
 *
 * @param {Array}  fields Fields in order.
 * @param {number} index  Position of the field.
 * @return {boolean} Whether it shares its row.
 */
export function isHalf( fields, index ) {
	return Boolean( fields[ index ]?.beside || fields[ index + 1 ]?.beside );
}

/**
 * Moves a field. Rows can only come apart by moving, never form: the moved field
 * leaves its row, and a field that gets a new neighbour above gets a row of its own.
 *
 * @param {Array}   fields  Fields in order.
 * @param {number}  from    Old position.
 * @param {number}  to      New position.
 * @param {boolean} inSteps Whether the form is shown in steps.
 * @return {{fields: Array, separated: Array<Array>}} New order, and the pairs that came apart.
 */
export function moveField( fields, from, to, inSteps = false ) {
	const above = new Map(
		fields.map( ( field, index ) => [ field.id, fields[ index - 1 ]?.id ] )
	);
	const moved = [ ...fields ];
	moved.splice( to, 0, ...moved.splice( from, 1 ) );
	const result = normalizeRows(
		moved.map( ( field, index ) =>
			field.beside &&
			( index === to || above.get( field.id ) !== moved[ index - 1 ]?.id )
				? { ...field, beside: false }
				: field
		),
		inSteps
	);
	const stillBeside = new Set(
		result.filter( ( field ) => field.beside ).map( ( field ) => field.id )
	);
	const separated = fields
		.map( ( field, index ) => [ fields[ index - 1 ], field ] )
		.filter(
			( [ previous, field ] ) =>
				field.beside && previous && ! stillBeside.has( field.id )
		);
	return { fields: result, separated };
}
