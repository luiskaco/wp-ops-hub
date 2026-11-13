<?php
/**
 * Herramientas de Medios y Subida de Imágenes para WP MCP Agent Bridge.
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Tools_Media {

	/**
	 * Tamaño máximo permitido para subida de imágenes (10 MB).
	 *
	 * @return int
	 */
	private static function max_upload_bytes() {
		return (int) apply_filters( 'wp_mcp_bridge_max_upload_bytes', 10 * 1024 * 1024 );
	}

	/**
	 * Tipos MIME de imagen seguros permitidos (excluye SVG/HTML activo).
	 *
	 * @return array
	 */
	private static function allowed_image_mimes() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'avif'         => 'image/avif',
		);
	}

	/**
	 * Registra el catálogo de herramientas de medios.
	 *
	 * @return array
	 */
	public static function get_media_tools() {
		return array(
			// Listar medios
			'list-media' => self::define_tool(
				'list-media',
				'Listar biblioteca de medios',
				'Devuelve archivos de la biblioteca de medios con filtrado por búsqueda y paginación.',
				'media',
				true,
				array( __CLASS__, 'tool_list_media' ),
				array(
					'search' => self::schema_string( 'Término de búsqueda.', 100 ),
					'limit'  => self::schema_int( 'Cantidad de elementos (1-50). Por defecto 20.', 1, 50 ),
					'page'   => self::schema_int( 'Número de página. Por defecto 1.', 1 ),
				),
				array(),
				array( 'cap' => 'upload_files' )
			),

			// Obtener un medio específico
			'get-media' => self::define_tool(
				'get-media',
				'Obtener archivo de medios',
				'Devuelve los metadatos completos de un archivo por su attachment ID, incluyendo URL, alt text y dimensiones.',
				'media',
				true,
				array( __CLASS__, 'tool_get_media' ),
				array(
					'id' => self::schema_int( 'ID del attachment.', 1 ),
				),
				array( 'id' ),
				array( 'cap' => 'upload_files' )
			),

			// Subir imagen desde URL
			'upload-media-url' => self::define_tool(
				'upload-media-url',
				'Subir imagen desde URL remota',
				'Descarga una imagen remota y la procesa en la biblioteca de medios nativa de WordPress con título y alt text.',
				'media',
				false,
				array( __CLASS__, 'tool_upload_media_url' ),
				array(
					'url'         => self::schema_string( 'URL pública (http o https) de la imagen a descargar.' ),
					'title'       => self::schema_string( 'Título opcional para el medio.', 200 ),
					'alt'         => self::schema_string( 'Texto alternativo (alt text) para accesibilidad y SEO.', 300 ),
					'description' => self::schema_string( 'Descripción o pie de foto opcional.', 500 ),
				),
				array( 'url' ),
				array( 'cap' => 'upload_files' )
			),

			// Subir imagen en Base64
			'upload-media-base64' => self::define_tool(
				'upload-media-base64',
				'Subir imagen en Base64',
				'Sube directamente un archivo de imagen codificado en Base64 a la biblioteca de medios.',
				'media',
				false,
				array( __CLASS__, 'tool_upload_media_base64' ),
				array(
					'base64_data' => self::schema_string( 'Cadena Base64 con el contenido del archivo (con o sin prefijo data:image/...).' ),
					'filename'    => self::schema_string( 'Nombre del archivo con extensión (ej: banner.png, producto.jpg).', 100 ),
					'title'       => self::schema_string( 'Título opcional.', 200 ),
					'alt'         => self::schema_string( 'Texto alternativo opcional.', 300 ),
				),
				array( 'base64_data', 'filename' ),
				array( 'cap' => 'upload_files' )
			),

			// Actualizar metadatos de medio
			'update-media' => self::define_tool(
				'update-media',
				'Actualizar metadatos de medio',
				'Actualiza el texto alternativo (alt), título o descripción de una imagen existente.',
				'media',
				false,
				array( __CLASS__, 'tool_update_media' ),
				array(
					'id'    => self::schema_int( 'ID del attachment a actualizar.', 1 ),
					'title' => self::schema_string( 'Nuevo título.', 200 ),
					'alt'   => self::schema_string( 'Nuevo texto alternativo (alt text).', 300 ),
				),
				array( 'id' ),
				array( 'cap' => 'upload_files' )
			),
		);
	}

	/**
	 * Callback para list-media.
	 */
	public static function tool_list_media( $args ) {
		$limit  = self::clamp_limit( isset( $args['limit'] ) ? $args['limit'] : 20 );
		$page   = self::clamp_page( isset( $args['page'] ) ? $args['page'] : 1 );
		$search = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'paged'          => $page,
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		$query = new WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $p ) {
			$items[] = array(
				'id'        => $p->ID,
				'title'     => $p->post_title,
				'mime_type' => $p->post_mime_type,
				'url'       => wp_get_attachment_url( $p->ID ),
				'alt'       => get_post_meta( $p->ID, '_wp_attachment_image_alt', true ),
				'date'      => $p->post_date,
			);
		}

		return array(
			'media' => $items,
			'total' => (int) $query->found_posts,
			'page'  => $page,
		);
	}

	/**
	 * Callback para get-media.
	 */
	public static function tool_get_media( $args ) {
		$id   = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$post = get_post( $id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return self::mcp_error( 'media_not_found', __( 'Archivo de medio no encontrado.', 'wp-mcp-agent-bridge' ) );
		}

		$meta = wp_get_attachment_metadata( $id );

		return array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'url'       => wp_get_attachment_url( $id ),
			'mime_type' => $post->post_mime_type,
			'alt'       => get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'width'     => isset( $meta['width'] ) ? $meta['width'] : null,
			'height'    => isset( $meta['height'] ) ? $meta['height'] : null,
			'date'      => $post->post_date,
		);
	}

	/**
	 * Callback para upload-media-url.
	 */
	public static function tool_upload_media_url( $args ) {
		$url = isset( $args['url'] ) ? esc_url_raw( (string) $args['url'] ) : '';
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return self::mcp_error( 'invalid_url', __( 'Debe proporcionar una URL válida HTTP o HTTPS.', 'wp-mcp-agent-bridge' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp_file = download_url( $url, 30 );
		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		$max_bytes = self::max_upload_bytes();
		if ( ! file_exists( $tmp_file ) || filesize( $tmp_file ) > $max_bytes ) {
			wp_delete_file( $tmp_file );
			return self::mcp_error( 'file_too_large', sprintf( __( 'El archivo supera el tamaño máximo permitido (%d MB).', 'wp-mcp-agent-bridge' ), (int) ( $max_bytes / 1048576 ) ) );
		}

		$path     = wp_parse_url( $url, PHP_URL_PATH );
		$filename = is_string( $path ) ? sanitize_file_name( basename( $path ) ) : '';
		if ( '' === $filename ) {
			$filename = 'image-' . time() . '.jpg';
		}

		$checked = wp_check_filetype_and_ext( $tmp_file, $filename, self::allowed_image_mimes() );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			wp_delete_file( $tmp_file );
			return self::mcp_error( 'disallowed_filetype', __( 'El archivo descargado no es una imagen válida permitida.', 'wp-mcp-agent-bridge' ) );
		}

		if ( ! empty( $checked['proper_filename'] ) ) {
			$filename = $checked['proper_filename'];
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		);

		$title         = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '';
		$attachment_id = media_handle_sideload( $file_array, 0, $title );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp_file );
			return $attachment_id;
		}

		if ( ! empty( $args['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ) );
		}
		if ( ! empty( $args['description'] ) ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => sanitize_textarea_field( $args['description'] ),
				)
			);
		}

		return array(
			'id'      => $attachment_id,
			'url'     => wp_get_attachment_url( $attachment_id ),
			'title'   => get_the_title( $attachment_id ),
			'alt'     => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'message' => __( 'Imagen descargada e incorporada a la biblioteca de medios.', 'wp-mcp-agent-bridge' ),
		);
	}

	/**
	 * Callback para upload-media-base64.
	 */
	public static function tool_upload_media_base64( $args ) {
		$raw_data = isset( $args['base64_data'] ) ? (string) $args['base64_data'] : '';
		$filename = isset( $args['filename'] ) ? sanitize_file_name( (string) $args['filename'] ) : '';

		if ( '' === $raw_data || '' === $filename ) {
			return self::mcp_error( 'missing_arguments', __( 'Los campos base64_data y filename son obligatorios.', 'wp-mcp-agent-bridge' ) );
		}

		// Remover encabezado data:image/...;base64, si viene presente
		if ( false !== strpos( $raw_data, ';base64,' ) ) {
			$parts    = explode( ';base64,', $raw_data, 2 );
			$raw_data = $parts[1];
		}

		$max_bytes = self::max_upload_bytes();
		if ( strlen( $raw_data ) > (int) ceil( $max_bytes * 1.37 ) ) {
			return self::mcp_error( 'file_too_large', sprintf( __( 'El payload Base64 supera el tamaño máximo permitido (%d MB).', 'wp-mcp-agent-bridge' ), (int) ( $max_bytes / 1048576 ) ) );
		}

		$binary = base64_decode( $raw_data, true );
		if ( false === $binary || '' === $binary ) {
			return self::mcp_error( 'invalid_base64', __( 'La cadena Base64 enviada es inválida.', 'wp-mcp-agent-bridge' ) );
		}

		if ( strlen( $binary ) > $max_bytes ) {
			return self::mcp_error( 'file_too_large', sprintf( __( 'El archivo decodificado supera el tamaño máximo permitido (%d MB).', 'wp-mcp-agent-bridge' ), (int) ( $max_bytes / 1048576 ) ) );
		}

		// Validar extensión y MIME permitido + firma real de imagen (magic bytes)
		$allowed     = self::allowed_image_mimes();
		$wp_filetype = wp_check_filetype( $filename, $allowed );
		if ( empty( $wp_filetype['ext'] ) || empty( $wp_filetype['type'] ) ) {
			return self::mcp_error( 'disallowed_filetype', __( 'Extensión de archivo no permitida.', 'wp-mcp-agent-bridge' ) );
		}

		$image_info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $binary ) : false;
		if ( ! is_array( $image_info ) || empty( $image_info['mime'] ) || ! in_array( $image_info['mime'], $allowed, true ) ) {
			return self::mcp_error( 'invalid_image_content', __( 'El contenido binario no corresponde a una imagen válida.', 'wp-mcp-agent-bridge' ) );
		}

		$upload = wp_upload_bits( $filename, null, $binary );
		if ( ! empty( $upload['error'] ) ) {
			return self::mcp_error( 'upload_failed', $upload['error'] );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$title = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : pathinfo( $filename, PATHINFO_FILENAME );

		$attachment_data = array(
			'post_mime_type' => $image_info['mime'],
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment_data, $upload['file'], 0 );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $upload['file'] );
			return $attachment_id;
		}

		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

		if ( ! empty( $args['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ) );
		}

		return array(
			'id'      => $attachment_id,
			'url'     => $upload['url'],
			'title'   => $title,
			'message' => __( 'Imagen subida correctamente desde Base64.', 'wp-mcp-agent-bridge' ),
		);
	}

	/**
	 * Callback para update-media.
	 */
	public static function tool_update_media( $args ) {
		$id   = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$post = get_post( $id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return self::mcp_error( 'media_not_found', __( 'Medio no encontrado.', 'wp-mcp-agent-bridge' ) );
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			return self::mcp_error( 'forbidden', __( 'No tienes permisos para editar este archivo de medios.', 'wp-mcp-agent-bridge' ), 403 );
		}

		if ( isset( $args['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => sanitize_text_field( $args['title'] ),
				)
			);
		}

		if ( isset( $args['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ) );
		}

		return array(
			'id'      => $id,
			'title'   => get_the_title( $id ),
			'alt'     => get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'message' => __( 'Metadatos de medio actualizados.', 'wp-mcp-agent-bridge' ),
		);
	}
}
