<?php
/**
 * Plugin Name: oha.deals demo data
 * Description: Answers the plugin's API requests with invented products. For screenshots and the live preview only; not part of the released plugin.
 * License:     GPL-2.0-or-later
 *
 * Drop this file into wp-content/mu-plugins/. No API key and no network access to oha.deals are needed.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Demo;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'OHA_DEALS_API_KEY' ) ) {
	define( 'OHA_DEALS_API_KEY', 'oha_test_00000000000000000000000000000000' );
}

const API    = 'https://oha.deals/api/partner/v1';
const IMAGES = 'https://raw.githubusercontent.com/oha-deals/wordpress-plugin/main/demo/images/';

/**
 * The invented catalogue: slug => name, brand, category, spec, price, before, all-time low, image.
 *
 * @return array<string, array<string, mixed>>
 */
function catalogue(): array {
	return array(
		'wireless-studio-headphones' => array( 'Wireless Studio Headphones', 'Acme Audio', 'Headphones', 'Over-ear · Noise cancelling · 40 h battery', 179.0, 229.0, true, 'headphones' ),
		'four-bay-home-server'       => array( 'Four-Bay Home Server', 'Acme Storage', 'Network storage', '4 bays · 8 GB RAM · 2.5 GbE', 449.0, 529.0, false, 'nas' ),
		'automatic-espresso-machine' => array( 'Automatic Espresso Machine', 'Acme Home', 'Coffee machines', 'Bean to cup · Milk frother · 1.8 l', 389.0, 469.0, true, 'coffee' ),
		'robot-vacuum-cleaner'       => array( 'Robot Vacuum Cleaner', 'Acme Home', 'Vacuum cleaners', 'Laser navigation · Auto-empty dock', 299.0, 399.0, false, 'vacuum' ),
		'fitness-smartwatch'         => array( 'Fitness Smartwatch', 'Acme Wear', 'Smartwatches', 'AMOLED · GPS · 10 day battery', 129.0, 169.0, true, 'watch' ),
		'led-desk-lamp'              => array( 'LED Desk Lamp', 'Acme Light', 'Desk lamps', 'Dimmable · 2700–6500 K · USB-C', 39.9, 54.9, false, 'lamp' ),
		'usb-c-charger-set'          => array( 'USB-C Charger Set 65 W', 'Acme Power', 'Chargers', '65 W · GaN · 1 m cable', 24.9, 34.9, true, 'charger' ),
		'e-book-reader'              => array( 'E-Book Reader 7"', 'Acme Read', 'E-book readers', '7 inch · Warm light · Waterproof', 119.0, 149.0, false, 'reader' ),
		'wireless-home-headphones'   => array( 'Wireless Home Headphones', 'Acme Audio', 'Headphones', 'Over-ear · 60 h battery', 139.0, 159.0, false, 'headphones-white' ),
		'wireless-travel-headphones' => array( 'Wireless Travel Headphones', 'Acme Audio', 'Headphones', 'On-ear · Foldable · 25 h battery', 89.0, 109.0, true, 'headphones-navy' ),
	);
}

/**
 * Technical data, for the comparison table.
 *
 * @param string $slug Slug.
 * @return array<string, string>|null
 */
function attrs( string $slug ): ?array {
	$labels = array( 'Type', 'Battery life', 'Noise cancelling', 'Weight', 'Bluetooth' );
	$values = array(
		'wireless-studio-headphones' => array( 'Over-ear', '40 h', 'Adaptive', '254 g', '5.4' ),
		'wireless-home-headphones'   => array( 'Over-ear', '60 h', 'Yes', '268 g', '5.3' ),
		'wireless-travel-headphones' => array( 'On-ear', '25 h', 'No', '172 g', '5.3' ),
	);

	return isset( $values[ $slug ] ) ? array_combine( $labels, $values[ $slug ] ) : null;
}

/**
 * A product summary as the API sends it.
 *
 * @param string $slug Slug.
 * @return array<string, mixed>
 */
function summary( string $slug ): array {
	$items = catalogue();
	$index = (int) array_search( $slug, array_keys( $items ), true );

	list( $name, $brand, $category, , $price, $before, , $image ) = $items[ $slug ];

	return array(
		'id'          => 1000 + $index,
		'slug'        => $slug,
		'name'        => $name,
		'brand'       => $brand,
		'category'    => array(
			'slug' => sanitize_title( $category ),
			'name' => $category,
		),
		'ean'         => null,
		'image'       => IMAGES . $image . '.webp',
		'price'       => $price,
		'priceHidden' => false,
		'currency'    => 'EUR',
		'shop'        => 'Kite Store',
		'offerCount'  => 5,
		'change'      => array(
			'd1'  => 0,
			'd7'  => -6.2,
			'd30' => -12.4,
		),
		'before'      => $before,
		'priceAt'     => null,
		'url'         => 'https://oha.deals/produkt/' . $slug,
	);
}

/**
 * Shop offers for a product.
 *
 * @param string $slug  Slug.
 * @param float  $price Best price.
 * @return array<int, array<string, mixed>>
 */
function offers( string $slug, float $price ): array {
	$shops  = array(
		array( 'Kite Store', 1.0, 0.0, 'in_stock' ),
		array( 'Harbor Tech', 1.03, 4.99, 'in_stock' ),
		array( 'Volt & Co', 1.06, 0.0, 'short' ),
		array( 'North Shop', 1.11, 5.95, 'in_stock' ),
		array( 'Pine Market', 1.17, null, 'long' ),
	);
	$offers = array();
	foreach ( $shops as $i => list( $name, $factor, $shipping, $availability ) ) {
		$amount   = round( $price * $factor, 2 );
		$offers[] = array(
			'shop'         => array(
				'id'   => 'awin-' . ( 900 + $i ),
				'name' => $name,
				'host' => sanitize_title( $name ) . '.example',
			),
			'price'        => $amount,
			'priceHidden'  => false,
			'shipping'     => $shipping,
			'total'        => null === $shipping ? null : round( $amount + $shipping, 2 ),
			'currency'     => 'EUR',
			'availability' => $availability,
			'condition'    => 'new',
			'priceAt'      => null,
			'url'          => API . '/go/ak_demo000000/' . $slug . '/awin-' . ( 900 + $i ),
			'merchant'     => array(
				'network'        => 'awin',
				'asin'           => null,
				'awinMerchantId' => 900 + $i,
				'awinProductId'  => (string) ( 40000000 + $i ),
				'url'            => null,
			),
		);
	}

	return $offers;
}

/**
 * A change-only price history that ends at today's price.
 *
 * @param float $price  Today's price.
 * @param float $before Former price.
 * @param int   $days   Range.
 * @return array<int, array<string, mixed>>
 */
function history( float $price, float $before, int $days ): array {
	$steps  = array(
		array( 1.02, 0.98 ),
		array( 0.9, 1.04 ),
		array( 0.74, 1.0 ),
		array( 0.6, 0.94 ),
		array( 0.5, 1.02 ),
		array( 0.33, 0.97 ),
		array( 0.2, 0.89 ),
		array( 0.11, 0.93 ),
	);
	$points = array();
	foreach ( $steps as list( $ago, $factor ) ) {
		$points[] = array(
			'date'  => gmdate( 'Y-m-d', time() - (int) round( $ago * $days ) * DAY_IN_SECONDS ),
			'price' => round( $before * $factor, 2 ),
			'avg'   => round( $before * $factor * 1.07, 2 ),
		);
	}
	$points[] = array(
		'date'  => gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ),
		'price' => $price,
		'avg'   => round( $price * 1.08, 2 ),
	);

	return $points;
}

/**
 * The answer to one API request.
 *
 * @param string                $path  Path below the API base.
 * @param array<string, string> $query Query arguments.
 * @return array<string, mixed>|null Null for "not found".
 */
function answer( string $path, array $query ): ?array {
	$items = catalogue();
	$slugs = array_keys( $items );

	if ( '/me' === $path ) {
		return array(
			'data' => array(
				'keyId'        => 'ak_demo000000',
				'prefix'       => 'oha_test_000',
				'name'         => 'Demo site',
				'site'         => 'demo.example',
				'tier'         => 'pro',
				'capabilities' => array(
					'amazon'   => false,
					'history'  => true,
					'ownLinks' => true,
				),
				'rateLimit'    => array(
					'perMinute' => 60,
					'perDay'    => 10000,
				),
			),
		);
	}

	if ( '/categories' === $path ) {
		$data = array();
		foreach ( $items as $item ) {
			$data[ $item[2] ] = array(
				'slug'     => sanitize_title( $item[2] ),
				'name'     => $item[2],
				'count'    => 1,
				'minPrice' => $item[4],
			);
		}

		return array( 'data' => array_values( $data ) );
	}

	if ( '/coupons' === $path ) {
		$rows = array(
			array( 'Kite Store', '10 € off orders over 100 €', 'For headphones and smartwatches.', 'SAVE10', 9 ),
			array( 'Harbor Tech', '5 % off network storage', 'One code per customer.', 'STORAGE5', 16 ),
			array( 'Volt & Co', 'Free shipping', 'No minimum order value.', 'SHIPFREE', 23 ),
		);
		$data = array();
		foreach ( $rows as $i => list( $shop, $title, $description, $code, $left ) ) {
			$host = sanitize_title( $shop ) . '.example';
			if ( isset( $query['host'] ) && $query['host'] !== $host ) {
				continue;
			}
			$data[] = array(
				'id'          => 7000 + $i,
				'shop'        => $shop,
				'host'        => $host,
				'title'       => $title,
				'description' => $description,
				'code'        => $code,
				'codeHidden'  => false,
				'url'         => 'https://oha.deals/g/' . ( 7000 + $i ),
				'pageUrl'     => 'https://oha.deals/gutscheine/' . sanitize_title( $shop ),
				'validUntil'  => gmdate( 'Y-m-d\T22:59:00.000\Z', time() + $left * DAY_IN_SECONDS ),
			);
		}

		return array( 'data' => array_slice( $data, 0, (int) ( $query['limit'] ?? 40 ) ) );
	}

	if ( '/deals' === $path ) {
		$mode = $query['mode'] ?? 'all';
		$data = array();
		foreach ( $slugs as $slug ) {
			$low = (bool) $items[ $slug ][6];
			if ( ( 'low' === $mode && ! $low ) || ( 'drop' === $mode && $low ) ) {
				continue;
			}
			if ( ! empty( $query['category'] ) && sanitize_title( $items[ $slug ][2] ) !== $query['category'] ) {
				continue;
			}
			$data[] = summary( $slug ) + array(
				'kind'       => $low ? 'low' : 'drop',
				'allTimeLow' => $low,
				'lastSeen'   => gmdate( 'Y-m-d' ),
			);
		}

		return array(
			'data' => array_slice( $data, 0, (int) ( $query['limit'] ?? 20 ) ),
			'meta' => array(
				'total'           => count( $data ),
				'offset'          => 0,
				'hasMore'         => false,
				'pricesCheckedAt' => gmdate( 'Y-m-d\TH:00:00.000\Z' ),
			),
		);
	}

	if ( '/products/search' === $path ) {
		$words = preg_split( '/\s+/', strtolower( trim( $query['q'] ?? '' ) ) );
		$data  = array();
		foreach ( $slugs as $slug ) {
			$haystack = strtolower( $items[ $slug ][0] . ' ' . $items[ $slug ][1] . ' ' . $items[ $slug ][2] );
			foreach ( is_array( $words ) ? $words : array() as $word ) {
				if ( '' !== $word && false === strpos( $haystack, $word ) ) {
					continue 2;
				}
			}
			$data[] = summary( $slug );
		}

		return array(
			'data' => $data,
			'meta' => array(
				'total'      => count( $data ),
				'relaxed'    => false,
				'categories' => array(),
			),
		);
	}

	if ( 0 === strpos( $path, '/products/' ) ) {
		$slug = substr( $path, strlen( '/products/' ) );
		if ( ! isset( $items[ $slug ] ) ) {
			return null;
		}
		list( , , , $spec, $price, $before ) = $items[ $slug ];

		return array(
			'data' => summary( $slug ) + array(
				'spec'            => $spec,
				'images'          => array( summary( $slug )['image'] ),
				'attrs'           => attrs( $slug ),
				'msrp'            => null,
				'low30'           => $price,
				'allTimeLow'      => array(
					'price' => $price,
					'date'  => gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ),
				),
				'offers'          => offers( $slug, (float) $price ),
				'usedOffers'      => array(),
				'pricesCheckedAt' => gmdate( 'Y-m-d\TH:00:00.000\Z' ),
				'history'         => history( (float) $price, (float) $before, (int) ( $query['days'] ?? 90 ) ),
			),
		);
	}

	return null;
}

add_filter(
	'pre_http_request',
	static function ( $response, $args, $url ) {
		if ( 0 !== strpos( (string) $url, API ) ) {
			return $response;
		}
		$query = array();
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$path = substr( (string) wp_parse_url( $url, PHP_URL_PATH ), strlen( (string) wp_parse_url( API, PHP_URL_PATH ) ) );
		$body = answer( $path, $query );
		$code = null === $body ? 404 : 200;
		if ( null === $body ) {
			$body = array(
				'error' => array(
					'code'    => 'not_found',
					'message' => 'Not found.',
					'status'  => 404,
				),
			);
		}

		return array(
			'headers'  => array(),
			'body'     => (string) wp_json_encode( $body ),
			'response' => array(
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
