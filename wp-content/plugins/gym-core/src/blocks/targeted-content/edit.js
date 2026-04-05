import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	SelectControl,
	TextControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Builds a summary string of active targeting rules for the placeholder.
 */
function getRuleSummary( attributes ) {
	const parts = [];

	if ( attributes.loggedIn ) {
		parts.push( __( 'Logged-in', 'gym-core' ) );
	}
	if ( attributes.membersOnly ) {
		parts.push( __( 'Active members', 'gym-core' ) );
	}
	if ( attributes.foundationsOnly ) {
		parts.push( __( 'Foundations', 'gym-core' ) );
	}
	if ( attributes.program ) {
		const data = window.gymTargetedContent || {};
		const programs = data.programs || {};
		const labels = attributes.program
			.split( ',' )
			.map( ( s ) => programs[ s ] || s );
		parts.push( labels.join( ', ' ) );
	}
	if ( attributes.minBelt ) {
		const data = window.gymTargetedContent || {};
		const belts = data.belts || [];
		const belt = belts.find( ( b ) => b.slug === attributes.minBelt );
		parts.push(
			belt
				? belt.name + '+'
				: attributes.minBelt + '+'
		);
	}
	if ( attributes.location ) {
		const data = window.gymTargetedContent || {};
		const locations = data.locations || {};
		const labels = attributes.location
			.split( ',' )
			.map( ( s ) => locations[ s ] || s );
		parts.push( labels.join( ', ' ) );
	}
	if ( attributes.minClasses > 0 ) {
		parts.push( attributes.minClasses + '+ classes' );
	}
	if ( attributes.minStreak > 0 ) {
		parts.push( attributes.minStreak + '+ week streak' );
	}

	return parts.length > 0
		? parts.join( ' \u00B7 ' )
		: __( 'No rules set \u2014 visible to everyone', 'gym-core' );
}

/**
 * Toggles a value in a comma-separated string.
 */
function toggleInList( current, value ) {
	const items = current ? current.split( ',' ).filter( Boolean ) : [];
	const index = items.indexOf( value );
	if ( index >= 0 ) {
		items.splice( index, 1 );
	} else {
		items.push( value );
	}
	return items.join( ',' );
}

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'gym-targeted-content-editor',
	} );

	const data = window.gymTargetedContent || {};
	const programs = data.programs || {};
	const locations = data.locations || {};
	const belts = data.belts || [];

	const selectedPrograms = attributes.program
		? attributes.program.split( ',' ).filter( Boolean )
		: [];
	const selectedLocations = attributes.location
		? attributes.location.split( ',' ).filter( Boolean )
		: [];

	const summary = getRuleSummary( attributes );

	const beltOptions = [
		{ label: __( '-- Any --', 'gym-core' ), value: '' },
		...belts.map( ( b ) => ( { label: b.name, value: b.slug } ) ),
	];

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Targeting Rules', 'gym-core' ) }
					initialOpen={ true }
				>
					<p className="components-base-control__help">
						{ __(
							'All rules use AND logic. Only viewers matching every rule will see this content.',
							'gym-core'
						) }
					</p>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Logged-in users only', 'gym-core' ) }
						checked={ attributes.loggedIn }
						onChange={ ( val ) =>
							setAttributes( { loggedIn: val } )
						}
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Active members only', 'gym-core' ) }
						checked={ attributes.membersOnly }
						onChange={ ( val ) =>
							setAttributes( { membersOnly: val } )
						}
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Foundations students only', 'gym-core' ) }
						checked={ attributes.foundationsOnly }
						onChange={ ( val ) =>
							setAttributes( { foundationsOnly: val } )
						}
					/>
				</PanelBody>

				{ Object.keys( programs ).length > 0 && (
					<PanelBody
						title={ __( 'Program & Belt', 'gym-core' ) }
						initialOpen={ false }
					>
						{ Object.entries( programs ).map(
							( [ slug, label ] ) => (
								<ToggleControl
									__nextHasNoMarginBottom
									key={ slug }
									label={ label }
									checked={ selectedPrograms.includes(
										slug
									) }
									onChange={ () =>
										setAttributes( {
											program: toggleInList(
												attributes.program,
												slug
											),
										} )
									}
								/>
							)
						) }

						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Minimum belt', 'gym-core' ) }
							value={ attributes.minBelt }
							options={ beltOptions }
							onChange={ ( val ) =>
								setAttributes( { minBelt: val } )
							}
							help={ __(
								'Uses the matched program\'s hierarchy. Requires a program above.',
								'gym-core'
							) }
						/>
					</PanelBody>
				) }

				{ Object.keys( locations ).length > 0 && (
					<PanelBody
						title={ __( 'Location', 'gym-core' ) }
						initialOpen={ false }
					>
						{ Object.entries( locations ).map(
							( [ slug, label ] ) => (
								<ToggleControl
									__nextHasNoMarginBottom
									key={ slug }
									label={ label }
									checked={ selectedLocations.includes(
										slug
									) }
									onChange={ () =>
										setAttributes( {
											location: toggleInList(
												attributes.location,
												slug
											),
										} )
									}
								/>
							)
						) }
					</PanelBody>
				) }

				<PanelBody
					title={ __( 'Attendance & Streaks', 'gym-core' ) }
					initialOpen={ false }
				>
					<NumberControl
						label={ __( 'Minimum classes', 'gym-core' ) }
						value={ attributes.minClasses }
						min={ 0 }
						onChange={ ( val ) =>
							setAttributes( {
								minClasses: parseInt( val, 10 ) || 0,
							} )
						}
					/>

					<NumberControl
						label={ __(
							'Minimum streak (weeks)',
							'gym-core'
						) }
						value={ attributes.minStreak }
						min={ 0 }
						onChange={ ( val ) =>
							setAttributes( {
								minStreak: parseInt( val, 10 ) || 0,
							} )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Fallback', 'gym-core' ) }
					initialOpen={ false }
				>
					<TextControl
						__nextHasNoMarginBottom
						label={ __(
							'Fallback message',
							'gym-core'
						) }
						value={ attributes.fallback }
						onChange={ ( val ) =>
							setAttributes( { fallback: val } )
						}
						help={ __(
							'Shown to viewers who don\'t match the rules. Leave empty to hide entirely.',
							'gym-core'
						) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="gym-targeted-content-editor__header">
					<span className="gym-targeted-content-editor__icon dashicons dashicons-visibility"></span>
					<span className="gym-targeted-content-editor__label">
						{ __( 'Targeted Content', 'gym-core' ) }
					</span>
					<span className="gym-targeted-content-editor__rules">
						{ summary }
					</span>
				</div>
				<div className="gym-targeted-content-editor__inner">
					<InnerBlocks />
				</div>
			</div>
		</>
	);
}
