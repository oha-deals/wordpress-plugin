import {
	BlockControls,
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import { Disabled, Placeholder, Spinner } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { RawHTML, useEffect, useRef } from '@wordpress/element';
import { doAction } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { ownAttributes } from '../utils/attributes';
import { PRODUCTS_CHANGED } from '../utils/events';
import useBlockPreview from '../utils/use-block-preview';
import ProductPicker from './ProductPicker';
import ReplaceProduct from './ReplaceProduct';
import SettingsLink from './SettingsLink';
import './editor.scss';

/**
 * Shared editor wrapper: a placeholder until the block has what it needs, then a server-rendered preview.
 *
 * @param {Object}                       props
 * @param {Object}                       props.metadata      Contents of block.json.
 * @param {Object}                       props.icon          Block icon.
 * @param {string}                       props.label         Block title for the placeholder.
 * @param {Object}                       props.attributes    Block attributes.
 * @param {(attributes: Object) => void} props.setAttributes Attribute setter.
 * @param {boolean}                      props.needsProduct  Whether the block shows one product, chosen by slug or EAN.
 * @param {unknown}                      props.placeholder   Replaces the preview while the block has nothing to show.
 * @param {unknown}                      props.children      Inspector controls.
 */
export default function BlockEdit( {
	metadata,
	icon,
	label,
	attributes,
	setAttributes,
	needsProduct,
	placeholder,
	children,
} ) {
	const { stopTyping } = useDispatch( blockEditorStore );
	const wrapperRef = useRef( null );
	const justChosen = useRef( false );
	const missing = needsProduct && ! attributes.slug && ! attributes.ean;
	const isEmpty = missing || !! placeholder;

	const { isLoading, className, content, notice, keyProblem, error } =
		useBlockPreview(
			metadata.name,
			ownAttributes( attributes, metadata.attributes ),
			! isEmpty
		);
	const hasPreview = ! isEmpty && !! content;
	const blockProps = useBlockProps( {
		ref: wrapperRef,
		className: hasPreview ? className : undefined,
	} );

	// The picker disappears once a product is chosen; move focus to the block so keyboard users are not lost.
	useEffect( () => {
		if ( justChosen.current && ! missing && wrapperRef.current ) {
			justChosen.current = false;
			wrapperRef.current.focus();
		}
	}, [ missing ] );

	// The Block Bindings source offers the values of the products in the post.
	const products = [ attributes.slug, attributes.slugs ].flat().join();
	useEffect( () => {
		doAction( PRODUCTS_CHANGED );
		return () => doAction( PRODUCTS_CHANGED );
	}, [ products ] );

	const select = ( { slug, ean } ) => {
		justChosen.current = missing && !! ( slug || ean );
		setAttributes( { slug, ean } );
		// The search counts as typing, which hides the block toolbar.
		stopTyping();
	};

	let body;
	if ( missing ) {
		body = (
			<Placeholder
				icon={ icon.src }
				label={ label }
				instructions={ __(
					'Choose a product to show live prices.',
					'oha-deals'
				) }
				className="oha-deals-placeholder"
			>
				<ProductPicker
					slug={ attributes.slug }
					ean={ attributes.ean }
					onSelect={ select }
				/>
			</Placeholder>
		);
	} else if ( placeholder ) {
		body = placeholder;
	} else if ( content ) {
		body = (
			<>
				{ /* Links and buttons of the preview must not navigate or take focus. */ }
				<Disabled className={ isLoading ? 'is-loading' : undefined }>
					<RawHTML>{ content }</RawHTML>
				</Disabled>
				{ isLoading && (
					<div className="oha-deals-preview-spinner">
						<Spinner />
					</div>
				) }
			</>
		);
	} else if ( isLoading ) {
		body = (
			<Placeholder
				icon={ icon.src }
				label={ label }
				className="oha-deals-placeholder"
			>
				<Spinner />
			</Placeholder>
		);
	} else {
		body = (
			<Placeholder
				icon={ icon.src }
				label={ label }
				className="oha-deals-placeholder"
			>
				<p className="oha-deals-placeholder__message">
					{ notice ||
						error ||
						__(
							'Nothing to show yet. Check the block settings.',
							'oha-deals'
						) }
				</p>
				{ keyProblem && <SettingsLink /> }
			</Placeholder>
		);
	}

	return (
		<>
			{ children }
			{ needsProduct && ! missing && (
				<BlockControls group="other">
					<ReplaceProduct
						slug={ attributes.slug }
						ean={ attributes.ean }
						onSelect={ select }
					/>
				</BlockControls>
			) }
			<div { ...blockProps }>{ body }</div>
		</>
	);
}
