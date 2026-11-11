<?php
/**
 * Servidor JSON-RPC 2.0 y enrutador MCP para WP MCP Agent Bridge.
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Server {

	/**
	 * Versiones del protocolo Model Context Protocol soportadas.
	 *
	 * @return array
	 */
	public static function protocol_versions() {
		return array( '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' );
	}

	/**
	 * Registra la ruta REST para el servidor MCP.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'mcp/v1',
			'/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_get_info' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'handle_rpc_request' ),
					'permission_callback' => array( __CLASS__, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Respuesta al GET informativo del endpoint MCP.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function handle_get_info( $request ) {
		$tools = self::get_active_tools();
		$info  = array(
			'name'              => 'WP MCP Agent Bridge',
			'version'           => self::VERSION,
			'protocol_versions' => self::protocol_versions(),
			'endpoint'          => rest_url( 'mcp/v1' ),
			'tools_count'       => count( $tools ),
			'user'              => wp_get_current_user()->user_login,
		);
		return new WP_REST_Response( $info, 200 );
	}

	/**
	 * Maneja las solicitudes POST JSON-RPC 2.0 de clientes MCP.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function handle_rpc_request( $request ) {
		$body = $request->get_json_params();

		// Validación de JSON-RPC 2.0
		if ( ! is_array( $body ) || ! isset( $body['jsonrpc'] ) || '2.0' !== $body['jsonrpc'] || ! isset( $body['method'] ) ) {
			return self::rpc_error( null, -32600, 'Invalid Request: Missing JSON-RPC 2.0 structure.' );
		}

		$id     = isset( $body['id'] ) ? $body['id'] : null;
		$method = (string) $body['method'];
		$params = isset( $body['params'] ) && is_array( $body['params'] ) ? $body['params'] : array();

		switch ( $method ) {
			case 'initialize':
				return self::rpc_initialize( $id, $params );

			case 'notifications/initialized':
				// Notificación post-handshake, no requiere respuesta de datos.
				return new WP_REST_Response( null, 204 );

			case 'ping':
				return self::rpc_success( $id, array() );

			case 'tools/list':
				return self::rpc_tools_list( $id, $params );

			case 'tools/call':
				return self::rpc_tools_call( $id, $params );

			default:
				return self::rpc_error( $id, -32601, sprintf( 'Method not found: %s', $method ) );
		}
	}

	/**
	 * Maneja el método initialize negociando capacidades.
	 *
	 * @param mixed $id
	 * @param array $params
	 * @return WP_REST_Response
	 */
	private static function rpc_initialize( $id, $params ) {
		$client_version = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '2024-11-05';
		$supported      = self::protocol_versions();
		$chosen_version = in_array( $client_version, $supported, true ) ? $client_version : $supported[0];

		$result = array(
			'protocolVersion' => $chosen_version,
			'capabilities'    => array(
				'tools' => (object) array(
					'listChanged' => false,
				),
			),
			'serverInfo'      => array(
				'name'    => 'wp-mcp-agent-bridge',
				'version' => self::VERSION,
			),
			'instructions'    => sprintf(
				'You are connected to WordPress (%s) at %s. Use tools/list to see all available actions.',
				get_bloginfo( 'name' ),
				home_url()
			),
		);

		return self::rpc_success( $id, $result );
	}

	/**
	 * Devuelve el listado de herramientas (tools/list).
	 *
	 * @param mixed $id
	 * @param array $params
	 * @return WP_REST_Response
	 */
	private static function rpc_tools_list( $id, $params ) {
		$all_tools   = self::get_active_tools();
		$tools_reply = array();

		foreach ( $all_tools as $tool ) {
			$tools_reply[] = array(
				'name'        => $tool['name'],
				'description' => $tool['description'],
				'inputSchema' => $tool['schema'],
			);
		}

		return self::rpc_success( $id, array( 'tools' => $tools_reply ) );
	}

	/**
	 * Ejecuta una herramienta específica (tools/call).
	 *
	 * @param mixed $id
	 * @param array $params
	 * @return WP_REST_Response
	 */
	private static function rpc_tools_call( $id, $params ) {
		$name = isset( $params['name'] ) ? sanitize_key( $params['name'] ) : '';
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		$tools = self::get_active_tools();
		if ( ! isset( $tools[ $name ] ) ) {
			self::log_activity( $name, false, 'Tool not found or disabled.' );
			return self::rpc_error( $id, -32602, sprintf( 'Unknown or disabled tool: %s', $name ) );
		}

		$tool = $tools[ $name ];

		// Validar políticas de escritura/borrado globales
		$settings = self::get_settings();
		if ( ! $tool['read_only'] && empty( $settings['allow_writes'] ) ) {
			self::log_activity( $name, false, 'Writes are disabled in plugin settings.' );
			return self::rpc_tool_error( $id, 'Writes are currently disabled in WP MCP Agent Bridge settings.' );
		}

		if ( ! empty( $tool['meta']['destructive'] ) && empty( $settings['allow_deletes'] ) ) {
			self::log_activity( $name, false, 'Deletes are disabled in plugin settings.' );
			return self::rpc_tool_error( $id, 'Destructive operations are disabled in WP MCP Agent Bridge settings.' );
		}

		// Validar capacidades requeridas de usuario
		if ( ! empty( $tool['meta']['cap'] ) && ! current_user_can( $tool['meta']['cap'] ) ) {
			self::log_activity( $name, false, sprintf( 'Missing capability: %s', $tool['meta']['cap'] ) );
			return self::rpc_tool_error( $id, sprintf( 'Permission denied: Requires capability %s', $tool['meta']['cap'] ) );
		}

		// Ejecución segura del callback
		try {
			$result = call_user_func( $tool['callback'], $args );

			if ( is_wp_error( $result ) ) {
				$msg = $result->get_error_message();
				self::log_activity( $name, false, $msg );
				return self::rpc_tool_error( $id, $msg );
			}

			self::log_activity( $name, true, 'Execution completed successfully.' );
			return self::rpc_tool_success( $id, $result );

		} catch ( Exception $e ) {
			$err = $e->getMessage();
			self::log_activity( $name, false, $err );
			return self::rpc_tool_error( $id, sprintf( 'Internal execution error: %s', $err ) );
		}
	}

	/**
	 * Devuelve un éxito estándar JSON-RPC.
	 *
	 * @param mixed $id
	 * @param mixed $result
	 * @return WP_REST_Response
	 */
	private static function rpc_success( $id, $result ) {
		$payload = array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
		return new WP_REST_Response( $payload, 200 );
	}

	/**
	 * Devuelve un error estándar JSON-RPC de nivel de protocolo.
	 *
	 * @param mixed  $id
	 * @param int    $code
	 * @param string $message
	 * @return WP_REST_Response
	 */
	private static function rpc_error( $id, $code, $message ) {
		$payload = array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => (int) $code,
				'message' => (string) $message,
			),
		);
		return new WP_REST_Response( $payload, 200 );
	}

	/**
	 * Devuelve una respuesta de éxito de herramienta en formato content block de MCP.
	 *
	 * @param mixed $id
	 * @param mixed $data
	 * @return WP_REST_Response
	 */
	private static function rpc_tool_success( $id, $data ) {
		$text = is_string( $data ) ? $data : wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$result = array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $text,
				),
			),
			'isError' => false,
		);
		return self::rpc_success( $id, $result );
	}

	/**
	 * Devuelve una respuesta de error de herramienta en formato MCP (isError = true).
	 *
	 * @param mixed  $id
	 * @param string $message
	 * @return WP_REST_Response
	 */
	private static function rpc_tool_error( $id, $message ) {
		$result = array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => 'Error: ' . (string) $message,
				),
			),
			'isError' => true,
		);
		return self::rpc_success( $id, $result );
	}
}
