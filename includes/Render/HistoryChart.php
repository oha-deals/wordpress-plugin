<?php
/**
 * Server-rendered price history chart.
 *
 * @package OhaDeals
 */

declare( strict_types=1 );

namespace OhaDeals\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Draws price history as an inline SVG step chart plus a data table. Complete without JavaScript; the Interactivity
 * API module of the price history block adds a readout (pointer, touch, keyboard) on top.
 *
 * The API sends change points only (a price holds until the next point) plus one point from before the window.
 * So the x axis is time, the first point is clamped to the window start, the last price is extended to today and
 * the line is drawn as steps.
 *
 * The SVG holds lines and areas only and stretches to its container. Axis labels, markers and the readout are HTML
 * placed in percent, so text keeps its size in narrow containers. The time axis runs from left to right in every
 * language.
 *
 * The SVG is labelled through <title> and <desc> (lowest, highest and latest price); the change points are available
 * in a <details> table for screen readers, keyboard users and anyone who wants the numbers.
 */
final class HistoryChart {

	/**
	 * ViewBox width. The SVG stretches to its container.
	 */
	const WIDTH = 1000;

	/**
	 * Script module with the readout: the id core generates for `viewScriptModule` of oha-deals/price-history.
	 */
	const MODULE_ID = 'oha-deals-price-history-view-script-module';

	/**
	 * Windows longer than this many days label the x axis with month and year instead of month and day.
	 */
	const LONG_WINDOW = 200;

	/**
	 * Formatter.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	/**
	 * Today's date (Y-m-d) in the site time zone; empty means "ask WordPress when rendering".
	 *
	 * @var string
	 */
	private string $today;

	/**
	 * Constructor.
	 *
	 * @param Formatter $formatter Formatter.
	 * @param string    $today     Today's date (Y-m-d); empty uses the current date in the site time zone.
	 */
	public function __construct( Formatter $formatter, string $today = '' ) {
		$this->formatter = $formatter;
		$this->today     = $today;
	}

	/**
	 * Renders the chart.
	 *
	 * @param array<int, array{date: string, price: float, avg: float|null}> $points   Change points, oldest first.
	 * @param string                                                         $label    Accessible name, e.g. the product name.
	 * @param int                                                            $height   Height of the plot in pixels (120–600).
	 * @param string                                                         $currency Currency code.
	 * @param int                                                            $days     Window length in days.
	 * @return string HTML, every dynamic part escaped. Empty when there are no points.
	 */
	public function render( array $points, string $label, int $height = 220, string $currency = 'EUR', int $days = 90 ): string {
		$today  = '' !== $this->today ? $this->today : (string) wp_date( 'Y-m-d' );
		$days   = max( 1, $days );
		$start  = gmdate( 'Y-m-d', self::time( $today ) - $days * DAY_IN_SECONDS );
		$points = self::window( $points, $start, $today );
		$count  = count( $points );
		if ( 0 === $count ) {
			return '';
		}

		// Shortcodes and product cards print the chart outside the price history block.
		wp_enqueue_script_module( self::MODULE_ID );

		$height   = max( 120, min( 600, $height ) );
		$id       = wp_unique_id( 'oha-deals-chart-' );
		$prices   = array_column( $points, 'price' );
		$averages = array_filter(
			array_column( $points, 'avg' ),
			static function ( $value ): bool {
				return null !== $value;
			}
		);
		$all      = array_merge( $prices, $averages );
		$lowest   = (float) min( $prices );
		$highest  = (float) max( $prices );
		$latest   = (float) $points[ $count - 1 ]['price'];
		$ticks    = self::ticks( (float) min( $all ), (float) max( $all ), $height < 180 ? 2 : 3 );
		$min      = $ticks[0];
		$max      = $ticks[ count( $ticks ) - 1 ];

		$start_time = self::time( $start );
		$span       = max( 1, self::time( $today ) - $start_time );

		$x = static function ( string $date ) use ( $start_time, $span ): float {
			return self::WIDTH * ( self::time( $date ) - $start_time ) / $span;
		};
		$y = static function ( float $price ) use ( $min, $max, $height ): float {
			return $height * ( 1 - ( $price - $min ) / ( $max - $min ) );
		};

		$price_line = self::steps( $points, 'price', $today, $x, $y );
		$avg_line   = self::steps( $points, 'avg', $today, $x, $y );
		$segments   = $this->segments( $points, $today, $x, $y, $height, $currency );

		$title = sprintf(
			/* translators: %s: product name. */
			__( 'Price history of %s', 'oha-deals' ),
			$label
		);
		if ( 1 === $count ) {
			$desc = sprintf(
				/* translators: 1: price, 2: date. */
				__( 'The price has been %1$s since %2$s.', 'oha-deals' ),
				$this->formatter->money( $latest, $currency ),
				$this->formatter->date( $points[0]['date'] )
			);
		} else {
			$desc = sprintf(
				/* translators: 1: start date, 2: end date, 3: lowest price, 4: highest price, 5: latest price. */
				__( 'From %1$s to %2$s. Lowest price %3$s, highest price %4$s, latest price %5$s.', 'oha-deals' ),
				$this->formatter->date( $start ),
				$this->formatter->date( $today ),
				$this->formatter->money( $lowest, $currency ),
				$this->formatter->money( $highest, $currency ),
				$this->formatter->money( $latest, $currency )
			);
		}

		// The presentation attributes are the fallback without the stylesheet (feeds, e-mails).
		$svg  = sprintf(
			'<svg class="oha-deals-chart__svg" viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d" preserveAspectRatio="none" role="img" aria-labelledby="%3$s-title %3$s-desc" focusable="false">',
			self::WIDTH,
			$height,
			esc_attr( $id )
		);
		$svg .= '<title id="' . esc_attr( $id . '-title' ) . '">' . esc_html( $title ) . '</title>';
		$svg .= '<desc id="' . esc_attr( $id . '-desc' ) . '">' . esc_html( $desc ) . '</desc>';
		foreach ( $ticks as $tick ) {
			$line_y = self::coord( $y( $tick ) );
			$svg   .= '<line class="oha-deals-chart__grid" x1="0" x2="' . esc_attr( (string) self::WIDTH ) . '" y1="' . esc_attr( $line_y ) . '" y2="' . esc_attr( $line_y ) . '" stroke="currentColor" stroke-opacity="0.2" />';
		}
		$first_x = strtok( $price_line[0], ',' );
		$last_x  = strtok( $price_line[ count( $price_line ) - 1 ], ',' );
		$svg    .= '<polygon class="oha-deals-chart__area" fill="none" points="' . esc_attr( $first_x . ',' . $height . ' ' . implode( ' ', $price_line ) . ' ' . $last_x . ',' . $height ) . '" />';
		if ( count( $avg_line ) > 1 ) {
			$svg .= '<polyline class="oha-deals-chart__avg" fill="none" stroke="currentColor" stroke-dasharray="6 4" points="' . esc_attr( implode( ' ', $avg_line ) ) . '" />';
		}
		$svg .= '<polyline class="oha-deals-chart__line" fill="none" stroke="currentColor" stroke-width="2" points="' . esc_attr( implode( ' ', $price_line ) ) . '" />';
		$svg .= '</svg>';

		$y_labels = '';
		foreach ( $ticks as $tick ) {
			$y_labels .= '<span class="oha-deals-chart__label" style="' . esc_attr( '--y:' . self::percent( $y( $tick ) / $height * 100 ) ) . '">' . esc_html( $this->formatter->money( $tick, $currency ) ) . '</span>';
		}

		$plot = $svg;
		if ( $highest > $lowest ) {
			$plot .= $this->marker( 'high', $highest, $points, $segments, $currency, $y, $height ) . $this->marker( 'low', $lowest, $points, $segments, $currency, $y, $height );
		}
		$plot .= '<span class="oha-deals-chart__marker oha-deals-chart__marker--now" style="' . esc_attr( self::position( 100.0, $segments[ $count - 1 ]['y'] ) ) . '" aria-hidden="true"></span>';
		$plot .= $this->readout( $segments );

		$legend = '';
		if ( count( $avg_line ) > 1 ) {
			$legend = '<ul class="oha-deals-chart__legend">'
				. '<li class="oha-deals-chart__key oha-deals-chart__key--price">' . esc_html__( 'Best price', 'oha-deals' ) . '</li>'
				. '<li class="oha-deals-chart__key oha-deals-chart__key--avg">' . esc_html__( 'Average price', 'oha-deals' ) . '</li>'
				. '</ul>';
		}

		return '<figure class="oha-deals-chart">'
			. $this->stats( $latest, $lowest, $highest, $currency )
			. '<div class="oha-deals-chart__frame" style="' . esc_attr( '--oha-deals-chart-height:' . $height . 'px' ) . '">'
			. '<div class="oha-deals-chart__y" aria-hidden="true">' . $y_labels . '</div>'
			. '<div class="oha-deals-chart__plot">' . $plot . '</div>'
			. $this->x_axis( $start, $days )
			. '</div>'
			. $legend
			. $this->table( $points, $title, $currency )
			. '</figure>';
	}

	/**
	 * Current, lowest and highest price of the window. With a price that never changed, only the current one.
	 *
	 * @param float  $latest   Current price.
	 * @param float  $lowest   Lowest price in the window.
	 * @param float  $highest  Highest price in the window.
	 * @param string $currency Currency code.
	 * @return string
	 */
	private function stats( float $latest, float $lowest, float $highest, string $currency ): string {
		$stats = array( 'now' => array( __( 'Current price', 'oha-deals' ), $latest ) );
		if ( $highest > $lowest ) {
			$stats['low']  = array( __( 'Lowest price', 'oha-deals' ), $lowest );
			$stats['high'] = array( __( 'Highest price', 'oha-deals' ), $highest );
		}

		$html = '';
		foreach ( $stats as $name => $stat ) {
			$html .= '<div class="' . esc_attr( 'oha-deals-chart__stat oha-deals-chart__stat--' . $name ) . '">'
				. '<dt>' . esc_html( $stat[0] ) . '</dt>'
				. '<dd>' . esc_html( $this->formatter->money( $stat[1], $currency ) ) . '</dd>'
				. '</div>';
		}

		return '<dl class="oha-deals-chart__stats">' . $html . '</dl>';
	}

	/**
	 * The time axis: four evenly spaced dates, hidden from assistive technology (the description and the table carry
	 * the full dates).
	 *
	 * @param string $start Window start (Y-m-d).
	 * @param int    $days  Window length in days.
	 * @return string
	 */
	private function x_axis( string $start, int $days ): string {
		if ( $days > self::LONG_WINDOW ) {
			/* translators: PHP date format (month and year) for the price chart axis, see https://www.php.net/manual/datetime.format.php */
			$format = _x( 'M Y', 'price chart axis', 'oha-deals' );
		} else {
			/* translators: PHP date format (month and day) for the price chart axis, see https://www.php.net/manual/datetime.format.php */
			$format = _x( 'M j', 'price chart axis', 'oha-deals' );
		}

		$html = '';
		foreach ( array( 0, 1, 2, 3 ) as $step ) {
			$time  = self::time( $start ) + (int) round( $days * $step / 3 ) * DAY_IN_SECONDS;
			$html .= '<span class="oha-deals-chart__label">' . esc_html( (string) wp_date( $format, $time ) ) . '</span>';
		}

		return '<div class="oha-deals-chart__x" aria-hidden="true">' . $html . '</div>';
	}

	/**
	 * A marker with the price, on the longest stretch at the lowest or highest price.
	 *
	 * @param string                                                                         $type     `low` or `high`.
	 * @param float                                                                          $price    The price to mark.
	 * @param array<int, array{date: string, price: float, avg: float|null}>                 $points   Change points inside the window.
	 * @param array<int, array{x: float, end: float, y: float, date: string, price: string}> $segments Segments of the same points.
	 * @param string                                                                         $currency Currency code.
	 * @param callable(float): float                                                         $y        Price to y.
	 * @param int                                                                            $height   Plot height.
	 * @return string
	 */
	private function marker( string $type, float $price, array $points, array $segments, string $currency, callable $y, int $height ): string {
		$index  = 0;
		$length = -1.0;
		foreach ( $points as $i => $point ) {
			$candidate = $segments[ $i ]['end'] - $segments[ $i ]['x'];
			if ( (float) $point['price'] === $price && $candidate > $length ) {
				$index  = $i;
				$length = $candidate;
			}
		}

		// The label of the highest price sits above the line, so it makes room for the average line there.
		$top     = 'high' === $type ? max( $price, (float) $points[ $index ]['avg'] ) : $price;
		$classes = 'oha-deals-chart__marker oha-deals-chart__marker--' . $type;
		if ( count( $points ) - 1 === $index ) {
			// Today's price has its own dot.
			$classes .= ' oha-deals-chart__marker--current';
		}

		return '<span class="' . esc_attr( $classes ) . '" style="' . esc_attr( self::position( ( $segments[ $index ]['x'] + $segments[ $index ]['end'] ) / 2, $y( $top ) / $height * 100 ) ) . '" aria-hidden="true">'
			. '<span class="oha-deals-chart__marker-text">' . esc_html( $this->formatter->money( $price, $currency ) ) . '</span>'
			. '</span>';
	}

	/**
	 * The stretches of constant price, positioned in percent of the plot, with display texts for the readout.
	 *
	 * @param array<int, array{date: string, price: float, avg: float|null}> $points   Change points inside the window.
	 * @param string                                                         $today    Today (Y-m-d).
	 * @param callable(string): float                                        $x        Date to x.
	 * @param callable(float): float                                         $y        Price to y.
	 * @param int                                                            $height   Plot height.
	 * @param string                                                         $currency Currency code.
	 * @return array<int, array{x: float, end: float, y: float, date: string, price: string}>
	 */
	private function segments( array $points, string $today, callable $x, callable $y, int $height, string $currency ): array {
		$segments = array();
		$count    = count( $points );
		foreach ( $points as $i => $point ) {
			$last = $i + 1 === $count;
			$next = $last ? $today : $points[ $i + 1 ]['date'];
			// A price holds until the day before the next change, the last one until today.
			$until = $last ? $today : gmdate( 'Y-m-d', self::time( $next ) - DAY_IN_SECONDS );
			$date  = $this->formatter->date( $point['date'] );
			if ( $until > $point['date'] ) {
				/* translators: 1: start date, 2: end date. */
				$date = sprintf( __( '%1$s – %2$s', 'oha-deals' ), $date, $this->formatter->date( $until ) );
			}

			$segments[] = array(
				'x'     => round( $x( $point['date'] ) / self::WIDTH * 100, 2 ),
				'end'   => round( $x( $next ) / self::WIDTH * 100, 2 ),
				'y'     => round( $y( (float) $point['price'] ) / $height * 100, 2 ),
				'date'  => $date,
				'price' => $this->formatter->money( (float) $point['price'], $currency ),
			);
		}

		return $segments;
	}

	/**
	 * The readout for the Interactivity API module: hidden until the module runs, then a slider over the plot that
	 * pointer, touch and arrow keys move along the line. The printed state matches the initial context, so the
	 * directives need no server-side processing (shortcodes).
	 *
	 * @param array<int, array{x: float, end: float, y: float, date: string, price: string}> $segments Segments, at least one.
	 * @return string
	 */
	private function readout( array $segments ): string {
		$last    = count( $segments ) - 1;
		$current = $segments[ $last ];
		$style   = self::position( 100.0, $current['y'] );
		$text    = $current['date'] . ', ' . $current['price'];
		$context = wp_interactivity_data_wp_context(
			array(
				'segments' => $segments,
				'ready'    => false,
				'active'   => false,
				'index'    => $last,
				'style'    => $style,
				'date'     => $current['date'],
				'price'    => $current['price'],
				'text'     => $text,
			)
		);

		return '<div class="oha-deals-chart__readout" data-wp-interactive="oha-deals/price-history" ' . $context
			. ' role="slider" tabindex="0" hidden'
			. ' aria-label="' . esc_attr__( 'Price by date', 'oha-deals' ) . '"'
			. ' aria-valuemin="0" aria-valuemax="' . esc_attr( (string) $last ) . '" aria-valuenow="' . esc_attr( (string) $last ) . '"'
			. ' aria-valuetext="' . esc_attr( $text ) . '"'
			. ' style="' . esc_attr( $style ) . '"'
			. ' data-wp-init="callbacks.init"'
			. ' data-wp-bind--hidden="!context.ready"'
			. ' data-wp-bind--style="context.style"'
			. ' data-wp-bind--aria-valuenow="context.index"'
			. ' data-wp-bind--aria-valuetext="context.text"'
			. ' data-wp-class--is-active="context.active"'
			. ' data-wp-on--pointerdown="actions.point"'
			. ' data-wp-on--pointermove="actions.point"'
			. ' data-wp-on--pointerleave="actions.leave"'
			. ' data-wp-on--focus="actions.show"'
			. ' data-wp-on--blur="actions.hide"'
			. ' data-wp-on--keydown="actions.key"'
			. '>'
			. '<span class="oha-deals-chart__cursor"></span>'
			. '<span class="oha-deals-chart__dot"></span>'
			. '<span class="oha-deals-chart__tooltip" aria-hidden="true">'
			. '<span class="oha-deals-chart__tooltip-date" data-wp-text="context.date">' . esc_html( $current['date'] ) . '</span>'
			. '<strong class="oha-deals-chart__tooltip-price" data-wp-text="context.price">' . esc_html( $current['price'] ) . '</strong>'
			. '</span>'
			. '</div>';
	}

	/**
	 * Round axis values that enclose the prices with some headroom, lowest first.
	 *
	 * @param float $min       Lowest value.
	 * @param float $max       Highest value.
	 * @param int   $intervals Wanted number of intervals; rounding can add up to two.
	 * @return float[] At least two values.
	 */
	private static function ticks( float $min, float $max, int $intervals ): array {
		// Leave headroom for the marker labels, and so that a flat line does not sit on an edge.
		$spread = $max - $min;
		if ( $spread < 0.01 ) {
			$spread = max( 1.0, $max * 0.1 );
			$min   -= $spread / 2;
			$max   += $spread / 2;
		} else {
			$min -= $spread * 0.12;
			$max += $spread * 0.12;
		}
		$min = max( 0.0, $min );

		$raw       = ( $max - $min ) / $intervals;
		$magnitude = pow( 10, floor( log10( $raw ) ) );
		$step      = 10 * $magnitude;
		foreach ( array( 1, 2, 2.5, 5 ) as $factor ) {
			if ( $factor * $magnitude >= $raw ) {
				$step = $factor * $magnitude;
				break;
			}
		}

		$ticks = array();
		$from  = (int) floor( $min / $step );
		$to    = max( $from + 1, (int) ceil( $max / $step ) );
		for ( $i = $from; $i <= $to; $i++ ) {
			$ticks[] = round( $i * $step, 4 );
		}

		return $ticks;
	}

	/**
	 * Keeps the points inside the window: the last point before the window start is moved to the start (its price
	 * was valid then), earlier ones are dropped, and dates after today are clamped to today.
	 *
	 * @param array<int, array{date: string, price: float, avg: float|null}> $points Change points.
	 * @param string                                                         $start  Window start (Y-m-d).
	 * @param string                                                         $today  Today (Y-m-d).
	 * @return array<int, array{date: string, price: float, avg: float|null}>
	 */
	private static function window( array $points, string $start, string $today ): array {
		usort(
			$points,
			static function ( array $a, array $b ): int {
				return strcmp( $a['date'], $b['date'] );
			}
		);

		$before = null;
		$inside = array();
		foreach ( $points as $point ) {
			$date = substr( $point['date'], 0, 10 );
			if ( $date < $start ) {
				$before = $point;
				continue;
			}
			$point['date'] = min( $date, $today );
			$inside[]      = $point;
		}

		if ( null !== $before && ( ! $inside || $inside[0]['date'] > $start ) ) {
			$before['date'] = $start;
			array_unshift( $inside, $before );
		}

		return $inside;
	}

	/**
	 * Polyline coordinates of a step line: each value holds until the next point, the last one until today.
	 *
	 * @param array<int, array{date: string, price: float, avg: float|null}> $points Change points inside the window.
	 * @param string                                                         $field  `price` or `avg`.
	 * @param string                                                         $today  Today (Y-m-d).
	 * @param callable(string): float                                        $x      Date to x.
	 * @param callable(float): float                                         $y      Price to y.
	 * @return string[] "x,y" pairs; empty when the field has no values.
	 */
	private static function steps( array $points, string $field, string $today, callable $x, callable $y ): array {
		$coords = array();
		$last_y = null;
		foreach ( $points as $point ) {
			if ( null === $point[ $field ] ) {
				continue;
			}
			$point_x = self::coord( $x( $point['date'] ) );
			if ( null !== $last_y ) {
				$coords[] = $point_x . ',' . $last_y;
			}
			$last_y   = self::coord( $y( (float) $point[ $field ] ) );
			$coords[] = $point_x . ',' . $last_y;
		}
		if ( null !== $last_y ) {
			$coords[] = self::coord( $x( $today ) ) . ',' . $last_y;
		}

		// Points on the same day collapse into one coordinate.
		$unique = array();
		foreach ( $coords as $coord ) {
			if ( end( $unique ) !== $coord ) {
				$unique[] = $coord;
			}
		}

		return $unique;
	}

	/**
	 * Unix time of a date at noon UTC (keeps day arithmetic free of time zone and DST effects).
	 *
	 * @param string $date Date (Y-m-d).
	 * @return int
	 */
	private static function time( string $date ): int {
		return (int) strtotime( substr( $date, 0, 10 ) . ' 12:00:00 UTC' );
	}

	/**
	 * The data table behind the chart, collapsed in a <details> element.
	 *
	 * @param array<int, array{date: string, price: float, avg: float|null}> $points   History.
	 * @param string                                                         $caption  Table caption.
	 * @param string                                                         $currency Currency code.
	 * @return string
	 */
	private function table( array $points, string $caption, string $currency ): string {
		$has_avg = false;
		foreach ( $points as $point ) {
			if ( null !== $point['avg'] ) {
				$has_avg = true;
				break;
			}
		}

		$rows = '';
		foreach ( array_reverse( $points ) as $point ) {
			$rows .= '<tr><th scope="row">' . esc_html( $this->formatter->date( $point['date'] ) ) . '</th>'
				. '<td>' . esc_html( $this->formatter->money( (float) $point['price'], $currency ) ) . '</td>';
			if ( $has_avg ) {
				$rows .= '<td>' . ( null === $point['avg'] ? '' : esc_html( $this->formatter->money( (float) $point['avg'], $currency ) ) ) . '</td>';
			}
			$rows .= '</tr>';
		}

		return '<details class="oha-deals-chart__data"><summary>' . esc_html__( 'Show price data', 'oha-deals' ) . '</summary>'
			. '<div class="oha-deals-table-scroll"><table class="oha-deals-table">'
			. '<caption>' . esc_html( $caption ) . '</caption>'
			. '<thead><tr><th scope="col">' . esc_html__( 'Date', 'oha-deals' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Best price', 'oha-deals' ) . '</th>'
			. ( $has_avg ? '<th scope="col">' . esc_html__( 'Average price', 'oha-deals' ) . '</th>' : '' )
			. '</tr></thead><tbody>' . $rows . '</tbody></table></div></details>';
	}

	/**
	 * The CSS custom properties that place a marker.
	 *
	 * @param float $x Horizontal position in percent.
	 * @param float $y Vertical position in percent.
	 * @return string
	 */
	private static function position( float $x, float $y ): string {
		return '--x:' . self::percent( $x ) . ';--y:' . self::percent( $y );
	}

	/**
	 * A CSS percentage, independent of the locale.
	 *
	 * @param float $value Percentage.
	 * @return string
	 */
	private static function percent( float $value ): string {
		return number_format( $value, 2, '.', '' ) . '%';
	}

	/**
	 * Formats a coordinate with one decimal and a dot separator, independent of the locale.
	 *
	 * @param float $value Coordinate.
	 * @return string
	 */
	private static function coord( float $value ): string {
		return number_format( $value, 1, '.', '' );
	}
}
