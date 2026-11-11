<?php
/**
 * Pruebas unitarias y de integración para WP Site Ops Toolkit.
 *
 * Carga el entorno de WordPress local y valida:
 * - Constantes y ajustes por defecto.
 * - Módulo de Mantenimiento y lógica de bypass.
 * - Módulo de Limpieza de base de datos y obtención de métricas.
 * - Módulo de Mail Debugger y modo simulación/blackhole.
 *
 * Ejecución:
 *   php tests/wp-site-ops-toolkit/run.php
 */

$wp_load = dirname( __DIR__, 2 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	echo "ERROR: No se encontró wp-load.php en {$wp_load}\n";
	exit( 1 );
}

require_once $wp_load;

if ( ! class_exists( 'WP_Site_Ops_Toolkit' ) ) {
	$plugin_file = dirname( __DIR__, 2 ) . '/plugins/wp-site-ops-toolkit/wp-site-ops-toolkit.php';
	if ( file_exists( $plugin_file ) ) {
		require_once $plugin_file;
	} else {
		echo "ERROR: No se encontró el archivo del plugin.\n";
		exit( 1 );
	}
}

$passed = 0;
$failed = 0;

function assert_test( $name, $condition, $msg = '' ) {
	global $passed, $failed;
	if ( $condition ) {
		echo "  ✔ [OK] {$name}\n";
		$passed++;
	} else {
		echo "  ✖ [FAIL] {$name} - {$msg}\n";
		$failed++;
	}
}

echo "\n--- INICIANDO PRUEBAS: WP Site Ops Toolkit ---\n\n";

// 1. Constantes y configuración
echo "1. Configuración y Constantes:\n";
assert_test( 'Versión definida', WP_Site_Ops_Toolkit::VERSION === '1.0.0' );
assert_test( 'Clave de opciones válida', WP_Site_Ops_Toolkit::OPTION_KEY === 'wp_site_ops_toolkit_settings' );
$defaults = WP_Site_Ops_Toolkit::get_default_settings();
assert_test( 'Mantenimiento desactivado por defecto', $defaults['maintenance_enabled'] === false );
assert_test( 'Limpiador DB activo por defecto', $defaults['db_cleaner_enabled'] === true );

// 2. Módulo de Limpieza de BD
echo "\n2. Módulo Limpiador de Base de Datos:\n";
$counts = WP_Site_Ops_Toolkit::get_cleanup_counts();
assert_test( 'Métricas de BD disponibles', is_array( $counts ) );
assert_test( 'Conteo de transients numérico', isset( $counts['transients'] ) && is_int( $counts['transients'] ) );
assert_test( 'Conteo de revisiones numérico', isset( $counts['revisions'] ) && is_int( $counts['revisions'] ) );
assert_test( 'Conteo de papelera numérico', isset( $counts['trashed_posts'] ) && is_int( $counts['trashed_posts'] ) );

// 3. Módulo de Mail Debugger
echo "\n3. Módulo de Mail Debugger:\n";
assert_test( 'Clave de logs de correo', WP_Site_Ops_Toolkit::MAIL_LOG_KEY === 'wp_site_ops_mail_log' );
$test_mail_args = array(
	'to'      => 'test@example.com',
	'subject' => 'Prueba de envio',
	'message' => 'Contenido de prueba',
);
$intercepted = WP_Site_Ops_Toolkit::intercept_wp_mail( $test_mail_args );
assert_test( 'Filtro wp_mail no corrompe argumentos', $intercepted['to'] === 'test@example.com' && $intercepted['subject'] === 'Prueba de envio' );

// 4. IP del cliente
echo "\n4. Helper de Red:\n";
$ip = WP_Site_Ops_Toolkit::get_client_ip();
assert_test( 'IP del cliente válida', filter_var( $ip, FILTER_VALIDATE_IP ) !== false );

// Resumen
echo "\n----------------------------------------\n";
echo "Total pruebas: " . ( $passed + $failed ) . " | Aprobadas: {$passed} | Fallidas: {$failed}\n";
echo "----------------------------------------\n\n";

exit( $failed > 0 ? 1 : 0 );
