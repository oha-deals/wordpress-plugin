/**
 * Settings → oha.deals: connection status, cache button and copy buttons.
 *
 * Plain script, not compiled. Talks to the plugin's own REST routes through wp.apiFetch (which sends the REST nonce).
 * All texts come from the server (data attributes), already translated.
 *
 * @param {Object} wp WordPress packages (a11y, apiFetch).
 */
( function ( wp ) {
	'use strict';

	const status = document.getElementById( 'oha-deals-status' );

	/**
	 * Copies text to the clipboard. Falls back to a hidden text area on sites without https.
	 *
	 * @param {string}      text    Text.
	 * @param {HTMLElement} trigger Button that gets the focus back.
	 * @return {Promise<boolean>} Whether the text was copied.
	 */
	function copy( text, trigger ) {
		if ( window.navigator.clipboard && window.isSecureContext ) {
			return window.navigator.clipboard.writeText( text ).then(
				function () {
					return true;
				},
				function () {
					return false;
				}
			);
		}

		const area = document.createElement( 'textarea' );
		let copied = false;
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.className = 'screen-reader-text';
		document.body.appendChild( area );
		area.select();
		try {
			copied = document.execCommand( 'copy' );
		} catch {
			copied = false;
		}
		document.body.removeChild( area );
		trigger.focus();

		return Promise.resolve( copied );
	}

	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '[data-oha-deals-copy]' );
		if ( ! button ) {
			return;
		}
		copy( button.getAttribute( 'data-oha-deals-copy' ), button ).then(
			function ( copied ) {
				const list = button.closest( '[data-copied]' );
				const label = list ? list.getAttribute( 'data-copied' ) : '';
				if (
					! copied ||
					! label ||
					button.classList.contains( 'is-copied' )
				) {
					return;
				}
				const before = button.textContent;
				button.textContent = label;
				button.classList.add( 'is-copied' );
				wp.a11y.speak( label );
				window.setTimeout( function () {
					button.textContent = before;
					button.classList.remove( 'is-copied' );
				}, 2000 );
			}
		);
	} );

	if ( ! status ) {
		return;
	}

	const strings = JSON.parse( status.getAttribute( 'data-strings' ) );
	const result = status.querySelector( '.oha-deals-status__result' );
	const spinner = status.querySelector( '.spinner' );
	const buttons = status.querySelectorAll( '[data-oha-deals-action]' );

	/**
	 * The element that shows one field of the status.
	 *
	 * @param {string} name Field name.
	 * @return {HTMLElement} Element.
	 */
	function field( name ) {
		return status.querySelector( '[data-field="' + name + '"]' );
	}

	/**
	 * Shows a connected key.
	 *
	 * @param {Object} summary Summary from the REST route.
	 */
	function showConnected( summary ) {
		status.setAttribute( 'data-state', 'connected' );
		field( 'title' ).textContent = strings.connected;
		field( 'plan' ).textContent = summary.plan;
		field( 'plan' ).hidden = false;
		field( 'problem' ).hidden = true;
		field( 'facts' ).hidden = false;
		field( 'rateLimit' ).textContent = summary.rateLimit;
		field( 'checkedAt' ).textContent = summary.checkedAt;
		field( 'pricesCheckedAt' ).textContent = summary.pricesCheckedAt;
		field( 'pricesCheckedAt' ).parentNode.hidden =
			! summary.pricesCheckedAt;

		const list = field( 'capabilities' );
		list.textContent = '';
		summary.capabilities.forEach( function ( capability ) {
			const item = document.createElement( 'li' );
			const state = document.createElement( 'span' );
			item.className = capability.enabled ? 'is-on' : 'is-off';
			item.textContent = capability.label + ' ';
			state.className = 'screen-reader-text';
			state.textContent = capability.enabled
				? strings.included
				: strings.not_included;
			item.appendChild( state );
			list.appendChild( item );
		} );
	}

	/**
	 * Shows a failed connection test. The last known facts stay hidden, they may be outdated.
	 *
	 * @param {string} message Error message.
	 */
	function showProblem( message ) {
		status.setAttribute( 'data-state', 'problem' );
		field( 'title' ).textContent = strings.problem;
		field( 'plan' ).hidden = true;
		field( 'facts' ).hidden = true;
		field( 'problem' ).textContent = message;
		field( 'problem' ).hidden = false;
	}

	/**
	 * Disables the buttons and shows the spinner while a request runs.
	 *
	 * @param {boolean} busy Whether a request is running.
	 */
	function setBusy( busy ) {
		spinner.classList.toggle( 'is-active', busy );
		buttons.forEach( function ( button ) {
			// aria-disabled instead of disabled, so the focus stays on the button.
			button.setAttribute( 'aria-disabled', busy ? 'true' : 'false' );
		} );
	}

	/**
	 * Runs one of the actions.
	 *
	 * @param {string}  action Route below oha-deals/v1.
	 * @param {boolean} quiet  Page load: do not announce success.
	 */
	function run( action, quiet ) {
		const isTest = 'connection' === action;
		setBusy( true );
		result.textContent = isTest ? strings.checking : strings.clearing;
		if ( quiet ) {
			result.textContent = '';
		}

		wp.apiFetch( { path: '/oha-deals/v1/' + action, method: 'POST' } )
			.then( function ( response ) {
				if ( isTest ) {
					showConnected( response.summary );
					result.textContent = quiet ? '' : strings.tested;
				} else {
					result.textContent = response.message;
				}
			} )
			.catch( function ( error ) {
				const message =
					error && error.message ? error.message : strings.failed;
				if ( isTest ) {
					result.textContent = '';
					showProblem( message );
					wp.a11y.speak(
						strings.problem + ': ' + message,
						'assertive'
					);
				} else {
					result.textContent = message;
				}
			} )
			.then( function () {
				setBusy( false );
			} );
	}

	status.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '[data-oha-deals-action]' );
		if ( button && 'true' !== button.getAttribute( 'aria-disabled' ) ) {
			run( button.getAttribute( 'data-oha-deals-action' ), false );
		}
	} );

	if ( 'checking' === status.getAttribute( 'data-state' ) ) {
		run( 'connection', true );
	}
} )( window.wp );
