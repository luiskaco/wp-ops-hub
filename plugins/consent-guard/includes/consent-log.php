<?php
/**
 * Registro de consentimientos en tabla propia, solo inserción (ADR-008).
 *
 * @package ConsentGuard
 */

declare(strict_types=1);

namespace ConsentGuard;

defined( 'ABSPATH' ) || exit;

final class ConsentLog {

	public const string DB_VERSION        = '1';
	public const string DB_VERSION_OPTION = 'consent_guard_db_version';
	public const string CRON_HOOK         = 'consent_guard_prune_log';

	public const array ACTIONS = array( 'accept_all', 'reject_all', 'custom' );

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'consent_guard_log';
	}

	/**
	 * Crea o actualiza la tabla si cambió la versión del esquema.
	 */
	public static function maybeInstall(): void {
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		// dbDelta exige este formato exacto (dos espacios tras PRIMARY KEY, una columna por línea).
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			consent_id char(32) NOT NULL,
			created_at datetime NOT NULL,
			revision int(10) unsigned NOT NULL DEFAULT 1,
			action varchar(20) NOT NULL,
			categories varchar(100) NOT NULL DEFAULT '',
			ip_anon varchar(45) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY consent_id (consent_id),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql );
		// Con autoload: se lee en cada petición y así no cuesta una consulta extra.
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Guarda una elección. Los datos llegan ya validados por el esquema REST.
	 *
	 * @param list<Category> $categories Categorías aceptadas.
	 */
	public static function insert( string $consent_id, int $revision, string $action, array $categories, string $ip_anon ): bool {
		global $wpdb;

		$names = array_map( static fn ( Category $c ): string => $c->value, $categories );

		$result = $wpdb->insert(
			self::table(),
			array(
				'consent_id' => $consent_id,
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
				'revision'   => $revision,
				'action'     => $action,
				'categories' => implode( ',', $names ),
				'ip_anon'    => $ip_anon,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Página de registros, lo más reciente primero.
	 *
	 * @return list<object>
	 */
	public static function page( int $page, int $per_page = 50 ): array {
		global $wpdb;
		$table  = self::table();
		$offset = max( 0, ( $page - 1 ) * $per_page );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- el nombre de tabla no viene del usuario.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ) );

		return is_array( $rows ) ? $rows : array();
	}

	public static function count(): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Resumen de los últimos N días por tipo de acción.
	 *
	 * @return array<string, int>
	 */
	public static function summary( int $days = 30 ): array {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT action, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY action", $since ) );

		$summary = array_fill_keys( self::ACTIONS, 0 );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( isset( $summary[ $row->action ] ) ) {
				$summary[ $row->action ] = (int) $row->total;
			}
		}
		return $summary;
	}

	/**
	 * Borra los registros más antiguos que el plazo de retención. Lo llama el cron diario.
	 */
	public static function prune(): int {
		global $wpdb;
		$table  = self::table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - Settings::int( 'log_retention_days' ) * DAY_IN_SECONDS );

		// Por lotes para no bloquear la tabla en sitios grandes.
		$deleted = 0;
		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$batch    = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT 5000", $cutoff ) );
			$deleted += $batch;
		} while ( 5000 === $batch );

		return $deleted;
	}

	/**
	 * Envía el registro completo como CSV. Quien llama ya comprobó nonce y permisos.
	 */
	public static function streamCsv(): never {
		global $wpdb;
		$table = self::table();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="consent-log-' . gmdate( 'Y-m-d' ) . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'id', 'consent_id', 'created_at_utc', 'revision', 'action', 'categories', 'ip_anon' ), ',', '"', '' );

		$last_id = PHP_INT_MAX;
		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id < %d ORDER BY id DESC LIMIT 2000", $last_id ), ARRAY_A );
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				fputcsv( $out, array_map( array( self::class, 'csvCell' ), $row ), ',', '"', '' );
				$last_id = (int) $row['id'];
			}
		} while ( is_array( $rows ) && 2000 === count( $rows ) );

		fclose( $out );
		exit;
	}

	/**
	 * Evita la inyección de fórmulas al abrir el CSV en Excel o similares.
	 */
	public static function csvCell( mixed $value ): string {
		$value = (string) $value;
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
	}

	public static function scheduleCron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function dropTable(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::DB_VERSION_OPTION );
	}
}
