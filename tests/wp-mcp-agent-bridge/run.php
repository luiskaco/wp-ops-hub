<?php
/**
 * Pruebas de WP MCP Agent Bridge contra el WordPress local (Laragon).
 *
 * Uso:
 *   php tests/wp-mcp-agent-bridge/run.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Solo CLI.\n" );
}

$host = getenv( 'MCP_TEST_HOST' ) ?: 'pluginwordpress.test';
if ( ! preg_match( '/(\.test|\.local|localhost)$/', $host ) && '1' !== getenv( 'MCP_TESTS_ALLOW' ) ) {
	exit( "Rechazado: el host '$host' no parece local. Usa MCP_TESTS_ALLOW=1 bajo tu responsabilidad.\n" );
}

$_SERVER['HTTP_HOST']      = $host;
$_SERVER['REMOTE_ADDR']    = '203.0.113.88';
$_SERVER['REQUEST_METHOD'] = 'POST';

require dirname( __DIR__, 2 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

if ( ! class_exists( 'WP_MCP_Agent_Bridge' ) ) {
	$plugin_file = dirname( __DIR__, 2 ) . '/plugins/wp-mcp-agent-bridge/wp-mcp-agent-bridge.php';
	if ( file_exists( $plugin_file ) ) {
		require_once $plugin_file;
	} else {
		exit( "WP MCP Agent Bridge no encontrado.\n" );
	}
}

$GLOBALS['mcp_pass'] = 0;
$GLOBALS['mcp_fail'] = array();

function mcp_assert( bool $condition, string $name ): void {
	if ( $condition ) {
		$GLOBALS['mcp_pass']++;
		echo "  ok   $name\n";
	} else {
		$GLOBALS['mcp_fail'][] = $name;
		echo "  FAIL $name\n";
	}
}

function mcp_section( string $title ): void {
	echo "\n$title\n";
}

function mcp_rpc( array $payload ): WP_REST_Response {
	$req = new WP_REST_Request( 'POST', '/mcp/v1/rpc' );
	$req->set_header( 'Content-Type', 'application/json' );
	$req->set_body( wp_json_encode( $payload ) );
	return WP_MCP_Agent_Bridge::handle_rpc_request( $req );
}

// Respaldar opciones previas y preparar entorno de prueba
$prev_settings = get_option( WP_MCP_Agent_Bridge::OPTION_KEY, null );
$prev_log      = get_option( WP_MCP_Agent_Bridge::LOG_KEY, null );

update_option(
	WP_MCP_Agent_Bridge::OPTION_KEY,
	array(
		'enabled'              => true,
		'allow_writes'         => true,
		'allow_deletes'        => true,
		'modules'              => array(
			'core'        => true,
			'media'       => true,
			'woocommerce' => true,
			'seo'         => true,
			'cache'       => true,
		),
		'activity_log_enabled' => false,
	),
	false
);

$suffix    = wp_generate_password( 6, false );
$admin_id  = wp_insert_user(
	array(
		'user_login' => 'mcp_test_admin_' . $suffix,
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'mcp-admin-' . $suffix . '@example.invalid',
		'role'       => 'administrator',
	)
);
$author_id = wp_insert_user(
	array(
		'user_login' => 'mcp_test_author_' . $suffix,
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'mcp-author-' . $suffix . '@example.invalid',
		'role'       => 'author',
	)
);
$sub_id    = wp_insert_user(
	array(
		'user_login' => 'mcp_test_sub_' . $suffix,
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'mcp-sub-' . $suffix . '@example.invalid',
		'role'       => 'subscriber',
	)
);

$created_posts = array();

try {
	// -----------------------------------------------------------------------
	mcp_section( '1. Protocolo JSON-RPC 2.0 y Handshake' );
	// -----------------------------------------------------------------------
	wp_set_current_user( $admin_id );

	$res = mcp_rpc( array( 'jsonrpc' => '1.0', 'method' => 'ping' ) );
	$d   = $res->get_data();
	mcp_assert( isset( $d['error']['code'] ) && -32600 === $d['error']['code'], 'Rechaza JSON-RPC != 2.0 (-32600)' );

	$res = mcp_rpc( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array( 'protocolVersion' => '2025-11-25' ) ) );
	$d   = $res->get_data();
	mcp_assert( '2025-11-25' === ( $d['result']['protocolVersion'] ?? '' ), 'initialize negocia version 2025-11-25' );

	$res = mcp_rpc( array( 'jsonrpc' => '2.0', 'method' => 'notifications/initialized' ) );
	mcp_assert( 204 === $res->get_status(), 'notifications/initialized responde HTTP 204' );

	$res = mcp_rpc( array( 'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list' ) );
	$d   = $res->get_data();
	mcp_assert( ! empty( $d['result']['tools'] ) && is_array( $d['result']['tools'] ), 'tools/list devuelve catalogo de herramientas' );

	$res = mcp_rpc( array( 'jsonrpc' => '2.0', 'id' => 3, 'method' => 'no/existe' ) );
	$d   = $res->get_data();
	mcp_assert( -32601 === ( $d['error']['code'] ?? 0 ), 'Metodo desconocido devuelve -32601' );

	// -----------------------------------------------------------------------
	mcp_section( '2. Validacion de inputSchema' );
	// -----------------------------------------------------------------------
	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 10,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'get-post',
				'arguments' => array(),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( -32602 === ( $d['error']['code'] ?? 0 ), 'Rechaza parametro required faltante (-32602)' );

	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 11,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'get-post',
				'arguments' => array( 'id' => 1, 'extra_field' => 'nope' ),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( -32602 === ( $d['error']['code'] ?? 0 ), 'Rechaza additionalProperties no declarados (-32602)' );

	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 12,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'list-posts',
				'arguments' => array( 'limit' => 999 ),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( -32602 === ( $d['error']['code'] ?? 0 ), 'Rechaza integer por encima de maximum (-32602)' );

	// -----------------------------------------------------------------------
	mcp_section( '3. Permisos de endpoint y Rate Limiting' );
	// -----------------------------------------------------------------------
	$rl_key_admin  = 'wp_mcp_rl_' . md5( $admin_id . '|' . $_SERVER['REMOTE_ADDR'] );
	$rl_key_sub    = 'wp_mcp_rl_' . md5( $sub_id . '|' . $_SERVER['REMOTE_ADDR'] );
	$rl_key_author = 'wp_mcp_rl_' . md5( $author_id . '|' . $_SERVER['REMOTE_ADDR'] );
	delete_transient( $rl_key_admin );
	delete_transient( $rl_key_sub );
	delete_transient( $rl_key_author );

	wp_set_current_user( 0 );
	$perm = WP_MCP_Agent_Bridge::check_permissions( new WP_REST_Request( 'POST', '/mcp/v1' ) );
	mcp_assert( is_wp_error( $perm ) && 'mcp_unauthorized' === $perm->get_error_code(), 'Usuario anonimo bloqueado (401)' );

	wp_set_current_user( $sub_id );
	$perm = WP_MCP_Agent_Bridge::check_permissions( new WP_REST_Request( 'POST', '/mcp/v1' ) );
	mcp_assert( is_wp_error( $perm ) && 'mcp_forbidden' === $perm->get_error_code(), 'Subscriber bloqueado (403)' );

	wp_set_current_user( $admin_id );
	set_transient( $rl_key_admin, 120, MINUTE_IN_SECONDS );
	$perm = WP_MCP_Agent_Bridge::check_permissions( new WP_REST_Request( 'POST', '/mcp/v1' ) );
	mcp_assert( is_wp_error( $perm ) && 'mcp_rate_limited' === $perm->get_error_code(), 'Rate limit bloquea al llegar al umbral (429)' );
	delete_transient( $rl_key_admin );

	// -----------------------------------------------------------------------
	mcp_section( '4. Autorizacion por objeto (IDOR) y politicas globales' );
	// -----------------------------------------------------------------------
	$admin_draft_id = wp_insert_post(
		array(
			'post_title'  => 'Borrador secreto admin',
			'post_status' => 'draft',
			'post_author' => $admin_id,
		)
	);
	$created_posts[] = $admin_draft_id;

	wp_set_current_user( $author_id );
	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 20,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'get-post',
				'arguments' => array( 'id' => $admin_draft_id ),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( true === ( $d['result']['isError'] ?? false ), 'Author no puede leer borrador privado de otro usuario' );

	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 21,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'update-post',
				'arguments' => array( 'id' => $admin_draft_id, 'title' => 'Hackeado' ),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( true === ( $d['result']['isError'] ?? false ), 'Author no puede editar post de otro usuario' );

	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 22,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'delete-post',
				'arguments' => array( 'id' => $admin_draft_id ),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( true === ( $d['result']['isError'] ?? false ), 'Author no puede borrar post de otro usuario' );

	// Politica allow_writes = false y allow_deletes = false
	wp_set_current_user( $admin_id );
	$opts                  = get_option( WP_MCP_Agent_Bridge::OPTION_KEY );
	$opts['allow_writes']  = false;
	$opts['allow_deletes'] = false;
	update_option( WP_MCP_Agent_Bridge::OPTION_KEY, $opts, false );

	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 23,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'create-post',
				'arguments' => array( 'title' => 'Bloqueado en solo lectura' ),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( true === ( $d['result']['isError'] ?? false ), 'allow_writes=false bloquea create-post' );

	$opts['allow_writes'] = true;
	update_option( WP_MCP_Agent_Bridge::OPTION_KEY, $opts, false );

	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 24,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'delete-post',
				'arguments' => array( 'id' => $admin_draft_id ),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( true === ( $d['result']['isError'] ?? false ), 'allow_deletes=false bloquea delete-post' );

	// -----------------------------------------------------------------------
	mcp_section( '5. Validacion de subida de medios Base64' );
	// -----------------------------------------------------------------------
	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 30,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'upload-media-base64',
				'arguments' => array(
					'filename'    => 'shell.php',
					'base64_data' => base64_encode( '<?php echo 1;' ),
				),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( true === ( $d['result']['isError'] ?? false ), 'Rechaza archivo .php en upload-media-base64' );

	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 31,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'upload-media-base64',
				'arguments' => array(
					'filename'    => 'fake.jpg',
					'base64_data' => base64_encode( '<html><script>alert(1)</script></html>' ),
				),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( true === ( $d['result']['isError'] ?? false ), 'Rechaza HTML disfrazado de .jpg (magic bytes)' );

	// PNG 1x1 valido (redirigiendo uploads a /tmp para no ensuciar wp-content/uploads local)
	$tmp_uploads = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wp-mcp-test-uploads-' . $suffix;
	wp_mkdir_p( $tmp_uploads );
	$upload_dir_filter = function ( $dirs ) use ( $tmp_uploads ) {
		$dirs['path']    = $tmp_uploads;
		$dirs['basedir'] = $tmp_uploads;
		$dirs['subdir']  = '';
		$dirs['url']     = 'http://example.invalid/uploads';
		$dirs['baseurl'] = 'http://example.invalid/uploads';
		return $dirs;
	};
	add_filter( 'upload_dir', $upload_dir_filter );

	$png_1x1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
	$res     = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 32,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'upload-media-base64',
				'arguments' => array(
					'filename'    => 'test-pixel-' . $suffix . '.png',
					'base64_data' => $png_1x1,
					'alt'         => 'Pixel de prueba',
				),
			),
		)
	);
	remove_filter( 'upload_dir', $upload_dir_filter );

	$d       = $res->get_data();
	$ok_png  = false === ( $d['result']['isError'] ?? true );
	mcp_assert( $ok_png, 'Acepta PNG real 1x1 en upload-media-base64' );
	if ( ! $ok_png && ! empty( $d['result']['content'][0]['text'] ) ) {
		echo "    -> Detalle: " . $d['result']['content'][0]['text'] . "\n";
	}
	if ( $ok_png && ! empty( $d['result']['content'][0]['text'] ) ) {
		$decoded = json_decode( $d['result']['content'][0]['text'], true );
		if ( ! empty( $decoded['id'] ) ) {
			wp_delete_attachment( (int) $decoded['id'], true );
		}
	}
	if ( is_dir( $tmp_uploads ) ) {
		foreach ( glob( $tmp_uploads . DIRECTORY_SEPARATOR . '*' ) as $f ) {
			@unlink( $f );
		}
		@rmdir( $tmp_uploads );
	}

	// -----------------------------------------------------------------------
	mcp_section( '6. Terminos y Admin Assets' );
	// -----------------------------------------------------------------------
	$res = mcp_rpc(
		array(
			'jsonrpc' => '2.0',
			'id'      => 40,
			'method'  => 'tools/call',
			'params'  => array(
				'name'      => 'create-term',
				'arguments' => array(
					'name'     => 'Prueba',
					'taxonomy' => 'taxonomia_inexistente_xyz',
				),
			),
		)
	);
	$d   = $res->get_data();
	mcp_assert( true === ( $d['result']['isError'] ?? false ), 'create-term rechaza taxonomia inexistente' );

} finally {
	foreach ( $created_posts as $pid ) {
		wp_delete_post( $pid, true );
	}
	wp_delete_user( $admin_id );
	wp_delete_user( $author_id );
	wp_delete_user( $sub_id );

	delete_transient( 'wp_mcp_rl_' . md5( $admin_id . '|' . $_SERVER['REMOTE_ADDR'] ) );
	delete_transient( 'wp_mcp_rl_' . md5( $author_id . '|' . $_SERVER['REMOTE_ADDR'] ) );
	delete_transient( 'wp_mcp_rl_' . md5( $sub_id . '|' . $_SERVER['REMOTE_ADDR'] ) );

	if ( null === $prev_settings ) {
		delete_option( WP_MCP_Agent_Bridge::OPTION_KEY );
	} else {
		update_option( WP_MCP_Agent_Bridge::OPTION_KEY, $prev_settings, false );
	}

	if ( null === $prev_log ) {
		delete_option( WP_MCP_Agent_Bridge::LOG_KEY );
	} else {
		update_option( WP_MCP_Agent_Bridge::LOG_KEY, $prev_log, false );
	}
}

echo "\nResumen: {$GLOBALS['mcp_pass']} ok, " . count( $GLOBALS['mcp_fail'] ) . " fallos.\n";
exit( empty( $GLOBALS['mcp_fail'] ) ? 0 : 1 );
