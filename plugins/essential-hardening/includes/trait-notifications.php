<?php
/**
 * Essential Hardening: Módulos 14, 15, 18 y 19: avisos por correo de actualizaciones pendientes, inicios de sesión de administradores, cambios de administradores y ráfagas de IPs bloqueadas.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 * Los métodos conservan visibilidad y comportamiento; solo cambia el archivo donde viven.
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Notifications {

	private function module_update_notifications(): void {
		// Programar evento cron diario si no existe
		if ( ! wp_next_scheduled( 'eh_daily_update_check_cron' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'eh_daily_update_check_cron' );
		}

		add_action( 'eh_daily_update_check_cron', array( $this, 'check_and_notify_updates' ) );

		// Enrutar correos de auto-actualización de WordPress al email configurado
		add_filter( 'auto_plugin_theme_update_email', array( $this, 'filter_auto_update_email' ), 10, 4 );
		add_filter( 'auto_core_update_email', array( $this, 'filter_auto_core_update_email' ), 10, 4 );

		// Procesar envío de correo de prueba manual desde el panel
		if ( is_admin() ) {
			add_action( 'admin_post_eh_test_update_email', array( $this, 'handle_test_update_email' ) );
		}
	}

	public function filter_auto_update_email( array $email, string $type, array $successful_updates, array $failed_updates ): array {
		if ( ! empty( $this->options['update_notifications'] ) && ! empty( $this->options['update_notify_email'] ) ) {
			if ( is_email( $this->options['update_notify_email'] ) ) {
				$email['to'] = $this->options['update_notify_email'];
			}
		}
		return $email;
	}

	public function filter_auto_core_update_email( array $email, string $type, $core_update, $result ): array {
		if ( ! empty( $this->options['update_notifications'] ) && ! empty( $this->options['update_notify_email'] ) ) {
			if ( is_email( $this->options['update_notify_email'] ) ) {
				$email['to'] = $this->options['update_notify_email'];
			}
		}
		return $email;
	}

	public function handle_test_update_email(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso no autorizado.', 'essential-hardening' ) );
		}

		check_admin_referer( 'eh_test_email_nonce' );

		$dummy_plugins = array(
			array(
				'name'            => 'Essential Hardening & Security (Prueba)',
				'current_version' => self::VERSION,
				'new_version'     => self::VERSION,
			),
		);

		$sent = $this->send_update_notification_email( $dummy_plugins, array(), false, '', true );

		$redirect_url = add_query_arg(
			array(
				'page'     => 'essential-hardening',
				'eh_email' => $sent ? 'sent' : 'failed',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function check_and_notify_updates(): void {
		if ( empty( $this->options['update_notifications'] ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'wp_get_update_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		wp_update_plugins();
		wp_update_themes();

		$pending_plugins = array();
		$update_plugins  = get_site_transient( 'update_plugins' );
		if ( ! empty( $update_plugins->response ) && is_array( $update_plugins->response ) ) {
			foreach ( $update_plugins->response as $plugin_file => $plugin_data ) {
				$plugin_full_path = WP_PLUGIN_DIR . '/' . $plugin_file;
				$current_version  = '';
				$plugin_name      = $plugin_file;

				if ( file_exists( $plugin_full_path ) ) {
					$info = get_plugin_data( $plugin_full_path, false, false );
					$plugin_name     = ! empty( $info['Name'] ) ? $info['Name'] : $plugin_file;
					$current_version = ! empty( $info['Version'] ) ? $info['Version'] : '';
				}

				$new_version = is_object( $plugin_data ) && isset( $plugin_data->new_version ) ? $plugin_data->new_version : ( is_array( $plugin_data ) && isset( $plugin_data['new_version'] ) ? $plugin_data['new_version'] : '' );

				$pending_plugins[] = array(
					'name'            => $plugin_name,
					'current_version' => $current_version,
					'new_version'     => $new_version,
				);
			}
		}

		$pending_themes = array();
		$update_themes  = get_site_transient( 'update_themes' );
		if ( ! empty( $update_themes->response ) && is_array( $update_themes->response ) ) {
			foreach ( $update_themes->response as $theme_slug => $theme_data ) {
				$theme           = wp_get_theme( $theme_slug );
				$theme_name      = $theme->exists() ? $theme->get( 'Name' ) : $theme_slug;
				$current_version = $theme->exists() ? $theme->get( 'Version' ) : '';
				$new_version     = is_object( $theme_data ) && isset( $theme_data->new_version ) ? $theme_data->new_version : ( is_array( $theme_data ) && isset( $theme_data['new_version'] ) ? $theme_data['new_version'] : '' );

				$pending_themes[] = array(
					'name'            => $theme_name,
					'current_version' => $current_version,
					'new_version'     => $new_version,
				);
			}
		}

		$pending_core = false;
		$core_version = '';
		$update_core  = get_site_transient( 'update_core' );
		if ( ! empty( $update_core->updates ) && is_array( $update_core->updates ) ) {
			foreach ( $update_core->updates as $core_update ) {
				if ( isset( $core_update->response ) && 'upgrade' === $core_update->response ) {
					$pending_core = true;
					$core_version = $core_update->current;
					break;
				}
			}
		}

		if ( empty( $pending_plugins ) && empty( $pending_themes ) && ! $pending_core ) {
			return;
		}

		$current_hash = md5( wp_json_encode( array( $pending_plugins, $pending_themes, $pending_core ) ) );
		$last_hash    = get_transient( 'eh_last_update_notice_hash' );
		if ( $current_hash === $last_hash ) {
			return;
		}

		$this->send_update_notification_email( $pending_plugins, $pending_themes, $pending_core, $core_version );
		set_transient( 'eh_last_update_notice_hash', $current_hash, 24 * HOUR_IN_SECONDS );
	}

	private function send_update_notification_email( array $plugins, array $themes, bool $core, string $core_version, bool $is_test = false ): bool {
		$recipient = ! empty( $this->options['update_notify_email'] ) ? sanitize_email( $this->options['update_notify_email'] ) : get_option( 'admin_email' );
		if ( ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

		$subject = sprintf(
			/* translators: %s: Nombre del sitio */
			__( '[Seguridad] Actualizaciones pendientes en %s', 'essential-hardening' ),
			$site_name
		);
		if ( $is_test ) {
			$subject = '[Prueba] ' . $subject;
		}

		$lists = array();
		if ( $core ) {
			$lists[] = array(
				'title' => __( 'Núcleo de WordPress', 'essential-hardening' ),
				/* translators: %s: versión */
				'items' => array( sprintf( __( 'Nueva versión disponible: WordPress %s', 'essential-hardening' ), $core_version ) ),
			);
		}
		$format = static function ( array $item ): string {
			return sprintf( '%s: %s → %s', $item['name'], $item['current_version'], $item['new_version'] );
		};
		if ( ! empty( $plugins ) ) {
			/* translators: %d: cantidad */
			$lists[] = array( 'title' => sprintf( __( 'Plugins (%d)', 'essential-hardening' ), count( $plugins ) ), 'items' => array_map( $format, $plugins ) );
		}
		if ( ! empty( $themes ) ) {
			/* translators: %d: cantidad */
			$lists[] = array( 'title' => sprintf( __( 'Temas (%d)', 'essential-hardening' ), count( $themes ) ), 'items' => array_map( $format, $themes ) );
		}

		return $this->send_template_email(
			$recipient,
			$subject,
			array(
				'level'   => 'warning',
				'heading' => $is_test ? __( 'Correo de prueba: actualizaciones pendientes', 'essential-hardening' ) : __( 'Hay actualizaciones pendientes', 'essential-hardening' ),
				/* translators: %s: nombre del sitio */
				'intro'   => sprintf( __( 'Essential Hardening detectó actualizaciones pendientes en %s.', 'essential-hardening' ), $site_name ),
				'lists'   => $lists,
				'button'  => array( 'label' => __( 'Ir a Actualizaciones', 'essential-hardening' ), 'url' => admin_url( 'update-core.php' ) ),
				'notes'   => array( __( 'Mantener plugins, temas y WordPress al día es la medida que más intrusiones evita.', 'essential-hardening' ) ),
				'reason'  => __( 'Recibís este correo porque están activados los avisos de actualizaciones.', 'essential-hardening' ),
			)
		);
	}

	private function module_admin_login_alerts(): void {
		add_action( 'wp_login', array( $this, 'notify_admin_login' ), 20, 2 );
	}

	public function notify_admin_login( string $user_login, $user ): void {
		if ( ! ( $user instanceof WP_User ) || ! $user->has_cap( 'manage_options' ) ) {
			return;
		}

		$ip         = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'Desconocido';
		$site_name  = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$datetime   = current_time( 'Y-m-d H:i:s' );

		$recipient = ! empty( $this->options['update_notify_email'] ) ? sanitize_email( $this->options['update_notify_email'] ) : $user->user_email;
		if ( ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		$subject = sprintf(
			/* translators: 1: Usuario, 2: Sitio */
			__( '[Alerta de Seguridad] Administrador conectado: %1$s en %2$s', 'essential-hardening' ),
			$user_login,
			$site_name
		);

		$this->send_template_email(
			$recipient,
			$subject,
			array(
				'level'   => 'info',
				'heading' => __( 'Un administrador inició sesión', 'essential-hardening' ),
				/* translators: %s: nombre del sitio */
				'intro'   => sprintf( __( 'Se inició sesión con una cuenta de Administrador en %s.', 'essential-hardening' ), $site_name ),
				'rows'    => array(
					array( __( 'Usuario', 'essential-hardening' ), $user_login ),
					array( __( 'Fecha y hora', 'essential-hardening' ), $datetime ),
					array( __( 'Dirección IP', 'essential-hardening' ), $ip ),
					array( __( 'Navegador', 'essential-hardening' ), $user_agent ),
				),
				'button'  => array( 'label' => __( 'No fui yo: cambiar contraseña', 'essential-hardening' ), 'url' => wp_lostpassword_url() ),
				'notes'   => array( __( 'Si fuiste vos, podés ignorar este correo.', 'essential-hardening' ) ),
				'reason'  => __( 'Recibís este correo porque están activadas las alertas de acceso de administradores.', 'essential-hardening' ),
			)
		);
	}

	private function module_admin_change_alerts(): void {
		add_action( 'user_register', array( $this, 'on_user_registered' ), 99, 1 );
		add_action( 'set_user_role', array( $this, 'on_user_role_set' ), 99, 3 );
		add_action( 'add_user_role', array( $this, 'on_user_role_added' ), 99, 2 );
	}

	public function on_user_registered( $user_id ): void {
		$user = get_userdata( (int) $user_id );
		if ( $user instanceof WP_User && in_array( 'administrator', (array) $user->roles, true ) ) {
			$this->notify_admin_change( $user, __( 'Se creó un usuario con rol Administrador', 'essential-hardening' ) );
		}
	}

	public function on_user_role_set( $user_id, $role, $old_roles ): void {
		if ( 'administrator' === $role && ! in_array( 'administrator', (array) $old_roles, true ) ) {
			$user = get_userdata( (int) $user_id );
			if ( $user instanceof WP_User ) {
				$this->notify_admin_change( $user, __( 'Un usuario fue promovido a Administrador', 'essential-hardening' ) );
			}
		}
	}

	public function on_user_role_added( $user_id, $role ): void {
		if ( 'administrator' === $role ) {
			$user = get_userdata( (int) $user_id );
			if ( $user instanceof WP_User ) {
				$this->notify_admin_change( $user, __( 'Se añadió el rol Administrador a un usuario', 'essential-hardening' ) );
			}
		}
	}

	private function notify_admin_change( WP_User $target, string $headline ): void {
		// Un mismo usuario no genera más de un aviso cada 5 minutos (crear + asignar rol dispara varios hooks)
		$dedupe_key = 'eh_admin_change_' . $target->ID;
		if ( get_transient( $dedupe_key ) ) {
			return;
		}
		set_transient( $dedupe_key, 1, 5 * MINUTE_IN_SECONDS );

		$actor      = wp_get_current_user();
		$actor_name = ( $actor instanceof WP_User && $actor->exists() ) ? $actor->user_login : __( 'ninguno (WP-CLI, tarea programada o petición sin sesión)', 'essential-hardening' );
		$site_name  = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$ip         = $this->get_client_ip();

		$this->log_security_event(
			'Cambio de Admin',
			$ip,
			/* translators: 1: usuario afectado, 2: usuario que hizo el cambio */
			sprintf( __( '%1$s es Administrador (cambio hecho por: %2$s)', 'essential-hardening' ), $target->user_login, $actor_name )
		);

		$subject = sprintf(
			/* translators: %s: nombre del sitio */
			__( '[Alerta de Seguridad] Cambio de administradores en %s', 'essential-hardening' ),
			$site_name
		);

		$this->send_template_email(
			$this->get_alert_recipient(),
			$subject,
			array(
				'level'   => 'alert',
				'heading' => $headline,
				/* translators: %s: nombre del sitio */
				'intro'   => sprintf( __( 'Hubo un cambio en los administradores de %s.', 'essential-hardening' ), $site_name ),
				'rows'    => array(
					array( __( 'Usuario afectado', 'essential-hardening' ), $target->user_login . ' (' . $target->user_email . ')' ),
					array( __( 'Cambio hecho por', 'essential-hardening' ), $actor_name ),
					array( __( 'Fecha y hora', 'essential-hardening' ), current_time( 'Y-m-d H:i:s' ) ),
					array( __( 'Dirección IP', 'essential-hardening' ), $ip ),
				),
				'button'  => array( 'label' => __( 'Revisar administradores', 'essential-hardening' ), 'url' => admin_url( 'users.php?role=administrator' ) ),
				'notes'   => array(
					__( 'Si lo hiciste vos, podés ignorar este correo.', 'essential-hardening' ),
					__( 'Si no reconocés el cambio, quitá ese usuario y cambiá las contraseñas de inmediato: crear un administrador oculto es típico de un atacante.', 'essential-hardening' ),
				),
				'reason'  => __( 'Recibís este correo porque están activadas las alertas de cambios de administradores.', 'essential-hardening' ),
			)
		);
	}
	/**
	 * Registra una IP recién bloqueada y avisa por correo si en la última hora
	 * se juntan varias IPs distintas bloqueadas (señal de ataque distribuido).
	 *
	 * Se llama desde el límite de intentos del login y desde el anti-recon.
	 */
	private function track_ip_block( string $ip, string $reason ): void {
		if ( empty( $this->options['ip_block_alerts'] ) || '' === $ip ) {
			return;
		}

		/**
		 * Cantidad de IPs distintas bloqueadas dentro de la ventana que dispara el aviso.
		 *
		 * @param int $threshold Por defecto 5. 0 desactiva el aviso.
		 */
		$threshold = absint( apply_filters( 'essential_hardening_ip_block_alert_threshold', 5 ) );
		if ( 0 === $threshold ) {
			return;
		}

		/**
		 * Segundos de la ventana en que se cuentan los bloqueos (y pausa mínima entre correos).
		 *
		 * @param int $window Por defecto 1 hora.
		 */
		$window = max( 5 * MINUTE_IN_SECONDS, absint( apply_filters( 'essential_hardening_ip_block_alert_window', HOUR_IN_SECONDS ) ) );
		$now    = time();

		$blocks = get_transient( 'eh_ip_block_window' );
		$blocks = is_array( $blocks ) ? $blocks : array();

		// Quitar bloqueos viejos y registrar el actual (una entrada por IP: cuenta IPs distintas)
		$blocks = array_filter(
			$blocks,
			static function ( $entry ) use ( $now, $window ) {
				return is_array( $entry ) && isset( $entry['time'] ) && ( $now - (int) $entry['time'] ) < $window;
			}
		);
		$blocks[ md5( $ip ) ] = array(
			'ip'     => $ip,
			'reason' => $reason,
			'time'   => $now,
		);
		// Tope de memoria ante ataques con miles de IPs
		if ( count( $blocks ) > 100 ) {
			$blocks = array_slice( $blocks, -100, null, true );
		}
		set_transient( 'eh_ip_block_window', $blocks, $window );

		if ( count( $blocks ) < $threshold || get_transient( 'eh_ip_block_alert_sent' ) ) {
			return;
		}

		// Un correo por ventana como máximo, aunque sigan llegando bloqueos
		set_transient( 'eh_ip_block_alert_sent', 1, $window );
		$this->send_ip_block_alert( $blocks, $window );
	}

	/**
	 * @param array<string, array{ip: string, reason: string, time: int}> $blocks
	 */
	private function send_ip_block_alert( array $blocks, int $window ): void {
		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$minutes   = (int) round( $window / MINUTE_IN_SECONDS );

		$subject = sprintf(
			/* translators: 1: cantidad de IPs, 2: nombre del sitio */
			__( '[Alerta de Seguridad] %1$d IPs bloqueadas en %2$s', 'essential-hardening' ),
			count( $blocks ),
			$site_name
		);

		$items = array();
		foreach ( $blocks as $entry ) {
			$items[] = sprintf( '%s — %s (%s)', $entry['ip'], $entry['reason'], wp_date( 'Y-m-d H:i:s', (int) $entry['time'] ) );
		}

		$this->send_template_email(
			$this->get_alert_recipient(),
			$subject,
			array(
				'level'   => 'alert',
				/* translators: %d: cantidad de IPs */
				'heading' => sprintf( __( '%d IPs bloqueadas en poco tiempo', 'essential-hardening' ), count( $blocks ) ),
				/* translators: 1: cantidad de IPs, 2: minutos, 3: nombre del sitio */
				'intro'   => sprintf( __( 'Essential Hardening bloqueó %1$d direcciones IP distintas en los últimos %2$d minutos en %3$s. Puede ser un ataque de fuerza bruta o un escaneo repartido entre varias IPs.', 'essential-hardening' ), count( $blocks ), $minutes, $site_name ),
				'lists'   => array( array( 'title' => __( 'IPs bloqueadas', 'essential-hardening' ), 'items' => $items ) ),
				'button'  => array( 'label' => __( 'Ver registro de eventos', 'essential-hardening' ), 'url' => admin_url( 'options-general.php?page=essential-hardening' ) ),
				'notes'   => array( __( 'Los bloqueos se levantan solos al cumplirse su duración. No vas a recibir otro aviso de este tipo durante la próxima hora.', 'essential-hardening' ) ),
				'reason'  => __( 'Recibís este correo porque está activada la alerta por ráfaga de IPs bloqueadas.', 'essential-hardening' ),
			)
		);
	}
}
