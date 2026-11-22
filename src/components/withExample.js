import {
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { isExample } from '../utils/attributes';

/**
 * The inserter preview: the front-end wrapper with a static illustration.
 *
 * @param {Object}  props
 * @param {string}  props.className Wrapper classes of the front end.
 * @param {unknown} props.children  Illustration.
 */
function Example( { className, children } ) {
	return <div { ...useBlockProps( { className } ) }>{ children }</div>;
}

/**
 * Whether a product block has no product yet.
 *
 * @param {Object} attributes Block attributes.
 * @return {boolean} False for blocks that do not need a product.
 */
const lacksProduct = ( attributes ) =>
	attributes.slugs
		? attributes.slugs.length < 2
		: attributes.slug === '' && ! attributes.ean;

/**
 * Shows a static illustration instead of the edit component for the `example` of block.json, and in previews of
 * patterns whose blocks have no product yet (WordPress 6.8+; before, the placeholder is shown there).
 *
 * @param {(props: Object) => Object}      Edit         Edit component.
 * @param {(props: Object) => Object}      Illustration Illustration component; gets the attributes as props.
 * @param {(attributes: Object) => string} classes      Wrapper classes of the front end.
 * @return {(props: Object) => Object} Edit component.
 */
export default function withExample( Edit, Illustration, classes ) {
	return function EditOrExample( props ) {
		const { attributes } = props;
		const isPreview = useSelect(
			( select ) =>
				!! select( blockEditorStore ).getSettings().isPreviewMode,
			[]
		);
		return isExample( attributes ) ||
			( isPreview && lacksProduct( attributes ) ) ? (
			<Example className={ `oha-deals ${ classes( attributes ) }` }>
				<Illustration { ...attributes } />
			</Example>
		) : (
			<Edit { ...props } />
		);
	};
}
