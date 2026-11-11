<?php
/**
 * Pruebas unitarias y de integración para WP MCP Agent Bridge.
 *
 * Carga el entorno de WordPress local y valida el registro de rutas REST,
 * los esquemas de herramientas y el protocolo JSON-RPC 2.0.
 *
 * Ejecución:
 *   php tests/wp-mcp-agent-bridge/run.php
 */

// Cargar WordPress local
$wp_load = dirname( __DIR__, 2 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	echo "ERROR: No se encontró wp-load.php en {$wp_load}\n";
	exit( 1 );
}

require_once $wp_load;

// Comprobar que la clase principal esté cargada
if ( ! class_exists( 'WP_MCP_Agent_Bridge' ) ) {
	$plugin_file = dirname( __DIR__, 2 ) . '/plugins/wp-mcp-agent-bridge/wp-mcp-agent-bridge.php';
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

echo "\n--- INICIANDO PRUEBAS: WP MCP Agent Bridge ---\n\n";

// 1. Constantes y configuración por defecto
echo "1. Configuración y Constantes:\n";
assert_test( 'Versión definida', WP_MCP_Agent_Bridge::VERSION === '1.0.0' );
assert_test( 'Clave de opciones', WP_MCP_Agent_Bridge::OPTION_KEY === 'wp_mcp_agent_bridge_settings' );
$defaults = WP_MCP_Agent_Bridge::get_default_settings();
assert_test( 'Ajustes por defecto válidos', isset( $defaults['enabled'] ) && $defaults['enabled'] === true );

// 2. Herramientas activas
echo "\n2. Catálogo de Herramientas (*Abilities*):\n";
$tools = WP_MCP_Agent_Bridge::get_active_tools();
assert_test( 'Herramientas disponibles', is_array( $tools ) && count( $tools ) > 0, 'No se cargaron herramientas.' );
assert_test( 'Tool get-site-info presente', isset( $tools['get-site-info'] ) );
assert_test( 'Tool list-posts presente', isset( $tools['list-posts'] ) );
assert_test( 'Tool upload-media-url presente', isset( $tools['upload-media-url'] ) );
assert_test( 'Tool upload-media-base64 presente', isset( $tools['upload-media-base64'] ) );

// 3. Simulación de solicitud JSON-RPC initialize
echo "\n3. Protocolo JSON-RPC 2.0 (initialize):\n";
$request = new WP_REST_Request( 'POST', '/mcp/v1/' );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 1,
			'method'  => 'initialize',
			'params'  => array( 'protocolVersion' => '2024-11-05' ),
		)
	)
);

$response = WP_MCP_Agent_Bridge::handle_rpc_request( $request );
$data     = $response->get_data();
assert_test( 'Respuesta RPC 200', $response->get_status() === 200 );
assert_test( 'Estructura JSON-RPC 2.0', isset( $data['jsonrpc'] ) && $data['jsonrpc'] === '2.0' );
assert_test( 'ID coincide', isset( $data['id'] ) && $data['id'] === 1 );
assert_test( 'Protocol version negociada', isset( $data['result']['protocolVersion'] ) );
assert_test( 'ServerInfo contiene wp-mcp-agent-bridge', isset( $data['result']['serverInfo']['name'] ) && $data['result']['serverInfo']['name'] === 'wp-mcp-agent-bridge' );

// 4. Simulación de tools/list
echo "\n4. Protocolo JSON-RPC 2.0 (tools/list):\n";
$request_list = new WP_REST_Request( 'POST', '/mcp/v1/' );
$request_list->set_header( 'Content-Type', 'application/json' );
$request_list->set_body(
	wp_json_encode(
		array(
			'jsonrpc' => '2.0',
			'id'      => 2,
			'method'  => 'tools/list',
			'params'  => array(),
		)
	)
);

$response_list = WP_MCP_Agent_Bridge::handle_rpc_request( $request_list );
$data_list     = $response_list->get_data();
assert_test( 'tools/list exitoso', $response_list->get_status() === 200 && isset( $data_list['result']['tools'] ) );
assert_test( 'tools/list no vacío', is_array( $data_list['result']['tools'] ) && count( $data_list['result']['tools'] ) >= 5 );

// 5. Ejecución interna de get-site-info
echo "\n5. Ejecución de Tool (get-site-info):\n";
$site_info = WP_MCP_Agent_Bridge::tool_get_site_info( array() );
assert_test( 'get-site-info devuelve site_name', ! empty( $site_info['site_name'] ) );
assert_test( 'get-site-info devuelve wp_version', ! empty( $site_info['wp_version'] ) );
assert_test( 'get-site-info contiene mapa de integraciones', isset( $site_info['integrations']['woocommerce'] ) );

// Resumen final
echo "\n----------------------------------------\n";
echo "Total pruebas: " . ( $passed + $failed ) . " | Aprobadas: {$passed} | Fallidas: {$failed}\n";
echo "----------------------------------------\n\n";

exit( $failed > 0 ? 1 : 0 );
