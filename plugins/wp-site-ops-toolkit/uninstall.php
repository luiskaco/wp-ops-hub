<?php
/**
 * Desinstalación limpia para WP Site Ops Toolkit.
 *
 * @package WP_Site_Ops_Toolkit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wp_site_ops_toolkit_settings' );
delete_option( 'wp_site_ops_mail_log' );
