<?php
/**
 * Pruebas automatizadas para WP Assets Pulse.
 *
 * Carga el entorno local de WordPress y verifica:
 * - Constantes y opciones por defecto.
 * - Desactivación de emojis.
 * - Atributos defer en scripts respetando exclusiones.
 * - Inyección de Resource Hints (preconnect).
 *
 * Ejecución:
 *   php tests/wp-assets-pulse/run.php
 */

$wp_load = dirname( __DIR__, 2 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	echo "ERROR: No se encontró wp-load.php en {$wp_load}\n";
	exit( 1 );
}

require_once $wp_load;

if ( ! class_exists( 'WP_Assets_Pulse' ) ) {
	$plugin_file = dirname( __DIR__, 2 ) . '/plugins/wp-assets-pulse/wp-assets-pulse.php';
	if ( file_exists( $plugin_file ) ) {
		require_once $plugin_file;
	} else {
		echo "ERROR: No se encontró el archivo principal del plugin.\n";
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

echo "\n--- INICIANDO PRUEBAS: WP Assets Pulse ---\n\n";

// 1. Constantes y configuración por defecto
echo "1. Configuración y Constantes:\n";
assert_test( 'Versión definida', WP_Assets_Pulse::VERSION === '1.0.0' );
assert_test( 'Clave de opciones válida', WP_Assets_Pulse::OPTION_KEY === 'wp_assets_pulse_settings' );
$defaults = WP_Assets_Pulse::get_default_settings();
assert_test( 'Emojis desactivados por defecto', $defaults['disable_emojis'] === true );
assert_test( 'Exclusiones de jQuery presentes', strpos( $defaults['defer_js_exclusions'], 'jquery' ) !== false );

// 2. Comprobación de atributo Defer en scripts
echo "\n2. Módulo de Carga Diferida (Defer):\n";
$script_tag = "<script src='https://example.com/custom.js'></script>";
$deferred   = WP_Assets_Pulse::add_defer_attribute( $script_tag, 'custom-script', 'https://example.com/custom.js' );
assert_test( 'Inyecta atributo defer en scripts normales', strpos( $deferred, 'defer src' ) !== false );

// Comprobación de exclusión
$jquery_tag = "<script src='https://example.com/jquery.js'></script>";
$excluded   = WP_Assets_Pulse::add_defer_attribute( $jquery_tag, 'jquery', 'https://example.com/jquery.js' );
assert_test( 'Respeta exclusión de jQuery (sin defer)', strpos( $excluded, 'defer' ) === false );

// 3. Comprobación de Resource Hints (Preconnect)
echo "\n3. Módulo de Resource Hints (Preconnect):\n";
$hints = WP_Assets_Pulse::add_preconnect_hints( array(), 'preconnect' );
assert_test( 'Retorna array de preconnect', is_array( $hints ) );
assert_test( 'Incluye Google Fonts preconnect', in_array( 'https://fonts.gstatic.com', $hints, true ) );

// Resumen final
echo "\n----------------------------------------\n";
echo "Total pruebas: " . ( $passed + $failed ) . " | Aprobadas: {$passed} | Fallidas: {$failed}\n";
echo "----------------------------------------\n\n";

exit( $failed > 0 ? 1 : 0 );
