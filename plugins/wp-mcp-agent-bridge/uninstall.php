<?php
/**
 * Desinstalación limpia para WP MCP Agent Bridge.
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wp_mcp_agent_bridge_settings' );
delete_option( 'wp_mcp_agent_bridge_log' );

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 200 ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		delete_option( 'wp_mcp_agent_bridge_settings' );
		delete_option( 'wp_mcp_agent_bridge_log' );
		restore_current_blog();
	}
}
