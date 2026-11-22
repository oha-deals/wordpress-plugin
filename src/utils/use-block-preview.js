import apiFetch from '@wordpress/api-fetch';
import { useEffect, useRef, useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';

const DEBOUNCE_MS = 400;

/**
 * Splits the server-rendered HTML of a block into its wrapper and its content, so that the editor's own block wrapper
 * can take the place of the server's: otherwise borders, padding and colours would apply twice.
 *
 * @param {string} html Rendered block.
 * @return {{ className: string, content: string, notice: string, keyProblem: boolean }} `notice` is the message when the
 *         server rendered an editor notice instead of the element; `keyProblem` whether the API key is the reason.
 */
function splitPreview( html ) {
	const { body } = new window.DOMParser().parseFromString(
		html,
		'text/html'
	);
	const root = body.childElementCount === 1 ? body.firstElementChild : null;
	if ( ! root || ! root.classList.contains( 'oha-deals' ) ) {
		return {
			className: '',
			content: html,
			notice: '',
			keyProblem: false,
		};
	}
	if ( root.classList.contains( 'oha-deals-notice' ) ) {
		root.querySelectorAll( 'strong, em' ).forEach( ( element ) =>
			element.remove()
		);
		return {
			className: '',
			content: '',
			notice: root.textContent.trim(),
			keyProblem: root.classList.contains( 'oha-deals-notice--key' ),
		};
	}
	return {
		className: root.className,
		content: root.innerHTML,
		notice: '',
		keyProblem: false,
	};
}

/**
 * Loads the server-rendered preview of a block. While a new one loads, the last one stays.
 *
 * ServerSideRender from core always prints the server's wrapper inside the block, and the useServerSideRender hook
 * does not exist in WordPress 6.6; this uses the same REST route directly.
 *
 * @param {string}  name       Block name.
 * @param {Object}  attributes Attributes of the block definition, without support attributes.
 * @param {boolean} enabled    Whether to load anything.
 * @return {{ isLoading: boolean, className: string, content: string|null, notice: string, keyProblem: boolean, error: string }}
 *         Preview; `content` is null until the first answer.
 */
export default function useBlockPreview( name, attributes, enabled ) {
	const [ preview, setPreview ] = useState( {
		isLoading: enabled,
		className: '',
		content: null,
		notice: '',
		keyProblem: false,
		error: '',
	} );
	const isFirst = useRef( true );
	const serialized = JSON.stringify( attributes );

	useEffect( () => {
		if ( ! enabled ) {
			return undefined;
		}
		const controller = new window.AbortController();
		const timer = setTimeout(
			() => {
				isFirst.current = false;
				apiFetch( {
					path: addQueryArgs( `/wp/v2/block-renderer/${ name }`, {
						context: 'edit',
					} ),
					method: 'POST',
					data: { attributes: JSON.parse( serialized ) },
					signal: controller.signal,
				} )
					.then( ( { rendered } ) =>
						setPreview( {
							isLoading: false,
							error: '',
							...splitPreview( rendered || '' ),
						} )
					)
					.catch( ( error ) => {
						if ( ! controller.signal.aborted ) {
							setPreview( {
								isLoading: false,
								className: '',
								content: '',
								notice: '',
								keyProblem: false,
								error: error?.message || '',
							} );
						}
					} );
			},
			isFirst.current ? 0 : DEBOUNCE_MS
		);
		setPreview( ( current ) => ( { ...current, isLoading: true } ) );

		return () => {
			clearTimeout( timer );
			controller.abort();
		};
	}, [ name, serialized, enabled ] );

	return preview;
}
