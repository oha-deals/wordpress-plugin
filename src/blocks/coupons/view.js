/**
 * Copy button of a coupon code. Without this module the button stays hidden and the code is plain, selectable text.
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

const RESET_MS = 2500;

/**
 * Selects the code, so that it can be copied by hand.
 *
 * @param {HTMLElement} code Code element.
 */
const select = ( code ) => {
	const { ownerDocument } = code;
	const range = ownerDocument.createRange();
	range.selectNodeContents( code );
	const selection = ownerDocument.defaultView.getSelection();
	selection.removeAllRanges();
	selection.addRange( range );
};

/**
 * Copies the code. The Clipboard API needs a secure context and a permission; the fallback works everywhere else.
 *
 * @param {HTMLElement} code Code element.
 * @return {Promise<boolean>} Whether the code was copied.
 */
const copy = async ( code ) => {
	const { ownerDocument } = code;
	const { clipboard } = ownerDocument.defaultView.navigator;
	if ( clipboard ) {
		try {
			await clipboard.writeText( code.textContent );
			return true;
		} catch {
			// Not allowed: use the fallback.
		}
	}
	select( code );
	try {
		return ownerDocument.execCommand( 'copy' );
	} catch {
		return false;
	}
};

store( 'oha-deals/coupon', {
	actions: {
		*copy() {
			const context = getContext();
			const code = getElement()
				.ref.closest( '.oha-deals-coupon__chip' )
				.querySelector( 'code' );

			const copied = yield copy( code );
			context.copied = copied;
			context.status = copied ? context.done : context.manual;

			yield new Promise( ( resolve ) => {
				setTimeout( resolve, RESET_MS );
			} );
			context.copied = false;
			context.status = '';
		},
	},
	callbacks: {
		init() {
			getContext().ready = true;
		},
	},
} );
