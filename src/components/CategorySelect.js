import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { SelectControl } from '@wordpress/components';

/**
 * Category dropdown fed by the plugin's REST proxy.
 *
 * @param {Object}                 props
 * @param {string}                 props.value    Selected category slug ('' = all).
 * @param {(slug: string) => void} props.onChange Called with the new slug.
 */
export default function CategorySelect( { value, onChange } ) {
	const [ categories, setCategories ] = useState( [] );
	const [ failed, setFailed ] = useState( false );

	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: '/oha-deals/v1/categories' } )
			.then( ( res ) => {
				const list = res && res.data !== undefined ? res.data : res;
				if ( ! cancelled ) {
					setCategories( Array.isArray( list ) ? list : [] );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setFailed( true );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	const options = [
		{ label: __( 'All categories', 'oha-deals' ), value: '' },
		...categories.map( ( c ) => ( {
			label: c.count ? `${ c.name } (${ c.count })` : c.name,
			value: c.slug,
		} ) ),
	];
	// Keep a stored slug selectable while the list loads or after a failure.
	if ( value && ! options.some( ( o ) => o.value === value ) ) {
		options.push( { label: value, value } );
	}

	return (
		<SelectControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ __( 'Category', 'oha-deals' ) }
			value={ value || '' }
			options={ options }
			onChange={ onChange }
			help={
				failed
					? __( 'Categories could not be loaded.', 'oha-deals' )
					: undefined
			}
		/>
	);
}
