/**
 * Rows of the form: a field with `beside` shares a row with the one before it.
 * The same rules as `Schema::normalize()` on the server: at most two fields per
 * row, and never a heading.
 */

/**
 * Whether the field at this position may sit next to the previous one.
 *
 * @param {Array}  fields Fields in order.
 * @param {number} index  Position of the field.
 * @return {boolean} Whether a pair is possible.
 */
export function canBeBeside( fields, index ) {
	const field = fields[ index ];
	const previous = fields[ index - 1 ];
	return Boolean(
		field &&
			previous &&
			field.type !== 'heading' &&
			previous.type !== 'heading' &&
			! previous.beside
	);
}

/**
 * Drops pairs that the rules no longer allow, e.g. after a field moved.
 *
 * @param {Array} fields Fields in order.
 * @return {Array} Fields with valid pairs only.
 */
export function normalizeRows( fields ) {
	const result = [];
	fields.forEach( ( field ) => {
		const index = result.length;
		const beside =
			Boolean( field.beside ) &&
			canBeBeside( [ ...result, field ], index );
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
