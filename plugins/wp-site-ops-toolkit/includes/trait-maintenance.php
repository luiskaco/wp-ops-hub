<?php
/**
 * Módulo de Modo Mantenimiento y Staging Gate para WP Site Ops Toolkit.
 *
 * @package WP_Site_Ops_Toolkit
 */

defined( 'ABSPATH' ) || exit;

trait WP_Site_Ops_Maintenance {

	/**
	 * Nombre de la cookie para bypass por clave secreta.
	 */
	public const BYPASS_COOKIE = 'ops_staging_bypass';

	/**
	 * Inicializa los hooks del módulo de mantenimiento.
	 *
	 * @return void
	 */
	public static function init_maintenance_hooks() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_maintenance_mode' ), 1 );
		add_action( 'init', array( __CLASS__, 'handle_secret_bypass_cookie' ) );
	}

	/**
	 * Verifica si la URL trae el parámetro de bypass secreto y fija la cookie temporal.
	 *
	 * @return void
	 */
	public static function handle_secret_bypass_cookie() {
		$settings = self::get_settings();
		$secret   = ! empty( $settings['maintenance_secret_key'] ) ? trim( $settings['maintenance_secret_key'] ) : '';

		if ( '' !== $secret && isset( $_GET['ops_preview'] ) && hash_equals( $secret, (string) $_GET['ops_preview'] ) ) {
			// Fija la cookie de acceso temporal durante 24 horas
			setcookie( self::BYPASS_COOKIE, md5( $secret . wp_salt( 'auth' ) ), time() + 86400, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

			// Redirige limpiando el parámetro de la URL
			$clean_url = remove_query_arg( 'ops_preview' );
			wp_safe_redirect( $clean_url );
			exit;
		}
	}

	/**
	 * Intercepta peticiones públicas y sirve pantalla 503 si el mantenimiento está activo.
	 *
	 * @return void
	 */
	public static function handle_maintenance_mode() {
		$settings = self::get_settings();

		if ( empty( $settings['maintenance_enabled'] ) ) {
			return;
		}

		// 1. Permitir acceso a usuarios logueados con permisos
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// 2. Permitir peticiones a la pantalla de login de WordPress
		$login_url = wp_login_url();
		$path      = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( false !== strpos( $path, 'wp-login.php' ) ) {
			return;
		}

		// 3. Permitir peticiones REST API autenticadas o cron
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		// 4. Validar lista blanca de IPs
		$client_ip = self::get_client_ip();
		$whitelist = ! empty( $settings['maintenance_ip_whitelist'] ) ? explode( "\n", str_replace( "\r", '', $settings['maintenance_ip_whitelist'] ) ) : array();
		$whitelist = array_filter( array_map( 'trim', $whitelist ) );

		if ( in_array( $client_ip, $whitelist, true ) ) {
			return;
		}

		// 5. Validar cookie de acceso secreto
		$secret = ! empty( $settings['maintenance_secret_key'] ) ? trim( $settings['maintenance_secret_key'] ) : '';
		if ( '' !== $secret && isset( $_COOKIE[ self::BYPASS_COOKIE ] ) ) {
			$expected = md5( $secret . wp_salt( 'auth' ) );
			if ( hash_equals( $expected, (string) $_COOKIE[ self::BYPASS_COOKIE ] ) ) {
				return;
			}
		}

		// Servir respuesta HTTP 503 Service Unavailable
		$title   = ! empty( $settings['maintenance_title'] ) ? $settings['maintenance_title'] : __( 'Sitio en mantenimiento', 'wp-site-ops-toolkit' );
		$message = ! empty( $settings['maintenance_message'] ) ? $settings['maintenance_message'] : __( 'Estamos realizando tareas de mantenimiento programadas. Volveremos enseguida.', 'wp-site-ops-toolkit' );

		nocache_headers();
		status_header( 503 );
		header( 'Retry-After: 3600' );
		header( 'Content-Type: text/html; charset=utf-8' );

		self::render_maintenance_template( $title, $message );
		exit;
	}

	/**
	 * Renderiza una plantilla HTML responsive y moderna para el modo mantenimiento.
	 *
	 * @param string $title
	 * @param string $message
	 * @return void
	 */
	private static function render_maintenance_template( $title, $message ) {
		$site_name = get_bloginfo( 'name' );
		?>
		<!DOCTYPE html>
		<html lang="<?php echo esc_attr( get_locale() ); ?>">
		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $title ); ?> &mdash; <?php echo esc_html( $site_name ); ?></title>
			<style>
				* { box-sizing: border-box; }
				body {
					margin: 0;
					padding: 0;
					min-height: 100vh;
					display: flex;
					align-items: center;
					justify-content: center;
					background: #f8fafc;
					color: #1e293b;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				}
				.maint-container {
					max-width: 580px;
					width: 90%;
					background: #ffffff;
					border: 1px solid #e2e8f0;
					border-radius: 12px;
					padding: 40px;
					text-align: center;
					box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
				}
				.maint-icon {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					width: 64px;
					height: 64px;
					border-radius: 50%;
					background: #f1f5f9;
					margin-bottom: 24px;
					font-size: 32px;
				}
				h1 {
					margin: 0 0 16px;
					font-size: 24px;
					font-weight: 700;
					color: #0f172a;
				}
				p {
					margin: 0 0 24px;
					font-size: 15px;
					line-height: 1.6;
					color: #475569;
				}
				.maint-footer {
					font-size: 12px;
					color: #94a3b8;
					border-top: 1px solid #f1f5f9;
					padding-top: 20px;
				}
			</style>
		</head>
		<body>
			<div class="maint-container">
				<div class="maint-icon">🛠️</div>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo nl2br( esc_html( $message ) ); ?></p>
				<div class="maint-footer">
					&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?> &bull; <?php esc_html_e( 'Modo mantenimiento activo', 'wp-site-ops-toolkit' ); ?>
				</div>
			</div>
		</body>
		</html>
		<?php
	}
}
