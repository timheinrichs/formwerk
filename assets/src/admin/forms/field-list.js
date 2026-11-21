import {
	DndContext,
	KeyboardSensor,
	PointerSensor,
	closestCenter,
	pointerWithin,
	useDroppable,
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
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { moveField, moveToStep, stepMove } from './layout';
import { FIELD_TYPES, hasValue, typeLabel } from './types';

// "50 %" etc., for the card.
function widthLabel( width ) {
	return {
		half: __( '50 %', 'formwerk' ),
		third: __( '33 %', 'formwerk' ),
		quarter: __( '25 %', 'formwerk' ),
	}[ width ];
}

function Row( {
	field,
	index,
	canMoveUp,
	canMoveDown,
	isSelected,
	hasError,
	onSelect,
	onMove,
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
		`is-width-${ field.width || 'full' }`,
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
					{ field.width &&
						field.width !== 'full' &&
						` · ${ widthLabel( field.width ) }` }
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
					id={ `formwerk-row-${ field.id }-up` }
					disabled={ ! canMoveUp }
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
					id={ `formwerk-row-${ field.id }-down` }
					disabled={ ! canMoveDown }
					accessibleWhenDisabled
					onClick={ () => onMove( index, index + 1 ) }
				/>
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

const STEP_DROP = 'step:';

// Adds a field; in a form in steps, to the given step.
function AddField( { id, onAdd } ) {
	return (
		<DropdownMenu
			icon="plus"
			label={ __( 'Add field', 'formwerk' ) }
			text={ __( 'Add field', 'formwerk' ) }
			toggleProps={ {
				id,
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
	);
}

// The list of a step also takes fields dropped on its empty space.
function StepDrop( { stepId, children } ) {
	const { setNodeRef, isOver } = useDroppable( {
		id: STEP_DROP + stepId,
	} );
	return (
		<div
			ref={ setNodeRef }
			className={ `formwerk-step-area__drop${
				isOver ? ' is-over' : ''
			}` }
		>
			{ children }
		</div>
	);
}

export default function FieldList( {
	fields,
	steps = null,
	selectedId,
	errorIds,
	stepErrors = [],
	onSelect,
	onChange,
	onAdd,
	onRemove,
	onRenameStep,
	onMoveStep,
	onRemoveStep,
	onAddStep,
	maxSteps = 20,
} ) {
	const sensors = useSensors(
		useSensor( PointerSensor, { activationConstraint: { distance: 6 } } ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);
	const inSteps = Array.isArray( steps ) && steps.length > 0;
	const stepTitle = ( step ) =>
		step?.title ||
		sprintf(
			/* translators: %d: number of the step. */
			__( 'Step %d', 'formwerk' ),
			steps.findIndex( ( item ) => item.id === step?.id ) + 1
		);

	// "position 2 of 4", or in a form in steps "position 2 of 4 in step “Course”".
	const place = ( index ) => {
		const field = fields[ index ];
		if ( ! inSteps || ! field ) {
			return sprintf(
				/* translators: 1: position, 2: number of fields. */
				__( 'position %1$d of %2$d', 'formwerk' ),
				index + 1,
				fields.length
			);
		}
		const inStep = fields.filter( ( item ) => item.step === field.step );
		return sprintf(
			/* translators: 1: position, 2: number of fields in the step, 3: step title. */
			__( 'position %1$d of %2$d in step “%3$s”', 'formwerk' ),
			inStep.indexOf( field ) + 1,
			inStep.length,
			stepTitle( steps.find( ( step ) => step.id === field.step ) )
		);
	};

	// After a move the row may sit in another step's list: keep focus on its arrow.
	const keepFocus = ( id, direction ) =>
		window.setTimeout( () => {
			const preferred = document.getElementById(
				`formwerk-row-${ id }-${ direction < 0 ? 'up' : 'down' }`
			);
			const other = document.getElementById(
				`formwerk-row-${ id }-${ direction < 0 ? 'down' : 'up' }`
			);
			( preferred?.getAttribute( 'aria-disabled' ) === 'true'
				? other
				: preferred
			)?.focus();
		}, 60 );

	const move = ( from, to ) => {
		if ( inSteps && Math.abs( to - from ) === 1 ) {
			// Arrow buttons: at the edge of a step the field goes into the next one.
			const result = stepMove( fields, steps, from, to - from );
			if ( ! result ) {
				return;
			}
			onChange( result.fields );
			keepFocus( fields[ from ].id, to - from );
			speak(
				[
					result.step
						? sprintf(
								/* translators: 1: field label, 2: step title. */
								__(
									'“%1$s” moved to step “%2$s”.',
									'formwerk'
								),
								fields[ from ].label,
								stepTitle( result.step )
						  )
						: sprintf(
								/* translators: 1: field label, 2: new place, e.g. "position 2 of 4". */
								__( '“%1$s” moved to %2$s.', 'formwerk' ),
								fields[ from ].label,
								place( to )
						  ),
				].join( ' ' )
			);
			return;
		}
		if ( to < 0 || to >= fields.length || from === to ) {
			return;
		}
		const source = inSteps
			? fields.map( ( field, index ) =>
					index === from
						? { ...field, step: fields[ to ].step }
						: field
			  )
			: fields;
		onChange( moveField( source, from, to ) );
		speak(
			sprintf(
				/* translators: 1: field label, 2: new place, e.g. "position 2 of 4". */
				__( '“%1$s” moved to %2$s.', 'formwerk' ),
				fields[ from ].label,
				place( to )
			)
		);
	};

	// What screen readers hear while a field is moved with the keyboard: names and positions,
	// not the internal IDs the drag library would announce.
	const labelOf = ( id ) =>
		fields.find( ( field ) => field.id === id )?.label ||
		__( '(no label)', 'formwerk' );
	const placeOf = ( id ) =>
		place( fields.findIndex( ( field ) => field.id === id ) );
	const overText = ( active, over, template ) => {
		if ( String( over.id ).startsWith( STEP_DROP ) ) {
			return sprintf(
				/* translators: 1: field label, 2: step title. */
				__( '“%1$s” is over step “%2$s”.', 'formwerk' ),
				labelOf( active.id ),
				stepTitle(
					steps.find(
						( step ) => STEP_DROP + step.id === String( over.id )
					)
				)
			);
		}
		return sprintf( template, labelOf( active.id ), placeOf( over.id ) );
	};
	const announcements = {
		onDragStart: ( { active } ) =>
			sprintf(
				/* translators: 1: field label, 2: place, e.g. "position 2 of 4". */
				__(
					'Picked up “%1$s” at %2$s. Use the arrow keys to move it, Space to drop it, Escape to cancel.',
					'formwerk'
				),
				labelOf( active.id ),
				placeOf( active.id )
			),
		onDragOver: ( { active, over } ) =>
			over
				? overText(
						active,
						over,
						/* translators: 1: field label, 2: place, e.g. "position 2 of 4". */
						__( '“%1$s” is now at %2$s.', 'formwerk' )
				  )
				: undefined,
		onDragEnd: ( { active, over } ) =>
			over
				? overText(
						active,
						over,
						/* translators: 1: field label, 2: place, e.g. "position 2 of 4". */
						__( '“%1$s” dropped at %2$s.', 'formwerk' )
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

	const onDragEnd = ( { active, over } ) => {
		if ( ! over || active.id === over.id ) {
			return;
		}
		const from = fields.findIndex( ( field ) => field.id === active.id );
		if ( String( over.id ).startsWith( STEP_DROP ) ) {
			const stepId = String( over.id ).slice( STEP_DROP.length );
			if ( fields[ from ]?.step !== stepId ) {
				onChange( moveToStep( fields, steps, from, stepId ) );
			}
			return;
		}
		move(
			from,
			fields.findIndex( ( field ) => field.id === over.id )
		);
	};

	// Fields win over the step areas around them, so a field can be sorted within its step.
	// A step's area only takes a field when the step has no fields yet.
	const collisions = ( args ) => {
		const items = () =>
			args.droppableContainers.filter(
				( container ) => ! isArea( container )
			);
		const isArea = ( container ) =>
			String( container.id ).startsWith( STEP_DROP );
		const emptyAreas = args.droppableContainers.filter(
			( container ) =>
				isArea( container ) &&
				! fields.some(
					( field ) =>
						STEP_DROP + field.step === String( container.id )
				)
		);
		if ( args.pointerCoordinates ) {
			const overEmpty = pointerWithin( {
				...args,
				droppableContainers: emptyAreas,
			} );
			if ( overEmpty.length ) {
				return overEmpty;
			}
			return closestCenter( { ...args, droppableContainers: items() } );
		}
		// Keyboard: empty steps count as places to go, too.
		return closestCenter( {
			...args,
			droppableContainers: [ ...items(), ...emptyAreas ],
		} );
	};

	const row = ( field, index ) => (
		<Row
			key={ field.id }
			field={ field }
			index={ index }
			canMoveUp={ index > 0 }
			canMoveDown={
				index < fields.length - 1 ||
				( inSteps && field.step !== steps[ steps.length - 1 ].id )
			}
			isSelected={ field.id === selectedId }
			hasError={ errorIds.includes( field.id ) }
			onSelect={ () => onSelect( field.id ) }
			onMove={ move }
			onRemove={ () => onRemove( field.id ) }
		/>
	);

	if ( ! inSteps ) {
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
								{ fields.map( row ) }
							</ol>
						</SortableContext>
					</DndContext>
				) }
				<AddField id="formwerk-add-field" onAdd={ onAdd } />
			</div>
		);
	}

	return (
		<div className="formwerk-field-list formwerk-field-list--steps">
			<DndContext
				sensors={ sensors }
				collisionDetection={ collisions }
				onDragEnd={ onDragEnd }
				accessibility={ { announcements, screenReaderInstructions } }
			>
				{ steps.map( ( step, stepIndex ) => {
					const inStep = fields
						.map( ( field, index ) => [ field, index ] )
						.filter( ( [ field ] ) => field.step === step.id );
					const error = stepErrors.find(
						( item ) => item.step === step.id
					);
					const name = stepTitle( step );
					// Known before saving: a step needs a field to fill in, not only a heading.
					const nothingToFill =
						inStep.length > 0 &&
						! inStep.some( ( [ field ] ) =>
							hasValue( field.type )
						);
					return (
						<section
							key={ step.id }
							className={ `formwerk-step-area${
								error ? ' has-error' : ''
							}` }
							aria-labelledby={ `formwerk-step-${ step.id }-heading` }
						>
							<div className="formwerk-step-area__head">
								<h3 id={ `formwerk-step-${ step.id }-heading` }>
									{ step.title
										? sprintf(
												/* translators: 1: number of the step, 2: number of steps, 3: step title. */
												__(
													'Step %1$d of %2$d: %3$s',
													'formwerk'
												),
												stepIndex + 1,
												steps.length,
												step.title
										  )
										: sprintf(
												/* translators: 1: number of the step, 2: number of steps. */
												__(
													'Step %1$d of %2$d',
													'formwerk'
												),
												stepIndex + 1,
												steps.length
										  ) }
								</h3>
								<span className="formwerk-step-area__actions">
									<Button
										size="small"
										icon="arrow-up-alt2"
										label={ sprintf(
											/* translators: %s: step title. */
											__(
												'Move step “%s” up',
												'formwerk'
											),
											name
										) }
										id={ `formwerk-step-${ step.id }-up` }
										disabled={ stepIndex === 0 }
										accessibleWhenDisabled
										onClick={ () =>
											onMoveStep( step.id, -1 )
										}
									/>
									<Button
										size="small"
										icon="arrow-down-alt2"
										label={ sprintf(
											/* translators: %s: step title. */
											__(
												'Move step “%s” down',
												'formwerk'
											),
											name
										) }
										id={ `formwerk-step-${ step.id }-down` }
										disabled={
											stepIndex === steps.length - 1
										}
										accessibleWhenDisabled
										onClick={ () =>
											onMoveStep( step.id, 1 )
										}
									/>
									<Button
										className="formwerk-field-row__remove"
										size="small"
										icon="trash"
										isDestructive
										label={ sprintf(
											/* translators: %s: step title. */
											__(
												'Remove step “%s”',
												'formwerk'
											),
											name
										) }
										disabled={ steps.length === 1 }
										accessibleWhenDisabled
										onClick={ () =>
											onRemoveStep( step.id )
										}
									/>
								</span>
							</div>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								id={ `formwerk-step-${ step.id }-title` }
								label={ __( 'Title of the step', 'formwerk' ) }
								value={ step.title }
								placeholder={ name }
								onChange={ ( title ) =>
									onRenameStep( step.id, title )
								}
								help={
									stepIndex === 0
										? __(
												'Visitors see it in the line “Step 1 of 2: About you”. Without a title the step is called “Step 1”.',
												'formwerk'
										  )
										: undefined
								}
							/>
							{ error && (
								<p
									className="formwerk-field-error"
									id={ `formwerk-step-${ step.id }-error` }
								>
									{ error.message }
								</p>
							) }
							{ ! error && nothingToFill && (
								<p className="formwerk-hint formwerk-step-area__warning">
									{ __(
										'This step has no field to fill in yet. Move a field into it, or the form cannot be saved.',
										'formwerk'
									) }
								</p>
							) }
							<StepDrop stepId={ step.id }>
								<SortableContext
									items={ inStep.map(
										( [ field ] ) => field.id
									) }
									strategy={ rectSortingStrategy }
								>
									{ inStep.length === 0 ? (
										<p className="formwerk-hint formwerk-step-area__empty">
											{ __(
												'No fields yet. Add one, or drag a field here.',
												'formwerk'
											) }
										</p>
									) : (
										<ol className="formwerk-field-rows">
											{ inStep.map(
												( [ field, index ] ) =>
													row( field, index )
											) }
										</ol>
									) }
								</SortableContext>
							</StepDrop>
							<AddField
								id={ `formwerk-add-field-${ step.id }` }
								onAdd={ ( type ) => onAdd( type, step.id ) }
							/>
						</section>
					);
				} ) }
			</DndContext>
			<Button
				variant="secondary"
				icon="plus"
				id="formwerk-add-step"
				onClick={ onAddStep }
				disabled={ steps.length >= maxSteps }
				accessibleWhenDisabled
				__next40pxDefaultSize
			>
				{ __( 'Add step', 'formwerk' ) }
			</Button>
			{ steps.length >= maxSteps && (
				<p className="formwerk-hint">
					{ sprintf(
						/* translators: %d: largest number of steps. */
						__( 'A form can have at most %d steps.', 'formwerk' ),
						maxSteps
					) }
				</p>
			) }
		</div>
	);
}
