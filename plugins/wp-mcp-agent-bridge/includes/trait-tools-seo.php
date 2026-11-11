<?php
/**
 * Herramientas de SEO multigestor (Yoast SEO, Rank Math, AIOSEO, SEOPress).
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Tools_SEO {

	/**
	 * Registra herramientas de SEO si hay algún plugin de SEO activo.
	 *
	 * @return array
	 */
	public static function get_seo_tools() {
		$plugin = self::detect_seo_plugin();
		if ( ! $plugin ) {
			return array();
		}

		return array(
			// Obtener metadatos SEO
			'seo-get-meta' => self::define_tool(
				'seo-get-meta',
				sprintf( 'Obtener metadatos SEO (%s)', $plugin['label'] ),
				sprintf( 'Lee título SEO, meta descripción, focus keyword, canonical y robots configurados en %s para un post.', $plugin['label'] ),
				'seo',
				true,
				array( __CLASS__, 'tool_seo_get_meta' ),
				array(
					'post_id' => self::schema_int( 'ID de la entrada, página o producto.', 1 ),
				),
				array( 'post_id' ),
				array( 'cap' => 'edit_posts' )
			),

			// Actualizar metadatos SEO
			'seo-update-meta' => self::define_tool(
				'seo-update-meta',
				sprintf( 'Actualizar metadatos SEO (%s)', $plugin['label'] ),
				sprintf( 'Actualiza campos SEO en %s (título, meta descripción, focus keyword, noindex).', $plugin['label'] ),
				'seo',
				false,
				array( __CLASS__, 'tool_seo_update_meta' ),
				array(
					'post_id'       => self::schema_int( 'ID de la publicación a optimizar.', 1 ),
					'title'         => self::schema_string( 'Título SEO personalizado.', 150 ),
					'description'   => self::schema_string( 'Meta descripción SEO.', 300 ),
					'focus_keyword' => self::schema_string( 'Palabra clave objetivo.', 100 ),
					'canonical'     => self::schema_string( 'URL canónica personalizada.' ),
					'noindex'       => self::schema_bool( 'Forzar noindex en la publicación.' ),
				),
				array( 'post_id' ),
				array( 'cap' => 'edit_posts' )
			),
		);
	}

	/**
	 * Detecta el plugin de SEO activo.
	 *
	 * @return array|null
	 */
	private static function detect_seo_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return array(
				'slug'   => 'yoast',
				'label'  => 'Yoast SEO',
				'fields' => array(
					'title'         => '_yoast_wpseo_title',
					'description'   => '_yoast_wpseo_metadesc',
					'focus_keyword' => '_yoast_wpseo_focuskw',
					'canonical'     => '_yoast_wpseo_canonical',
					'noindex'       => '_yoast_wpseo_meta-robots-noindex',
				),
			);
		}

		if ( class_exists( 'RankMath' ) ) {
			return array(
				'slug'   => 'rankmath',
				'label'  => 'Rank Math',
				'fields' => array(
					'title'         => 'rank_math_title',
					'description'   => 'rank_math_description',
					'focus_keyword' => 'rank_math_focus_keyword',
					'canonical'     => 'rank_math_canonical_url',
					'noindex'       => 'rank_math_robots',
				),
			);
		}

		if ( defined( 'AIOSEO_VERSION' ) ) {
			return array(
				'slug'   => 'aioseo',
				'label'  => 'All in One SEO',
				'fields' => array(
					'title'         => '_aioseo_title',
					'description'   => '_aioseo_description',
					'focus_keyword' => '_aioseo_keywords',
					'canonical'     => '_aioseo_canonical_url',
				),
			);
		}

		return null;
	}

	/**
	 * Callback para seo-get-meta.
	 */
	public static function tool_seo_get_meta( $args ) {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return self::mcp_error( 'post_not_found', 'Publicación no encontrada.' );
		}

		$plugin = self::detect_seo_plugin();
		$meta   = array(
			'post_id' => $post_id,
			'plugin'  => $plugin['label'],
		);

		foreach ( $plugin['fields'] as $key => $meta_key ) {
			$meta[ $key ] = get_post_meta( $post_id, $meta_key, true );
		}

		return $meta;
	}

	/**
	 * Callback para seo-update-meta.
	 */
	public static function tool_seo_update_meta( $args ) {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return self::mcp_error( 'post_not_found', 'Publicación no encontrada.' );
		}

		$plugin = self::detect_seo_plugin();
		$fields = $plugin['fields'];

		if ( isset( $args['title'] ) && isset( $fields['title'] ) ) {
			update_post_meta( $post_id, $fields['title'], sanitize_text_field( $args['title'] ) );
		}
		if ( isset( $args['description'] ) && isset( $fields['description'] ) ) {
			update_post_meta( $post_id, $fields['description'], sanitize_textarea_field( $args['description'] ) );
		}
		if ( isset( $args['focus_keyword'] ) && isset( $fields['focus_keyword'] ) ) {
			update_post_meta( $post_id, $fields['focus_keyword'], sanitize_text_field( $args['focus_keyword'] ) );
		}
		if ( isset( $args['canonical'] ) && isset( $fields['canonical'] ) ) {
			update_post_meta( $post_id, $fields['canonical'], esc_url_raw( $args['canonical'] ) );
		}
		if ( isset( $args['noindex'] ) && isset( $fields['noindex'] ) ) {
			$noindex_val = $args['noindex'] ? '1' : '0';
			update_post_meta( $post_id, $fields['noindex'], $noindex_val );
		}

		return array(
			'post_id' => $post_id,
			'plugin'  => $plugin['label'],
			'message' => 'Metadatos de SEO actualizados exitosamente.',
		);
	}
}
