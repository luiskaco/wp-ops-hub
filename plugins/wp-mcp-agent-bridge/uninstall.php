<?php
/**
 * Desinstalación limpia para WP MCP Agent Bridge.
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wp_mcp_agent_bridge_settings' );
delete_option( 'wp_mcp_agent_bridge_log' );
