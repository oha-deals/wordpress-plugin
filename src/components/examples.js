/**
 * Static illustrations for the inserter preview: the front-end markup with invented products, no request.
 */
import { __, sprintf } from '@wordpress/i18n';

const SHAPES = {
	headphones:
		'M18 52V38a22 22 0 0 1 44 0v14h-9V38a13 13 0 0 0-26 0v14h-9Zm-2-2h12v18H20a4 4 0 0 1-4-4V50Zm36 0h12v14a4 4 0 0 1-4 4h-8V50Z',
	watch: 'M30 12h20l3 14H27l3-14Zm-4 14h28a4 4 0 0 1 4 4v20a4 4 0 0 1-4 4H26a4 4 0 0 1-4-4V30a4 4 0 0 1 4-4Zm2 5v18h24V31H28Zm-1 23h26l-3 14H30l-3-14Z',
	speaker:
		'M26 10h28a4 4 0 0 1 4 4v52a4 4 0 0 1-4 4H26a4 4 0 0 1-4-4V14a4 4 0 0 1 4-4Zm14 8a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 15a14 14 0 1 0 0 28 14 14 0 0 0 0-28Zm0 8a6 6 0 1 1 0 12 6 6 0 0 1 0-12Z',
};

const Picture = ( { shape } ) => (
	<svg
		className="oha-deals-image"
		viewBox="0 0 80 80"
		width="160"
		height="160"
		aria-hidden="true"
	>
		<path fill="#394150" fillRule="evenodd" d={ SHAPES[ shape ] } />
	</svg>
);

const Amount = ( { children } ) => (
	<strong className="oha-deals-amount">{ children }</strong>
);

const Before = ( { children } ) => (
	<span className="oha-deals-before">
		<s>{ children }</s>
	</span>
);

const Discount = ( { children, className = '' } ) => (
	<span
		className={ `oha-deals-badge oha-deals-badge--discount ${ className }` }
	>
		{ children }
	</span>
);

const Button = ( { variant = 'primary', children } ) => (
	<span
		className={ `oha-deals-button oha-deals-button--small oha-deals-button--${ variant }` }
	>
		{ children }
	</span>
);

// The names are sample data and stay untranslated; the labels are the ones the templates use.
const label = {
	best: () => __( 'Best price', 'oha-deals' ),
	shop: ( name ) =>
		/* translators: %s: shop name. */
		sprintf( __( 'at %s', 'oha-deals' ), name ),
	to: () => __( 'To the shop', 'oha-deals' ),
	free: () => __( 'Free shipping', 'oha-deals' ),
	lowest: () => __( 'Lowest price ever', 'oha-deals' ),
};

const DEALS = [
	[ 'headphones', 'Studio Headphones', '€ 79', '€ 119', '−34%' ],
	[ 'watch', 'Fitness Watch', '€ 129', '€ 169', '−24%' ],
	[ 'speaker', 'Bluetooth Speaker', '€ 49', '€ 69', '−29%' ],
];

export function ProductExample( { layout = 'card' } ) {
	if ( layout === 'inline' ) {
		return (
			<>
				<span className="oha-deals-inline__link">
					<u>Studio Headphones</u>
				</span>{ ' ' }
				<span className="oha-deals-inline__price">
					{ sprintf(
						/* translators: 1: price, 2: shop name. */
						__( 'from %1$s at %2$s', 'oha-deals' ),
						'€ 79',
						'Kite Store'
					) }
				</span>
			</>
		);
	}

	return (
		<>
			<div className="oha-deals-product__body">
				<div className="oha-deals-product__media">
					<Picture shape="headphones" />
				</div>
				<div className="oha-deals-product__main">
					<div className="oha-deals-product__head">
						<p className="oha-deals-product__brand">Acme Audio</p>
						<h3 className="oha-deals-product__title">
							Studio Headphones
						</h3>
					</div>
					<div className="oha-deals-product__buy">
						<div className="oha-deals-price-block">
							<p className="oha-deals-price-block__line">
								<Amount>€ 79</Amount>
								<Discount>−34%</Discount>
							</p>
							<p className="oha-deals-price-block__shop">
								{ label.shop( 'Kite Store' ) }
							</p>
						</div>
						<p className="oha-deals-product__action">
							<Button>
								{ __( 'View best price', 'oha-deals' ) }
							</Button>
						</p>
					</div>
				</div>
			</div>
			{ layout === 'card' && (
				<ul className="oha-deals-offer-list">
					{ [
						[ 'Kite Store', '€ 79' ],
						[ 'Harbor Tech', '€ 82' ],
					].map( ( [ shop, price ] ) => (
						<li className="oha-deals-offer-list__item" key={ shop }>
							<span className="oha-deals-offer-list__shop">
								{ shop }
							</span>
							<span className="oha-deals-offer-list__price">
								<Amount>{ price }</Amount>
								<small className="oha-deals-offer-list__shipping">
									{ label.free() }
								</small>
							</span>
							<span className="oha-deals-offer-list__link">
								<u>{ label.to() }</u>
							</span>
						</li>
					) ) }
				</ul>
			) }
		</>
	);
}

export function OffersExample() {
	return (
		<table className="oha-deals-table oha-deals-offers-table">
			<caption className="oha-deals-table__caption">
				{ sprintf(
					/* translators: %s: product name. */
					__( 'Prices for %s', 'oha-deals' ),
					'Studio Headphones'
				) }
			</caption>
			<tbody>
				{ [
					[ 'Kite Store', '€ 79' ],
					[ 'Harbor Tech', '€ 82' ],
					[ 'North Shop', '€ 85' ],
				].map( ( [ shop, price ], index ) => (
					<tr
						key={ shop }
						className={
							'oha-deals-offers-table__row' +
							( index === 0
								? ' oha-deals-offers-table__row--best'
								: '' )
						}
					>
						<th
							scope="row"
							className="oha-deals-offers-table__shop"
						>
							<span className="oha-deals-offers-table__name">
								{ shop }
							</span>
							{ index === 0 && (
								<span className="oha-deals-badge oha-deals-badge--best">
									{ label.best() }
								</span>
							) }
							<span className="oha-deals-stock oha-deals-stock--in_stock">
								{ __( 'In stock', 'oha-deals' ) }
							</span>
						</th>
						<td className="oha-deals-offers-table__price">
							<Amount>{ price }</Amount>
							<small className="oha-deals-offers-table__shipping">
								{ label.free() }
							</small>
						</td>
						<td className="oha-deals-offers-table__action">
							<Button
								variant={
									index === 0 ? 'primary' : 'secondary'
								}
							>
								{ label.to() }
							</Button>
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

export function DealsExample() {
	return (
		<ul
			className="oha-deals-grid"
			style={ { '--oha-deals-columns': DEALS.length } }
		>
			{ DEALS.map( ( [ shape, name, price, before, off ] ) => (
				<li
					className="oha-deals-grid__item oha-deals-deal"
					key={ name }
				>
					<div className="oha-deals-deal__media">
						<Picture shape={ shape } />
						<Discount className="oha-deals-deal__discount">
							{ off }
						</Discount>
					</div>
					<div className="oha-deals-deal__body">
						<p className="oha-deals-deal__kind oha-deals-deal__kind--low">
							{ label.lowest() }
						</p>
						<h3 className="oha-deals-deal__title">{ name }</h3>
						<div className="oha-deals-deal__buy">
							<p className="oha-deals-deal__price">
								<Amount>{ price }</Amount>
								<Before>{ before }</Before>
							</p>
						</div>
					</div>
				</li>
			) ) }
		</ul>
	);
}

export function HistoryExample() {
	const line =
		'0,52 180,52 180,78 390,78 390,40 560,40 560,104 800,104 800,88 1000,88';

	return (
		<>
			<div className="oha-deals-history__header">
				<p className="oha-deals-history__heading">
					{ sprintf(
						/* translators: %s: product name. */
						__( 'Price history of %s', 'oha-deals' ),
						'Studio Headphones'
					) }
				</p>
			</div>
			<figure className="oha-deals-chart">
				<div
					className="oha-deals-chart__frame"
					style={ { '--oha-deals-chart-height': '150px' } }
				>
					<div className="oha-deals-chart__y">
						{ [ '€ 120', '€ 100', '€ 80' ].map( ( text, index ) => (
							<span
								key={ text }
								className="oha-deals-chart__label"
								style={ { '--y': `${ index * 50 }%` } }
							>
								{ text }
							</span>
						) ) }
					</div>
					<div className="oha-deals-chart__plot">
						<svg
							className="oha-deals-chart__svg"
							viewBox="0 0 1000 150"
							preserveAspectRatio="none"
							aria-hidden="true"
						>
							{ [ 0, 75, 150 ].map( ( y ) => (
								<line
									key={ y }
									className="oha-deals-chart__grid"
									x1="0"
									x2="1000"
									y1={ y }
									y2={ y }
								/>
							) ) }
							<polygon
								className="oha-deals-chart__area"
								points={ `0,150 ${ line } 1000,150` }
							/>
							<polyline
								className="oha-deals-chart__line"
								fill="none"
								points={ line }
							/>
						</svg>
						<span
							className="oha-deals-chart__marker oha-deals-chart__marker--low"
							style={ { '--x': '68%', '--y': '69.3%' } }
						>
							<span className="oha-deals-chart__marker-text">
								€ 89
							</span>
						</span>
						<span
							className="oha-deals-chart__marker oha-deals-chart__marker--now"
							style={ { '--x': '100%', '--y': '58.6%' } }
						></span>
					</div>
				</div>
			</figure>
		</>
	);
}

export function CouponsExample() {
	return (
		<ul className="oha-deals-coupons__list">
			{ [
				[ 'Kite Store', '10 %', 'SAVE10' ],
				[ 'Harbor Tech', '€ 5', 'STORAGE5' ],
			].map( ( [ shop, title, code ] ) => (
				<li className="oha-deals-coupon" key={ code }>
					<div className="oha-deals-coupon__body">
						<p className="oha-deals-coupon__shop">{ shop }</p>
						<h3 className="oha-deals-coupon__title">
							{ sprintf(
								/* translators: %s: amount, e.g. "10 %". */
								__( '%s off your order', 'oha-deals' ),
								title
							) }
						</h3>
					</div>
					<div className="oha-deals-coupon__stub">
						<div className="oha-deals-coupon__chip">
							<code className="oha-deals-coupon__code">
								{ code }
							</code>
						</div>
						<p className="oha-deals-coupon__action">
							<Button>
								{ sprintf(
									/* translators: %s: shop name. */
									__( 'Redeem at %s', 'oha-deals' ),
									shop
								) }
							</Button>
						</p>
					</div>
				</li>
			) ) }
		</ul>
	);
}

export function ComparisonExample( { orientation = 'columns' } ) {
	const head = ( [ shape, name ], index ) => (
		<span className="oha-deals-comparison__product">
			<span className="oha-deals-comparison__media">
				<Picture shape={ shape } />
			</span>
			<span className="oha-deals-comparison__text">
				<span className="oha-deals-comparison__name">{ name }</span>
				{ index === 2 && (
					<span className="oha-deals-badge oha-deals-badge--best">
						{ label.best() }
					</span>
				) }
			</span>
		</span>
	);

	if ( orientation === 'rows' ) {
		return (
			<table className="oha-deals-table oha-deals-comparison__table oha-deals-comparison__table--rows">
				<tbody>
					{ DEALS.map( ( deal, index ) => (
						<tr
							key={ deal[ 1 ] }
							className="oha-deals-comparison__row"
						>
							<td className="oha-deals-comparison__rank">
								<span className="oha-deals-comparison__number">
									{ index + 1 }
								</span>
							</td>
							<th
								scope="row"
								className="oha-deals-comparison__head"
							>
								{ head( deal ) }
							</th>
							<td className="oha-deals-comparison__cell oha-deals-comparison__cell--price">
								<Amount>{ deal[ 2 ] }</Amount>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		);
	}

	return (
		<table
			className="oha-deals-table oha-deals-comparison__table oha-deals-comparison__table--columns"
			style={ { '--oha-deals-products': DEALS.length } }
		>
			<thead>
				<tr>
					<td className="oha-deals-comparison__corner"></td>
					{ DEALS.map( ( deal, index ) => (
						<th
							key={ deal[ 1 ] }
							scope="col"
							className={
								'oha-deals-comparison__head' +
								( index === 2 ? ' is-best' : '' )
							}
						>
							{ head( deal, index ) }
						</th>
					) ) }
				</tr>
			</thead>
			<tbody>
				<tr>
					<th scope="row" className="oha-deals-comparison__attribute">
						{ label.best() }
					</th>
					{ DEALS.map( ( deal, index ) => (
						<td
							key={ deal[ 1 ] }
							className={
								'oha-deals-comparison__cell oha-deals-comparison__cell--price' +
								( index === 2 ? ' is-best' : '' )
							}
						>
							<Amount>{ deal[ 2 ] }</Amount>
						</td>
					) ) }
				</tr>
			</tbody>
		</table>
	);
}
