<?php
/**
 * Herramientas del Núcleo de WordPress (Posts, Páginas, CPTs, Términos, Usuarios, Plugins).
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Tools_Core {

	/**
	 * Registra el catálogo de herramientas del núcleo.
	 *
	 * @return array
	 */
	public static function get_core_tools() {
		return array(
			// Información del sitio
			'get-site-info' => self::define_tool(
				'get-site-info',
				'Obtener información del sitio',
				'Devuelve nombre del sitio, URL, versión de WordPress, tema activo, plugins instalados y módulos MCP activos.',
				'core',
				true,
				array( __CLASS__, 'tool_get_site_info' ),
				array(),
				array(),
				array( 'cap' => 'edit_posts' )
			),

			// Listar Posts / Páginas / CPTs
			'list-posts' => self::define_tool(
				'list-posts',
				'Listar publicaciones',
				'Devuelve entradas, páginas o cualquier Custom Post Type con filtrado por tipo, estado, búsqueda y paginación.',
				'core',
				true,
				array( __CLASS__, 'tool_list_posts' ),
				array(
					'post_type' => self::schema_string( 'Tipo de publicación (post, page, o slug de CPT). Por defecto post.', 40 ),
					'status'    => self::schema_string( 'Estado (publish, draft, pending, private, any). Por defecto publish.', 20 ),
					'search'    => self::schema_string( 'Término de búsqueda.', 100 ),
					'limit'     => self::schema_int( 'Cantidad de elementos a retornar (1-50). Por defecto 20.', 1, 50 ),
					'page'      => self::schema_int( 'Número de página. Por defecto 1.', 1 ),
				),
				array(),
				array( 'cap' => 'edit_posts' )
			),

			// Obtener un Post específico
			'get-post' => self::define_tool(
				'get-post',
				'Obtener publicación',
				'Devuelve los detalles completos de una publicación por su ID, incluyendo contenido, metadatos y categorías.',
				'core',
				true,
				array( __CLASS__, 'tool_get_post' ),
				array(
					'id' => self::schema_int( 'ID de la publicación.', 1 ),
				),
				array( 'id' ),
				array( 'cap' => 'edit_posts' )
			),

			// Crear Post / Página
			'create-post' => self::define_tool(
				'create-post',
				'Crear publicación',
				'Crea una nueva entrada, página o CPT. Por defecto se crea como borrador a menos que se especifique publish.',
				'core',
				false,
				array( __CLASS__, 'tool_create_post' ),
				array(
					'title'     => self::schema_string( 'Título de la publicación.', 250 ),
					'content'   => self::schema_string( 'Cuerpo o contenido principal (HTML o Markdown).' ),
					'post_type' => self::schema_string( 'Tipo de publicación (post, page o CPT). Por defecto post.', 40 ),
					'status'    => self::schema_string( 'Estado (draft, publish, pending, private). Por defecto draft.', 20 ),
					'excerpt'   => self::schema_string( 'Extracto o resumen breve.', 500 ),
				),
				array( 'title' ),
				array( 'cap' => 'edit_posts' )
			),

			// Actualizar Post
			'update-post' => self::define_tool(
				'update-post',
				'Actualizar publicación',
				'Modifica los campos enviados de una publicación existente.',
				'core',
				false,
				array( __CLASS__, 'tool_update_post' ),
				array(
					'id'        => self::schema_int( 'ID de la publicación a actualizar.', 1 ),
					'title'     => self::schema_string( 'Nuevo título.', 250 ),
					'content'   => self::schema_string( 'Nuevo contenido.' ),
					'status'    => self::schema_string( 'Nuevo estado (publish, draft, pending, private).', 20 ),
					'excerpt'   => self::schema_string( 'Nuevo extracto.', 500 ),
				),
				array( 'id' ),
				array( 'cap' => 'edit_posts' )
			),

			// Eliminar Post
			'delete-post' => self::define_tool(
				'delete-post',
				'Eliminar publicación',
				'Envía una publicación a la papelera o la elimina de forma permanente.',
				'core',
				false,
				array( __CLASS__, 'tool_delete_post' ),
				array(
					'id'    => self::schema_int( 'ID de la publicación a eliminar.', 1 ),
					'force' => self::schema_bool( 'Si es true, borra permanentemente omitiendo la papelera. Por defecto false.' ),
				),
				array( 'id' ),
				array( 'cap' => 'delete_posts', 'destructive' => true )
			),

			// Listar Términos / Taxonomías
			'list-terms' => self::define_tool(
				'list-terms',
				'Listar términos de taxonomía',
				'Lista categorías, etiquetas o términos de una taxonomía personalizada.',
				'core',
				true,
				array( __CLASS__, 'tool_list_terms' ),
				array(
					'taxonomy' => self::schema_string( 'Slug de la taxonomía (category, post_tag, etc.). Por defecto category.', 40 ),
					'limit'    => self::schema_int( 'Límite de términos a retornar. Por defecto 20.', 1, 50 ),
				),
				array(),
				array( 'cap' => 'edit_posts' )
			),

			// Crear Término
			'create-term' => self::define_tool(
				'create-term',
				'Crear término',
				'Crea una categoría o etiqueta nueva en la taxonomía indicada.',
				'core',
				false,
				array( __CLASS__, 'tool_create_term' ),
				array(
					'name'        => self::schema_string( 'Nombre del término.', 100 ),
					'taxonomy'    => self::schema_string( 'Slug de la taxonomía (category, post_tag). Por defecto category.', 40 ),
					'description' => self::schema_string( 'Descripción opcional.', 300 ),
				),
				array( 'name' ),
				array( 'cap' => 'manage_categories' )
			),

			// Listar Plugins
			'list-plugins' => self::define_tool(
				'list-plugins',
				'Listar plugins',
				'Muestra los plugins instalados en el sitio con su versión, estado (activo/inactivo) y descripción.',
				'core',
				true,
				array( __CLASS__, 'tool_list_plugins' ),
				array(),
				array(),
				array( 'cap' => 'manage_options' )
			),
		);
	}

	/**
	 * Callback para get-site-info.
	 */
	public static function tool_get_site_info( $args ) {
		$theme = wp_get_theme();
		return array(
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
			'wp_version'   => get_bloginfo( 'version' ),
			'php_version'  => PHP_VERSION,
			'theme'        => array(
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
			),
			'is_multisite' => is_multisite(),
			'integrations' => array(
				'woocommerce' => class_exists( 'WooCommerce' ),
				'yoast_seo'   => defined( 'WPSEO_VERSION' ),
				'rank_math'   => class_exists( 'RankMath' ),
				'elementor'   => did_action( 'elementor/loaded' ),
			),
		);
	}

	/**
	 * Callback para list-posts.
	 */
	public static function tool_list_posts( $args ) {
		$post_type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';
		$status    = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'publish';
		$limit     = self::clamp_limit( isset( $args['limit'] ) ? $args['limit'] : 20 );
		$page      = self::clamp_page( isset( $args['page'] ) ? $args['page'] : 1 );
		$search    = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $limit,
			'paged'          => $page,
		);

		if ( 'publish' !== $status ) {
			if ( ! current_user_can( 'read_private_posts' ) && ( 'private' === $status || 'any' === $status ) ) {
				$query_args['post_status'] = 'publish';
			}
			if ( ! current_user_can( 'edit_others_posts' ) ) {
				$query_args['author'] = get_current_user_id();
			}
		}

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		$query = new WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $p ) {
			$items[] = array(
				'id'        => $p->ID,
				'title'     => get_the_title( $p ),
				'slug'      => $p->post_name,
				'status'    => $p->post_status,
				'date'      => $p->post_date,
				'url'       => get_permalink( $p ),
				'post_type' => $p->post_type,
			);
		}

		return array(
			'posts'       => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'page'        => $page,
		);
	}

	/**
	 * Callback para get-post.
	 */
	public static function tool_get_post( $args ) {
		$id   = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$post = get_post( $id );

		if ( ! $post ) {
			return self::mcp_error( 'post_not_found', __( 'Publicación no encontrada.', 'wp-mcp-agent-bridge' ) );
		}

		if ( ! current_user_can( 'read_post', $id ) || ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $id ) ) ) {
			return self::mcp_error( 'forbidden', __( 'No tienes permisos para leer esta publicación.', 'wp-mcp-agent-bridge' ), 403 );
		}

		return array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'content'   => $post->post_content,
			'excerpt'   => $post->post_excerpt,
			'slug'      => $post->post_name,
			'status'    => $post->post_status,
			'post_type' => $post->post_type,
			'author'    => get_the_author_meta( 'display_name', $post->post_author ),
			'date'      => $post->post_date,
			'url'       => get_permalink( $post ),
		);
	}

	/**
	 * Callback para create-post.
	 */
	public static function tool_create_post( $args ) {
		$title     = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '';
		$content   = isset( $args['content'] ) ? (string) $args['content'] : '';
		$post_type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';
		$status    = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'draft';
		$excerpt   = isset( $args['excerpt'] ) ? sanitize_textarea_field( $args['excerpt'] ) : '';

		if ( '' === $title ) {
			return self::mcp_error( 'missing_title', __( 'El título de la publicación es obligatorio.', 'wp-mcp-agent-bridge' ) );
		}

		if ( ! post_type_exists( $post_type ) ) {
			return self::mcp_error( 'invalid_post_type', __( 'El tipo de publicación especificado no existe.', 'wp-mcp-agent-bridge' ) );
		}

		$pto = get_post_type_object( $post_type );
		if ( ! $pto || ! current_user_can( $pto->cap->create_posts ) ) {
			return self::mcp_error( 'forbidden', __( 'No tienes permisos para crear este tipo de publicación.', 'wp-mcp-agent-bridge' ), 403 );
		}

		if ( 'publish' === $status && ! current_user_can( $pto->cap->publish_posts ) ) {
			return self::mcp_error( 'forbidden', __( 'No tienes permisos para publicar este tipo de contenido.', 'wp-mcp-agent-bridge' ), 403 );
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$content = wp_kses_post( $content );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_type'    => $post_type,
				'post_status'  => $status,
				'post_excerpt' => $excerpt,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return array(
			'id'      => $post_id,
			'title'   => $title,
			'status'  => $status,
			'url'     => get_permalink( $post_id ),
			'message' => __( 'Publicación creada exitosamente.', 'wp-mcp-agent-bridge' ),
		);
	}

	/**
	 * Callback para update-post.
	 */
	public static function tool_update_post( $args ) {
		$id   = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$post = get_post( $id );

		if ( ! $post ) {
			return self::mcp_error( 'post_not_found', __( 'Publicación no encontrada.', 'wp-mcp-agent-bridge' ) );
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			return self::mcp_error( 'forbidden', __( 'No tienes permisos para editar esta publicación.', 'wp-mcp-agent-bridge' ), 403 );
		}

		$update_data = array( 'ID' => $id );

		if ( isset( $args['title'] ) ) {
			$update_data['post_title'] = sanitize_text_field( $args['title'] );
		}
		if ( isset( $args['content'] ) ) {
			$raw_content                 = (string) $args['content'];
			$update_data['post_content'] = current_user_can( 'unfiltered_html' ) ? $raw_content : wp_kses_post( $raw_content );
		}
		if ( isset( $args['status'] ) ) {
			$new_status = sanitize_key( $args['status'] );
			$pto        = get_post_type_object( $post->post_type );
			if ( 'publish' === $new_status && $pto && ! current_user_can( $pto->cap->publish_posts ) ) {
				return self::mcp_error( 'forbidden', __( 'No tienes permisos para publicar esta entrada.', 'wp-mcp-agent-bridge' ), 403 );
			}
			$update_data['post_status'] = $new_status;
		}
		if ( isset( $args['excerpt'] ) ) {
			$update_data['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
		}

		$res = wp_update_post( $update_data, true );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return array(
			'id'      => $id,
			'title'   => get_the_title( $id ),
			'status'  => get_post_status( $id ),
			'url'     => get_permalink( $id ),
			'message' => __( 'Publicación actualizada exitosamente.', 'wp-mcp-agent-bridge' ),
		);
	}

	/**
	 * Callback para delete-post.
	 */
	public static function tool_delete_post( $args ) {
		$id    = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$force = ! empty( $args['force'] );
		$post  = get_post( $id );

		if ( ! $post ) {
			return self::mcp_error( 'post_not_found', __( 'Publicación no encontrada.', 'wp-mcp-agent-bridge' ) );
		}

		if ( ! current_user_can( 'delete_post', $id ) ) {
			return self::mcp_error( 'forbidden', __( 'No tienes permisos para eliminar esta publicación.', 'wp-mcp-agent-bridge' ), 403 );
		}

		$res = wp_delete_post( $id, $force );
		if ( ! $res ) {
			return self::mcp_error( 'delete_failed', __( 'No se pudo eliminar la publicación solicitada.', 'wp-mcp-agent-bridge' ) );
		}

		return array(
			'id'      => $id,
			'deleted' => true,
			'force'   => $force,
			'message' => $force ? __( 'Publicación eliminada permanentemente.', 'wp-mcp-agent-bridge' ) : __( 'Publicación enviada a la papelera.', 'wp-mcp-agent-bridge' ),
		);
	}

	/**
	 * Callback para list-terms.
	 */
	public static function tool_list_terms( $args ) {
		$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : 'category';
		$limit    = self::clamp_limit( isset( $args['limit'] ) ? $args['limit'] : 20 );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return self::mcp_error( 'invalid_taxonomy', __( 'La taxonomía especificada no existe.', 'wp-mcp-agent-bridge' ) );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => $limit,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$list = array();
		foreach ( $terms as $t ) {
			$list[] = array(
				'id'    => $t->term_id,
				'name'  => $t->name,
				'slug'  => $t->slug,
				'count' => $t->count,
			);
		}

		return array(
			'taxonomy' => $taxonomy,
			'terms'    => $list,
		);
	}

	/**
	 * Callback para create-term.
	 */
	public static function tool_create_term( $args ) {
		$name     = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
		$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : 'category';
		$desc     = isset( $args['description'] ) ? sanitize_text_field( $args['description'] ) : '';

		if ( '' === $name ) {
			return self::mcp_error( 'missing_term_name', __( 'El nombre del término es obligatorio.', 'wp-mcp-agent-bridge' ) );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return self::mcp_error( 'invalid_taxonomy', __( 'La taxonomía especificada no existe.', 'wp-mcp-agent-bridge' ) );
		}

		$res = wp_insert_term( $name, $taxonomy, array( 'description' => $desc ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return array(
			'term_id'  => $res['term_id'],
			'name'     => $name,
			'taxonomy' => $taxonomy,
			'message'  => __( 'Término creado exitosamente.', 'wp-mcp-agent-bridge' ),
		);
	}

	/**
	 * Callback para list-plugins.
	 */
	public static function tool_list_plugins( $args ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$output      = array();

		foreach ( $all_plugins as $file => $data ) {
			$output[] = array(
				'file'        => $file,
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'active'      => is_plugin_active( $file ),
				'description' => wp_strip_all_tags( $data['Description'] ),
			);
		}

		return array( 'plugins' => $output );
	}
}
