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
