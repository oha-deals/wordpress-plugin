import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { moveItem } from '../utils/attributes';
import ProductPicker from './ProductPicker';
import SortableRow from './SortableRow';

/**
 * Picker for several products: search and add, reorder, remove.
 *
 * @param {Object}                    props
 * @param {string[]}                  props.slugs    Selected slugs in order.
 * @param {number}                    props.max      Most products.
 * @param {(slugs: string[]) => void} props.onChange Called with the new slugs.
 */
export default function ProductList( { slugs, max, onChange } ) {
	const [ products, setProducts ] = useState( {} );
	const unknown = slugs.filter( ( slug ) => ! products[ slug ] ).join( ',' );

	// Load the names of products that are not known yet.
	useEffect( () => {
		if ( ! unknown ) {
			return undefined;
		}
		let cancelled = false;
		apiFetch( {
			path: addQueryArgs( '/oha-deals/v1/products', { slugs: unknown } ),
		} )
			.then( ( response ) => {
				const list = response?.data ?? response;
				if ( ! cancelled && Array.isArray( list ) ) {
					setProducts( ( current ) => ( {
						...current,
						...Object.fromEntries(
							list.map( ( item ) => [ item.slug, item ] )
						),
					} ) );
				}
			} )
			.catch( () => {
				// The slugs are shown instead of the names.
			} );
		return () => {
			cancelled = true;
		};
	}, [ unknown ] );

	const nameOf = ( slug ) => products[ slug ]?.name || slug;

	const add = ( { slug }, item ) => {
		if ( ! slug || slugs.includes( slug ) || slugs.length >= max ) {
			return;
		}
		if ( item ) {
			setProducts( ( current ) => ( { ...current, [ slug ]: item } ) );
		}
		onChange( [ ...slugs, slug ] );
		speak(
			/* translators: %s: product name. */
			sprintf( __( '%s added.', 'oha-deals' ), item?.name || slug ),
			'polite'
		);
	};

	return (
		<div className="oha-deals-products">
			{ slugs.length > 0 && (
				<ol className="oha-deals-sortable">
					{ slugs.map( ( slug, index ) => (
						<SortableRow
							key={ slug }
							name={ nameOf( slug ) }
							isFirst={ index === 0 }
							isLast={ index === slugs.length - 1 }
							onMove={ ( offset ) =>
								onChange( moveItem( slugs, index, offset ) )
							}
							after={
								<Button
									size="small"
									variant="tertiary"
									isDestructive
									onClick={ () =>
										onChange(
											slugs.filter(
												( other ) => other !== slug
											)
										)
									}
									aria-label={ sprintf(
										/* translators: %s: product name. */
										__( 'Remove %s', 'oha-deals' ),
										nameOf( slug )
									) }
								>
									{ __( 'Remove', 'oha-deals' ) }
								</Button>
							}
						>
							{ products[ slug ]?.image && (
								<img
									src={ products[ slug ].image }
									alt=""
									width="32"
									height="32"
								/>
							) }
							<span className="oha-deals-sortable__name">
								{ nameOf( slug ) }
							</span>
						</SortableRow>
					) ) }
				</ol>
			) }
			{ slugs.length < max ? (
				<ProductPicker
					label={ __( 'Add a product', 'oha-deals' ) }
					exclude={ slugs }
					onSelect={ add }
				/>
			) : (
				<p className="oha-deals-products__full">
					{ sprintf(
						/* translators: %d: number of products. */
						__(
							'A comparison has at most %d products.',
							'oha-deals'
						),
						max
					) }
				</p>
			) }
		</div>
	);
}
