<?php
/**
 * Actualizaciones automáticas desde GitHub con Plugin Update Checker (PUC).
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Updater {

	/**
	 * Inicializa el verificador de actualizaciones automáticas desde GitHub (PUC).
	 *
	 * @return void
	 */
	private static function init_update_checker() {
		$puc_file = dirname( WP_MCP_AGENT_BRIDGE_FILE ) . '/vendor/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $puc_file ) ) {
			return;
		}

		require_once $puc_file;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/luiskaco/utilities_wordpress/',
			WP_MCP_AGENT_BRIDGE_FILE,
			'wp-mcp-agent-bridge'
		);

		$vcs_api = $update_checker->getVcsApi();
		if ( method_exists( $vcs_api, 'setReleaseVersionFilter' ) ) {
			$vcs_api->setReleaseVersionFilter( '/^wp-mcp-agent-bridge-/i' );
		}

		if ( method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
			$vcs_api->enableReleaseAssets(
				'/^wp-mcp-agent-bridge.*\.zip$/i',
				$vcs_api::REQUIRE_RELEASE_ASSETS
			);
		}

		$normalize_version = function ( $info_or_update ) {
			if ( $info_or_update && isset( $info_or_update->version ) ) {
				if ( preg_match( '/^wp-mcp-agent-bridge-v?([0-9\.]+)/i', $info_or_update->version, $matches ) ) {
					$info_or_update->version = $matches[1];
				}
			}
			return $info_or_update;
		};

		$update_checker->addFilter( 'request_info_result', $normalize_version );
		$update_checker->addFilter( 'request_update_result', $normalize_version );

		// Reescribe la ruta de la API de GitHub al subdirectorio del plugin en el monorepo.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				static $in_flight = false;
				if ( $in_flight || ! is_string( $url ) ) {
					return $preempt;
				}

				if ( false !== strpos( $url, 'contents/wp-mcp-agent-bridge.php' ) ) {
					$in_flight  = true;
					$target_url = str_replace(
						'contents/wp-mcp-agent-bridge.php',
						'contents/plugins/wp-mcp-agent-bridge/wp-mcp-agent-bridge.php',
						$url
					);
					$response   = wp_remote_get( $target_url, $parsed_args );
					$in_flight  = false;
					return $response;
				}

				return $preempt;
			},
			10,
			3
		);
	}
}
