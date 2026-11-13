<?php
/**
 * Utilidades base, helpers y respuestas normalizadas para WP MCP Agent Bridge.
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Support {

	/**
	 * Construye la definición estandarizada de una habilidad / tool para MCP.
	 *
	 * @param string   $name        Identificador único (kebab-case).
	 * @param string   $title       Título legible para humanos.
	 * @param string   $description Descripción clara de lo que hace.
	 * @param string   $category    Categoría (core, media, woocommerce, seo, cache, builders).
	 * @param bool     $read_only   Indica si la operación solo lee (true) o escribe/modifica (false).
	 * @param callable $callback    Método que ejecutará la acción.
	 * @param array    $properties  Esquema JSON Schema de los parámetros de entrada.
	 * @param array    $required    Lista de nombres de propiedades requeridas.
	 * @param array    $meta        Metadatos adicionales (ej: 'cap' para capability requerida).
	 * @return array
	 */
	public static function define_tool( $name, $title, $description, $category, $read_only, $callback, $properties = array(), $required = array(), $meta = array() ) {
		return array(
			'name'        => (string) $name,
			'title'       => (string) $title,
			'description' => (string) $description,
			'category'    => (string) $category,
			'read_only'   => (bool) $read_only,
			'callback'    => $callback,
			'schema'      => array(
				'type'                 => 'object',
				'properties'           => (object) $properties,
				'required'             => array_values( (array) $required ),
				'additionalProperties' => false,
			),
			'meta'        => is_array( $meta ) ? $meta : array(),
		);
	}

	/**
	 * Propiedad de tipo texto para JSON Schema.
	 *
	 * @param string $description
	 * @param int    $max_length
	 * @return array
	 */
	public static function schema_string( $description, $max_length = 0 ) {
		$schema = array(
			'type'        => 'string',
			'description' => (string) $description,
		);
		if ( $max_length > 0 ) {
			$schema['maxLength'] = (int) $max_length;
		}
		return $schema;
	}

	/**
	 * Propiedad de tipo entero para JSON Schema.
	 *
	 * @param string $description
	 * @param int|null $minimum
	 * @param int|null $maximum
	 * @return array
	 */
	public static function schema_int( $description, $minimum = null, $maximum = null ) {
		$schema = array(
			'type'        => 'integer',
			'description' => (string) $description,
		);
		if ( null !== $minimum ) {
			$schema['minimum'] = (int) $minimum;
		}
		if ( null !== $maximum ) {
			$schema['maximum'] = (int) $maximum;
		}
		return $schema;
	}

	/**
	 * Propiedad de tipo booleano para JSON Schema.
	 *
	 * @param string $description
	 * @return array
	 */
	public static function schema_bool( $description ) {
		return array(
			'type'        => 'boolean',
			'description' => (string) $description,
		);
	}

	/**
	 * Propiedad de tipo array para JSON Schema.
	 *
	 * @param string $description
	 * @param array  $items_schema
	 * @return array
	 */
	public static function schema_array( $description, $items_schema = array( 'type' => 'string' ) ) {
		return array(
			'type'        => 'array',
			'description' => (string) $description,
			'items'       => (object) $items_schema,
		);
	}

	/**
	 * Valida los argumentos recibidos contra el inputSchema de la herramienta.
	 *
	 * @param array $args   Argumentos enviados por el cliente.
	 * @param array $schema Esquema JSON Schema de la herramienta.
	 * @return true|WP_Error
	 */
	public static function validate_tool_arguments( $args, $schema ) {
		if ( ! is_array( $args ) ) {
			return self::mcp_error( 'invalid_arguments', __( 'Los argumentos deben ser un objeto JSON válido.', 'wp-mcp-agent-bridge' ) );
		}

		$properties = isset( $schema['properties'] ) ? (array) $schema['properties'] : array();
		$required   = isset( $schema['required'] ) && is_array( $schema['required'] ) ? $schema['required'] : array();

		if ( ! empty( $schema['additionalProperties'] ) === false ) {
			foreach ( array_keys( $args ) as $key ) {
				if ( ! array_key_exists( $key, $properties ) ) {
					return self::mcp_error( 'unexpected_argument', sprintf( __( 'Parámetro no permitido: %s', 'wp-mcp-agent-bridge' ), sanitize_key( $key ) ) );
				}
			}
		}

		foreach ( $required as $req_key ) {
			if ( ! array_key_exists( $req_key, $args ) || null === $args[ $req_key ] ) {
				return self::mcp_error( 'missing_argument', sprintf( __( 'Falta el parámetro requerido: %s', 'wp-mcp-agent-bridge' ), $req_key ) );
			}
		}

		foreach ( $args as $key => $value ) {
			if ( ! isset( $properties[ $key ] ) || ! is_array( $properties[ $key ] ) ) {
				continue;
			}
			$prop = $properties[ $key ];
			$type = isset( $prop['type'] ) ? $prop['type'] : '';

			if ( 'string' === $type ) {
				if ( ! is_string( $value ) ) {
					return self::mcp_error( 'invalid_type', sprintf( __( 'El parámetro %s debe ser de tipo string.', 'wp-mcp-agent-bridge' ), $key ) );
				}
				if ( ! empty( $prop['maxLength'] ) && strlen( $value ) > (int) $prop['maxLength'] ) {
					return self::mcp_error( 'max_length_exceeded', sprintf( __( 'El parámetro %s excede la longitud máxima (%d).', 'wp-mcp-agent-bridge' ), $key, (int) $prop['maxLength'] ) );
				}
			} elseif ( 'integer' === $type ) {
				if ( ! is_int( $value ) && ! ( is_numeric( $value ) && (string) (int) $value === (string) $value ) ) {
					return self::mcp_error( 'invalid_type', sprintf( __( 'El parámetro %s debe ser un entero.', 'wp-mcp-agent-bridge' ), $key ) );
				}
				$int_val = (int) $value;
				if ( isset( $prop['minimum'] ) && $int_val < (int) $prop['minimum'] ) {
					return self::mcp_error( 'below_minimum', sprintf( __( 'El parámetro %s debe ser >= %d.', 'wp-mcp-agent-bridge' ), $key, (int) $prop['minimum'] ) );
				}
				if ( isset( $prop['maximum'] ) && $int_val > (int) $prop['maximum'] ) {
					return self::mcp_error( 'above_maximum', sprintf( __( 'El parámetro %s debe ser <= %d.', 'wp-mcp-agent-bridge' ), $key, (int) $prop['maximum'] ) );
				}
			} elseif ( 'boolean' === $type ) {
				if ( ! is_bool( $value ) ) {
					return self::mcp_error( 'invalid_type', sprintf( __( 'El parámetro %s debe ser booleano.', 'wp-mcp-agent-bridge' ), $key ) );
				}
			} elseif ( 'array' === $type && ! is_array( $value ) ) {
				return self::mcp_error( 'invalid_type', sprintf( __( 'El parámetro %s debe ser un arreglo.', 'wp-mcp-agent-bridge' ), $key ) );
			}
		}

		return true;
	}

	/**
	 * Sanitiza y limita el número de resultados paginados.
	 *
	 * @param mixed $value
	 * @param int   $default
	 * @param int   $max
	 * @return int
	 */
	public static function clamp_limit( $value, $default = 20, $max = 50 ) {
		$val = is_numeric( $value ) ? (int) $value : (int) $default;
		if ( $val < 1 ) {
			return 1;
		}
		return $val > $max ? $max : $val;
	}

	/**
	 * Sanitiza y devuelve la página de consulta (iniciando en 1).
	 *
	 * @param mixed $value
	 * @return int
	 */
	public static function clamp_page( $value ) {
		$val = is_numeric( $value ) ? (int) $value : 1;
		return $val < 1 ? 1 : $val;
	}

	/**
	 * Genera un error estandarizado WP_Error para respuestas MCP.
	 *
	 * @param string $code
	 * @param string $message
	 * @param int    $status
	 * @return WP_Error
	 */
	public static function mcp_error( $code, $message, $status = 400 ) {
		return new WP_Error( $code, $message, array( 'status' => (int) $status ) );
	}

	/**
	 * Registra un evento en el log rotativo en wp_options (autoload = false).
	 *
	 * @param string $tool    Nombre de la tool.
	 * @param bool   $success Si la ejecución fue exitosa.
	 * @param string $message Detalle o mensaje de error.
	 * @return void
	 */
	public static function log_activity( $tool, $success, $message = '' ) {
		$settings = self::get_settings();
		if ( empty( $settings['activity_log_enabled'] ) ) {
			return;
		}

		$log = get_option( self::LOG_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$user    = wp_get_current_user();
		$user_id = $user instanceof WP_User ? $user->ID : 0;

		$entry = array(
			'timestamp' => current_time( 'mysql' ),
			'tool'      => sanitize_key( $tool ),
			'success'   => (bool) $success,
			'user_id'   => (int) $user_id,
			'user_name' => $user instanceof WP_User && $user->ID ? $user->user_login : 'anonymous',
			'message'   => sanitize_text_field( substr( (string) $message, 0, 300 ) ),
		);

		array_unshift( $log, $entry );
		if ( count( $log ) > self::MAX_LOG_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );
		}

		update_option( self::LOG_KEY, $log, false );
	}
}
