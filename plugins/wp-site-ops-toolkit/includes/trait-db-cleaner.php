<?php
/**
 * Módulo de Limpieza y Optimización de Base de Datos para WP Site Ops Toolkit.
 *
 * @package WP_Site_Ops_Toolkit
 */

defined( 'ABSPATH' ) || exit;

trait WP_Site_Ops_Db_Cleaner {

	/**
	 * Obtiene las métricas de elementos limpiables en la base de datos.
	 *
	 * @return array
	 */
	public static function get_cleanup_counts() {
		global $wpdb;

		// 1. Transients expirados
		$expired_transients = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);

		// 2. Revisiones de posts
		$revisions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
				'revision'
			)
		);

		// 3. Entradas y páginas en la papelera
		$trashed_posts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s",
				'trash'
			)
		);

		// 4. Comentarios de spam y papelera
		$spam_comments = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN (%s, %s)",
				'spam',
				'trash'
			)
		);

		// 5. Metadatos huérfanos (postmeta sin post padre)
		$orphan_postmeta = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
		);

		return array(
			'transients'      => (int) $expired_transients,
			'revisions'       => (int) $revisions,
			'trashed_posts'   => (int) $trashed_posts,
			'spam_comments'   => (int) $spam_comments,
			'orphan_postmeta' => (int) $orphan_postmeta,
		);
	}

	/**
	 * Ejecuta la limpieza de las áreas seleccionadas.
	 *
	 * @param array $targets Lista de claves a limpiar.
	 * @return array Resultados de elementos eliminados por categoría.
	 */
	public static function execute_cleanup( $targets = array() ) {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'error' => 'Permisos insuficientes.' );
		}

		$results = array();

		// Limpieza de Transients Expirados
		if ( in_array( 'transients', $targets, true ) ) {
			// Obtener claves expiradas
			$keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
					$wpdb->esc_like( '_transient_timeout_' ) . '%',
					time()
				)
			);

			$count = 0;
			foreach ( $keys as $timeout_key ) {
				$transient_key = str_replace( '_transient_timeout_', '', $timeout_key );
				delete_transient( $transient_key );
				$count++;
			}
			$results['transients'] = $count;
		}

		// Limpieza de Revisiones de Posts
		if ( in_array( 'revisions', $targets, true ) ) {
			$revision_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
					'revision'
				)
			);

			$count = 0;
			foreach ( $revision_ids as $rev_id ) {
				wp_delete_post_revision( (int) $rev_id );
				$count++;
			}
			$results['revisions'] = $count;
		}

		// Limpieza de Papelera de Posts
		if ( in_array( 'trashed_posts', $targets, true ) ) {
			$trashed_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = %s",
					'trash'
				)
			);

			$count = 0;
			foreach ( $trashed_ids as $post_id ) {
				wp_delete_post( (int) $post_id, true );
				$count++;
			}
			$results['trashed_posts'] = $count;
		}

		// Limpieza de Spam y Comentarios en Papelera
		if ( in_array( 'spam_comments', $targets, true ) ) {
			$comment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN (%s, %s)",
					'spam',
					'trash'
				)
			);

			$count = 0;
			foreach ( $comment_ids as $c_id ) {
				wp_delete_comment( (int) $c_id, true );
				$count++;
			}
			$results['spam_comments'] = $count;
		}

		// Limpieza de Metadatos Huérfanos
		if ( in_array( 'orphan_postmeta', $targets, true ) ) {
			$deleted = $wpdb->query(
				"DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL"
			);
			$results['orphan_postmeta'] = false !== $deleted ? (int) $deleted : 0;
		}

		return $results;
	}
}
