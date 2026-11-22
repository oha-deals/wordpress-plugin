import { useState, useEffect, useRef, useId } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { speak } from '@wordpress/a11y';
import { Button, Spinner } from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import parseInput from './parse-input';
import SettingsLink from './SettingsLink';

const DEBOUNCE_MS = 300;
const MIN_CHARS = 2;

/**
 * Normalise a REST response that may or may not use a { data } envelope.
 *
 * @param {unknown} response Response body.
 * @return {unknown} Payload.
 */
const unwrap = ( response ) =>
	response && response.data !== undefined ? response.data : response;

/**
 * Product search combobox for the oha.deals blocks.
 *
 * Accepts a search term, a slug, an EAN or an https://oha.deals/produkt/<slug> URL.
 *
 * @param {Object}                                                      props
 * @param {string}                                                      props.slug      Currently selected slug.
 * @param {string}                                                      props.ean       Currently selected EAN.
 * @param {(value: {slug: string, ean: string}, item?: Object) => void} props.onSelect  Called with { slug, ean } (empty strings when cleared) and the search result.
 * @param {string}                                                      props.label     Optional label.
 * @param {boolean}                                                     props.canRemove Whether the selection can be cleared.
 * @param {string[]}                                                    props.exclude   Slugs to leave out of the results.
 */
export default function ProductPicker( {
	slug,
	ean,
	onSelect,
	label,
	canRemove = true,
	exclude = [],
} ) {
	const baseId = useId();
	const listId = `${ baseId }-list`;
	const inputId = `${ baseId }-input`;

	const [ query, setQuery ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ status, setStatus ] = useState( 'idle' ); // idle | loading | done | error
	const [ failure, setFailure ] = useState( '' );
	const [ open, setOpen ] = useState( false );
	const [ active, setActive ] = useState( -1 );
	const [ current, setCurrent ] = useState( null );
	const requestId = useRef( 0 );

	// Resolve the stored slug/EAN to a display name.
	useEffect( () => {
		if ( ! slug && ! ean ) {
			setCurrent( null );
			return undefined;
		}
		let cancelled = false;
		apiFetch( {
			path: addQueryArgs(
				'/oha-deals/v1/product',
				slug ? { slug } : { ean }
			),
		} )
			.then( ( res ) => {
				if ( ! cancelled ) {
					setCurrent( unwrap( res ) );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setCurrent( null );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ slug, ean ] );

	// Debounced search, or direct resolve for pasted identifiers.
	useEffect( () => {
		// Bump first, so a request still in flight for an earlier query is ignored even when this one is too short.
		requestId.current += 1;
		const id = requestId.current;

		const parsed = parseInput( query );
		if ( parsed.type === 'search' && parsed.value.length < MIN_CHARS ) {
			setResults( [] );
			setStatus( 'idle' );
			return undefined;
		}

		setResults( [] );
		setActive( -1 );
		setStatus( 'loading' );

		const timer = setTimeout( async () => {
			const stale = () => id !== requestId.current;
			try {
				let items = [];
				if ( parsed.type !== 'search' ) {
					const args =
						parsed.type === 'ean'
							? { ean: parsed.value }
							: { slug: parsed.value };
					try {
						const found = unwrap(
							await apiFetch( {
								path: addQueryArgs(
									'/oha-deals/v1/product',
									args
								),
							} )
						);
						if ( found && found.slug ) {
							items = [ found ];
						}
					} catch {
						// Not a known product: fall back to a text search.
					}
				}
				if ( ! items.length ) {
					const found = unwrap(
						await apiFetch( {
							path: addQueryArgs( '/oha-deals/v1/search', {
								q: query.trim(),
							} ),
						} )
					);
					items = Array.isArray( found ) ? found : [];
				}
				if ( stale() ) {
					return;
				}
				setResults( items );
				setActive( items.length ? 0 : -1 );
				setStatus( 'done' );
				speak(
					items.length
						? sprintf(
								/* translators: %d: number of search results. */
								_n(
									'%d product found.',
									'%d products found.',
									items.length,
									'oha-deals'
								),
								items.length
							)
						: __( 'No products found.', 'oha-deals' ),
					'polite'
				);
			} catch ( error ) {
				if ( stale() ) {
					return;
				}
				setResults( [] );
				setFailure( error?.code || '' );
				setStatus( 'error' );
				speak( __( 'The search failed.', 'oha-deals' ), 'assertive' );
			}
		}, DEBOUNCE_MS );

		return () => clearTimeout( timer );
	}, [ query ] );

	const choose = ( item ) => {
		setCurrent( item );
		setQuery( '' );
		setResults( [] );
		setOpen( false );
		setStatus( 'idle' );
		onSelect( { slug: item.slug || '', ean: item.ean || '' }, item );
	};

	const clear = () => {
		setCurrent( null );
		onSelect( { slug: '', ean: '' } );
	};

	const visible = results.filter(
		( item ) => ! exclude.includes( item.slug )
	);

	const onKeyDown = ( event ) => {
		if ( event.key === 'ArrowDown' ) {
			event.preventDefault();
			setOpen( true );
			setActive( ( i ) => Math.min( i + 1, visible.length - 1 ) );
		} else if ( event.key === 'ArrowUp' ) {
			event.preventDefault();
			setActive( ( i ) => Math.max( i - 1, 0 ) );
		} else if (
			event.key === 'Enter' &&
			open &&
			status === 'done' &&
			visible[ active ]
		) {
			event.preventDefault();
			choose( visible[ active ] );
		} else if ( event.key === 'Escape' && open ) {
			event.stopPropagation();
			setOpen( false );
		}
	};

	const showList = open && query.trim().length > 0 && status !== 'idle';
	const hasSelection = !! ( slug || ean );

	let inputLabel = __( 'Search product', 'oha-deals' );
	if ( label ) {
		inputLabel = label;
	} else if ( hasSelection ) {
		inputLabel = __( 'Change product', 'oha-deals' );
	}

	return (
		<div className="oha-deals-picker">
			{ hasSelection && (
				<div className="oha-deals-picker__current">
					{ current && current.image && (
						<img
							src={ current.image }
							alt=""
							width="40"
							height="40"
						/>
					) }
					<div className="oha-deals-picker__current-text">
						<strong>
							{ current ? current.name : slug || ean }
						</strong>
						{ current && current.priceFormatted && (
							<span>
								{ current.priceFormatted }
								{ current.shop ? ` · ${ current.shop }` : '' }
							</span>
						) }
					</div>
					{ canRemove && (
						<Button
							variant="tertiary"
							isDestructive
							size="compact"
							onClick={ clear }
						>
							{ __( 'Remove', 'oha-deals' ) }
						</Button>
					) }
				</div>
			) }

			<label className="oha-deals-picker__label" htmlFor={ inputId }>
				{ inputLabel }
			</label>
			<input
				id={ inputId }
				className="oha-deals-picker__input components-text-control__input"
				type="text"
				role="combobox"
				aria-expanded={ showList }
				aria-controls={ listId }
				aria-autocomplete="list"
				aria-activedescendant={
					showList && active >= 0
						? `${ baseId }-opt-${ active }`
						: undefined
				}
				autoComplete="off"
				placeholder={ __(
					'Name, EAN, slug or oha.deals URL',
					'oha-deals'
				) }
				value={ query }
				onChange={ ( e ) => {
					setQuery( e.target.value );
					setOpen( true );
				} }
				onFocus={ () => setOpen( true ) }
				onBlur={ () => setTimeout( () => setOpen( false ), 150 ) }
				onKeyDown={ onKeyDown }
			/>

			{ showList && (
				<ul
					id={ listId }
					className="oha-deals-picker__list"
					role="listbox"
					aria-label={ __( 'Products', 'oha-deals' ) }
				>
					{ status === 'loading' && (
						<li
							className="oha-deals-picker__state"
							role="presentation"
						>
							<Spinner /> { __( 'Searching…', 'oha-deals' ) }
						</li>
					) }
					{ status === 'error' && (
						<li
							className="oha-deals-picker__state"
							role="presentation"
							// Keep the list open while its link is clicked.
							onMouseDown={ ( e ) => e.preventDefault() }
						>
							<span>
								{ failure === 'oha_deals_no_key'
									? __(
											'Add your oha.deals API key to search products.',
											'oha-deals'
										)
									: __(
											'The search failed. Please try again, or check the API key.',
											'oha-deals'
										) }{ ' ' }
								<SettingsLink />
							</span>
						</li>
					) }
					{ status === 'done' && visible.length === 0 && (
						<li
							className="oha-deals-picker__state"
							role="presentation"
						>
							{ __( 'No products found.', 'oha-deals' ) }
						</li>
					) }
					{ status === 'done' &&
						visible.map( ( item, index ) => (
							// Keyboard handling lives on the combobox input (aria-activedescendant).
							// eslint-disable-next-line jsx-a11y/click-events-have-key-events
							<li
								key={ item.slug }
								id={ `${ baseId }-opt-${ index }` }
								role="option"
								aria-selected={ index === active }
								className={
									'oha-deals-picker__option' +
									( index === active ? ' is-active' : '' )
								}
								onMouseDown={ ( e ) => e.preventDefault() }
								onClick={ () => choose( item ) }
							>
								{ item.image ? (
									<img
										src={ item.image }
										alt=""
										width="36"
										height="36"
									/>
								) : (
									<span className="oha-deals-picker__noimg" />
								) }
								<span className="oha-deals-picker__option-text">
									<span className="oha-deals-picker__name">
										{ item.name }
									</span>
									<span className="oha-deals-picker__meta">
										{ [
											item.brand,
											item.priceFormatted,
											item.shop,
										]
											.filter( Boolean )
											.join( ' · ' ) }
									</span>
								</span>
							</li>
						) ) }
				</ul>
			) }
		</div>
	);
}
