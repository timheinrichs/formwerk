/**
 * Order and width of the fields. Every field has its own width (100, 50, 33 or 25 %);
 * fields follow each other in rows, and a field that no longer fits starts the next
 * row, the same way as on the website (`Schema::WIDTHS`).
 */

export const WIDTHS = [ 'full', 'half', 'third', 'quarter' ];

/**
 * Columns a width takes in the builder's grid of 12.
 *
 * @param {string} width Width of a field.
 * @return {number} Columns.
 */
export function columnsOf( width ) {
	return { half: 6, third: 4, quarter: 3 }[ width ] || 12;
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
 * Moves a field to another position.
 *
 * @param {Array}  fields Fields in order.
 * @param {number} from   Old position.
 * @param {number} to     New position.
 * @return {Array} Fields.
 */
export function moveField( fields, from, to ) {
	const moved = [ ...fields ];
	moved.splice( to, 0, ...moved.splice( from, 1 ) );
	return moved;
}

/**
 * Moves a field one place up or down. At the edge of a step it goes into the
 * neighbouring step instead: to its end when moving up, to its start when moving down.
 *
 * @param {Array}  fields    Fields in step order.
 * @param {Array}  steps     Steps of the form.
 * @param {number} index     Position of the field.
 * @param {number} direction -1 (up) or 1 (down).
 * @return {{fields: Array, step: Object|null}|null} Result, with the step the field moved into; null when it cannot move.
 */
export function stepMove( fields, steps, index, direction ) {
	const field = fields[ index ];
	const neighbour = fields[ index + direction ];
	if ( ! field ) {
		return null;
	}
	if ( neighbour && ( neighbour.step || '' ) === ( field.step || '' ) ) {
		return {
			fields: moveField( fields, index, index + direction ),
			step: null,
		};
	}
	const position = steps.findIndex( ( step ) => step.id === field.step );
	const target = steps[ position + direction ];
	if ( ! target ) {
		return null;
	}
	return {
		fields: orderBySteps(
			fields.map( ( item, at ) =>
				at === index ? { ...item, step: target.id } : item
			),
			steps
		),
		step: target,
	};
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
	const field = { ...fields[ index ], step: stepId };
	const rest = fields.filter( ( _, at ) => at !== index );
	return orderBySteps( [ ...rest, field ], steps );
}
