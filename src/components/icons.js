import { Path, SVG } from '@wordpress/components';

/**
 * Brand colour of the block icons.
 */
const FOREGROUND = '#FF684F';

const icon = ( path ) => ( {
	foreground: FOREGROUND,
	src: (
		<SVG viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
			<Path fillRule="evenodd" clipRule="evenodd" d={ path } />
		</SVG>
	),
} );

// A price tag.
export const product = icon(
	'M4.5 4v7.6L13.1 20a1.5 1.5 0 0 0 2.1 0l4.8-4.8a1.5 1.5 0 0 0 0-2.1L11.6 4.5H4.5Zm1.5 1.5H11l7.9 7.9-4.8 4.8L6 10.9V6Zm2.5 1.2a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6Z'
);

// Rows of shops, the first one checked.
export const offers = icon(
	'm4.2 6.3 1.1-1.1 1.2 1.3 2.5-2.6 1 1.1-3.5 3.6-2.3-2.3ZM12 5.5h8V7h-8V5.5ZM4 11.25h16v1.5H4v-1.5Zm0 5.5h16v1.5H4v-1.5Z'
);

// A percent badge.
export const deals = icon(
	'M12 3.2 14.3 5l2.9-.1 1 2.8 2.4 1.6-.9 2.7.9 2.7-2.4 1.6-1 2.8-2.9-.1L12 20.8 9.7 19l-2.9.1-1-2.8-2.4-1.6.9-2.7-.9-2.7 2.4-1.6 1-2.8 2.9.1L12 3.2Zm0 1.9-1.8 1.4-2.3-.1-.8 2.2-1.9 1.3.7 2.1-.7 2.1 1.9 1.3.8 2.2 2.3-.1L12 18.9l1.8-1.4 2.3.1.8-2.2 1.9-1.3-.7-2.1.7-2.1-1.9-1.3-.8-2.2-2.3.1L12 5.1ZM9.7 8.5a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Zm4.6 4.6a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Zm.3-4.7 1 1-6.2 6.2-1-1 6.2-6.2Z'
);

// A step line.
export const history = icon(
	'M5.5 4v14.5H20V20H4V4h1.5ZM7.5 8.5h4v4h3V15h4V8H20v8.5h-7V14h-3v-4H7.5V8.5Z'
);

// A ticket.
export const coupons = icon(
	'M3.5 6h17v4.1a2 2 0 0 0 0 3.8V18h-17v-4.1a2 2 0 0 0 0-3.8V6ZM5 7.5v1.7a3.5 3.5 0 0 1 0 5.6v1.7h9v-1.7h1.5v1.7H19v-1.7a3.5 3.5 0 0 1 0-5.6V7.5h-3.5v1.7H14V7.5H5Zm9 3.2h1.5v2.6H14v-2.6Z'
);

// Two columns side by side, the first one marked.
export const comparison = icon(
	'M4 4h16v16H4V4Zm1.5 1.5v13h5.75v-13H5.5Zm7.25 0v13h5.75v-13h-5.75ZM7 8h3v1.5H7V8Zm0 3.25h3v1.5H7v-1.5ZM14.2 8h3v1.5h-3V8Zm0 3.25h3v1.5h-3v-1.5Zm-6 5.9-1.7-1.7.9-.9.8.8 1.6-1.6.9.9-2.5 2.5Z'
);

// Layout variations.
export const layoutCard = icon(
	'M4 4h16v16H4V4Zm1.5 1.5v13h13v-13h-13ZM7 7h4v4H7V7Zm5.5.5H17V9h-4.5V7.5Zm0 2.5H15v1.5h-2.5V10ZM7 13.5h10V17H7v-3.5Z'
);
export const layoutCompact = icon(
	'M3.5 8h17v8h-17V8ZM5 9.5v5h14v-5H5Zm1.5 1h3v3h-3v-3Zm4 .5h3v2h-3v-2Zm4 0h3.5v2h-3.5v-2Z'
);
export const layoutInline = icon(
	'M4 6.5h16V8H4V6.5Zm0 4.75h5v1.5H4v-1.5Zm6.5-.75h6v3h-6v-3Zm7.5.75h2v1.5h-2v-1.5ZM4 16h11v1.5H4V16Z'
);
export const topList = icon(
	'M4 4.5h3.5V8H4V4.5Zm6 1h10V7H10V5.5ZM4 10.25h3.5v3.5H4v-3.5Zm6 1h10v1.5H10v-1.5ZM4 16h3.5v3.5H4V16Zm6 1h10v1.5H10V17Z'
);
