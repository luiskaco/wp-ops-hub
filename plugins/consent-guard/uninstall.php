<?php
/**
 * Limpieza al borrar el plugin. Respeta «Conservar los registros al desinstalar».
 *
 * Sintaxis compatible con PHP 7.4: WordPress puede ejecutarlo aunque el plugin no cargue.
 *
 * @package ConsentGuard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Borra los datos de un sitio.
 */
function consent_guard_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'consent_guard_settings', array() );
	$keep     = is_array( $settings ) && ! empty( $settings['keep_data_on_uninstall'] );

	delete_option( 'consent_guard_settings' );
	delete_option( 'consent_guard_cookies' );
	delete_option( 'consent_guard_last_scan' );
	wp_clear_scheduled_hook( 'consent_guard_prune_log' );

	// Contadores del límite de peticiones.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_cg\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_cg\\_rl\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	if ( $keep ) {
		return;
	}

	$table = $wpdb->prefix . 'consent_guard_log';
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	delete_option( 'consent_guard_db_version' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $consent_guard_site_id ) {
		switch_to_blog( (int) $consent_guard_site_id );
		consent_guard_uninstall_site();
		restore_current_blog();
	}
} else {
	consent_guard_uninstall_site();
}
