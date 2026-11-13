<?php
/**
 * Desinstalación limpia para WP Assets Pulse.
 *
 * @package WP_Assets_Pulse
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wp_assets_pulse_settings' );
