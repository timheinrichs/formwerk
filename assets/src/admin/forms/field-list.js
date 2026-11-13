import {
	DndContext,
	KeyboardSensor,
	PointerSensor,
	closestCenter,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	SortableContext,
	rectSortingStrategy,
	sortableKeyboardCoordinates,
	useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { speak } from '@wordpress/a11y';
import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { canBeBeside, isHalf, moveField, normalizeRows } from './layout';
import { FIELD_TYPES, typeLabel } from './types';

function Row( {
	field,
	index,
	count,
	isSelected,
	hasError,
	half,
	pair,
	onSelect,
	onMove,
	onToggleBeside,
	onRemove,
} ) {
	const {
		attributes,
		listeners,
		setNodeRef,
		setActivatorNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: field.id } );

	const name = field.label || __( '(no label)', 'formwerk' );
	const classes = [
		'formwerk-field-row',
		isSelected && 'is-selected',
		isDragging && 'is-dragging',
		hasError && 'has-error',
		half && 'is-half',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<li
			ref={ setNodeRef }
			className={ classes }
			style={ {
				transform: CSS.Translate.toString( transform ),
				transition,
			} }
		>
			<button
				type="button"
				className="formwerk-field-row__handle"
				ref={ setActivatorNodeRef }
				{ ...attributes }
				{ ...listeners }
				aria-label={ sprintf(
					/* translators: %s: field label. */
					__(
						'Move “%s” (drag, or press Space and use the arrow keys)',
						'formwerk'
					),
					name
				) }
			>
				<svg
					viewBox="0 0 24 24"
					width="20"
					height="20"
					aria-hidden="true"
					focusable="false"
				>
					<path
						fill="currentColor"
						d="M8 7h2V5H8v2Zm0 6h2v-2H8v2Zm0 6h2v-2H8v2Zm6-14v2h2V5h-2Zm0 8h2v-2h-2v2Zm0 6h2v-2h-2v2Z"
					/>
				</svg>
			</button>
			<button
				type="button"
				id={ `formwerk-row-${ field.id }` }
				className="formwerk-field-row__main"
				onClick={ onSelect }
				aria-current={ isSelected ? 'true' : undefined }
			>
				<span className="formwerk-field-row__label">
					{ name }
					{ field.required && (
						<span className="formwerk-field-row__required">
							{ ' ' }
							{ __( '(required)', 'formwerk' ) }
						</span>
					) }
				</span>
				<span className="formwerk-field-row__type">
					{ hasError && (
						<strong>
							{ __( 'Needs attention', 'formwerk' ) } ·{ ' ' }
						</strong>
					) }
					{ typeLabel( field.type ) }
					{ pair?.pressed &&
						' · ' +
							sprintf(
								/* translators: %s: label of the other field in the row. */
								__( 'Shares a row with “%s”', 'formwerk' ),
								pair.partner
							) }
				</span>
			</button>
			<span className="formwerk-field-row__actions">
				<Button
					size="small"
					icon="arrow-up-alt2"
					label={ sprintf(
						/* translators: %s: field label. */
						__( 'Move “%s” up', 'formwerk' ),
						name
					) }
					disabled={ index === 0 }
					accessibleWhenDisabled
					onClick={ () => onMove( index, index - 1 ) }
				/>
				<Button
					size="small"
					icon="arrow-down-alt2"
					label={ sprintf(
						/* translators: %s: field label. */
						__( 'Move “%s” down', 'formwerk' ),
						name
					) }
					disabled={ index === count - 1 }
					accessibleWhenDisabled
					onClick={ () => onMove( index, index + 1 ) }
				/>
				{ pair && (
					// The same label in both states; aria-pressed tells whether the two share a row.
					<Button
						className="formwerk-field-row__pair"
						size="small"
						icon="columns"
						isPressed={ pair.pressed }
						label={ sprintf(
							/* translators: 1: label of the lower field, 2: label of the upper field. */
							__( 'Put “%1$s” next to “%2$s”', 'formwerk' ),
							pair.lower,
							pair.upper
						) }
						onClick={ onToggleBeside }
					/>
				) }
				<Button
					className="formwerk-field-row__remove"
					size="small"
					icon="trash"
					isDestructive
					label={ sprintf(
						/* translators: %s: field label. */
						__( 'Remove “%s”', 'formwerk' ),
						name
					) }
					onClick={ onRemove }
				/>
			</span>
		</li>
	);
}

export default function FieldList( {
	fields,
	selectedId,
	errorIds,
	onSelect,
	onChange,
	onAdd,
	onRemove,
} ) {
	const sensors = useSensors(
		useSensor( PointerSensor, { activationConstraint: { distance: 6 } } ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const move = ( from, to ) => {
		if ( to < 0 || to >= fields.length || from === to ) {
			return;
		}
		// A field that moves leaves its row; the one that sat next to it stays where it is.
		const result = moveField( fields, from, to );
		onChange( result.fields );
		const messages = [
			sprintf(
				/* translators: 1: field label, 2: new position, 3: number of fields. */
				__( '“%1$s” moved to position %2$d of %3$d.', 'formwerk' ),
				fields[ from ].label,
				to + 1,
				fields.length
			),
			...result.separated.map( ( [ first, second ] ) =>
				sprintf(
					/* translators: 1, 2: field labels. */
					__(
						'“%1$s” and “%2$s” no longer share a row.',
						'formwerk'
					),
					first.label,
					second.label
				)
			),
		];
		speak( messages.join( ' ' ) );
	};

	// What screen readers hear while a field is moved with the keyboard: names and positions,
	// not the internal IDs the drag library would announce.
	const labelOf = ( id ) =>
		fields.find( ( field ) => field.id === id )?.label ||
		__( '(no label)', 'formwerk' );
	const positionOf = ( id ) =>
		fields.findIndex( ( field ) => field.id === id ) + 1;
	const announcements = {
		onDragStart: ( { active } ) =>
			sprintf(
				/* translators: 1: field label, 2: position, 3: number of fields. */
				__(
					'Picked up “%1$s” at position %2$d of %3$d. Use the arrow keys to move it, Space to drop it, Escape to cancel.',
					'formwerk'
				),
				labelOf( active.id ),
				positionOf( active.id ),
				fields.length
			),
		onDragOver: ( { active, over } ) =>
			over
				? sprintf(
						/* translators: 1: field label, 2: position, 3: number of fields. */
						__(
							'“%1$s” is now at position %2$d of %3$d.',
							'formwerk'
						),
						labelOf( active.id ),
						positionOf( over.id ),
						fields.length
				  )
				: undefined,
		onDragEnd: ( { active, over } ) =>
			over
				? sprintf(
						/* translators: 1: field label, 2: position, 3: number of fields. */
						__(
							'“%1$s” dropped at position %2$d of %3$d.',
							'formwerk'
						),
						labelOf( active.id ),
						positionOf( over.id ),
						fields.length
				  )
				: undefined,
		onDragCancel: ( { active } ) =>
			sprintf(
				/* translators: %s: field label. */
				__( 'Moving “%s” cancelled.', 'formwerk' ),
				labelOf( active.id )
			),
	};
	const screenReaderInstructions = {
		draggable: __(
			'To move a field, press Space, use the arrow keys, then press Space again. Press Escape to cancel.',
			'formwerk'
		),
	};

	const labelAt = ( index ) =>
		fields[ index ]?.label || __( '(no label)', 'formwerk' );

	// The row button of a field: on the lower field it pairs it with the one above, on the
	// upper field of a pair it is the same switch, so either half can split the row.
	const pairOf = ( index ) => {
		if ( fields[ index + 1 ]?.beside ) {
			return {
				target: index + 1,
				pressed: true,
				lower: labelAt( index + 1 ),
				upper: labelAt( index ),
				partner: labelAt( index + 1 ),
			};
		}
		if ( canBeBeside( fields, index ) ) {
			return {
				target: index,
				pressed: Boolean( fields[ index ].beside ),
				lower: labelAt( index ),
				upper: labelAt( index - 1 ),
				partner: labelAt( index - 1 ),
			};
		}
		return null;
	};

	const toggleBeside = ( index ) => {
		const field = fields[ index ];
		const beside = ! field.beside;
		onChange(
			normalizeRows(
				fields.map( ( item, position ) =>
					position === index ? { ...item, beside } : item
				)
			)
		);
		speak(
			beside
				? sprintf(
						/* translators: 1: field label, 2: label of the field above. */
						__( '“%1$s” is now next to “%2$s”.', 'formwerk' ),
						field.label,
						fields[ index - 1 ]?.label
				  )
				: sprintf(
						/* translators: %s: field label. */
						__( '“%s” is now on its own row.', 'formwerk' ),
						field.label
				  )
		);
	};

	const onDragEnd = ( { active, over } ) => {
		if ( over && active.id !== over.id ) {
			move(
				fields.findIndex( ( field ) => field.id === active.id ),
				fields.findIndex( ( field ) => field.id === over.id )
			);
		}
	};

	return (
		<div className="formwerk-field-list">
			{ fields.length === 0 ? (
				<p className="formwerk-intro">
					{ __(
						'This form has no fields yet. Add the first one, for example a name or an email address.',
						'formwerk'
					) }
				</p>
			) : (
				<DndContext
					sensors={ sensors }
					collisionDetection={ closestCenter }
					onDragEnd={ onDragEnd }
					accessibility={ {
						announcements,
						screenReaderInstructions,
					} }
				>
					<SortableContext
						items={ fields.map( ( field ) => field.id ) }
						strategy={ rectSortingStrategy }
					>
						<ol className="formwerk-field-rows">
							{ fields.map( ( field, index ) => (
								<Row
									key={ field.id }
									field={ field }
									index={ index }
									count={ fields.length }
									isSelected={ field.id === selectedId }
									hasError={ errorIds.includes( field.id ) }
									half={ isHalf( fields, index ) }
									pair={ pairOf( index ) }
									onSelect={ () => onSelect( field.id ) }
									onMove={ move }
									onToggleBeside={ () =>
										toggleBeside( pairOf( index ).target )
									}
									onRemove={ () => onRemove( field.id ) }
								/>
							) ) }
						</ol>
					</SortableContext>
				</DndContext>
			) }
			<DropdownMenu
				icon="plus"
				label={ __( 'Add field', 'formwerk' ) }
				text={ __( 'Add field', 'formwerk' ) }
				toggleProps={ {
					id: 'formwerk-add-field',
					variant: 'secondary',
					__next40pxDefaultSize: true,
				} }
			>
				{ ( { onClose } ) => (
					<MenuGroup>
						{ FIELD_TYPES.map( ( item ) => (
							<MenuItem
								key={ item.type }
								onClick={ () => {
									onAdd( item.type );
									onClose();
								} }
							>
								{ item.label }
							</MenuItem>
						) ) }
					</MenuGroup>
				) }
			</DropdownMenu>
		</div>
	);
}
