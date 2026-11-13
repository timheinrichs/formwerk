/**
 * Rows of the form: a field with `beside` shares a row with the one before it.
 * The same rules as `Schema::normalize()` on the server: at most two fields per
 * row, and headings, long text and consent always take a whole row.
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
			canShareRow( field.type ) &&
			canShareRow( previous.type ) &&
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

/**
 * Moves a field. Rows can only come apart by moving, never form: the moved field
 * leaves its row, and a field that gets a new neighbour above gets a row of its own.
 *
 * @param {Array}  fields Fields in order.
 * @param {number} from   Old position.
 * @param {number} to     New position.
 * @return {{fields: Array, separated: Array<Array>}} New order, and the pairs that came apart.
 */
export function moveField( fields, from, to ) {
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
		)
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
