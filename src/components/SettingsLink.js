import { ExternalLink } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Link to the plugin settings, for users who may open them. The scripts run in the admin page around the canvas, so
 * its location gives the admin directory.
 */
export default function SettingsLink() {
	const canManage = useSelect(
		( select ) => select( coreStore ).canUser( 'read', 'settings' ),
		[]
	);
	if ( ! canManage ) {
		return null;
	}

	const url = new URL( 'options-general.php', window.location.href ).href;

	return (
		<ExternalLink href={ addQueryArgs( url, { page: 'oha-deals' } ) }>
			{ __( 'Open the oha.deals settings', 'oha-deals' ) }
		</ExternalLink>
	);
}
