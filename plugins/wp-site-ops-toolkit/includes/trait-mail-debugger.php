<?php
/**
 * Módulo de Captura y Depuración de Correos (wp_mail Logger & Blackhole) para WP Site Ops Toolkit.
 *
 * @package WP_Site_Ops_Toolkit
 */

defined( 'ABSPATH' ) || exit;

trait WP_Site_Ops_Mail_Debugger {

	/**
	 * Clave para almacenar el log de correos en wp_options.
	 */
	public const MAIL_LOG_KEY = 'wp_site_ops_mail_log';

	/**
	 * Límite máximo de correos almacenados.
	 */
	public const MAX_MAIL_ENTRIES = 50;

	/**
	 * Registra los hooks para interceptar wp_mail.
	 *
	 * @return void
	 */
	public static function init_mail_debugger_hooks() {
		add_filter( 'wp_mail', array( __CLASS__, 'intercept_wp_mail' ), 999 );
		add_action( 'wp_mail_failed', array( __CLASS__, 'handle_mail_failed' ) );
	}

	/**
	 * Intercepta y registra cada correo saliente de WordPress.
	 *
	 * @param array $args Argumentos de wp_mail (to, subject, message, headers, attachments).
	 * @return array
	 */
	public static function intercept_wp_mail( $args ) {
		$settings = self::get_settings();

		if ( empty( $settings['mail_debugger_enabled'] ) ) {
			return $args;
		}

		$to          = isset( $args['to'] ) ? ( is_array( $args['to'] ) ? implode( ', ', $args['to'] ) : $args['to'] ) : '';
		$subject     = isset( $args['subject'] ) ? (string) $args['subject'] : '';
		$message     = isset( $args['message'] ) ? (string) $args['message'] : '';
		$headers     = isset( $args['headers'] ) ? ( is_array( $args['headers'] ) ? implode( "\n", $args['headers'] ) : (string) $args['headers'] ) : '';
		$attachments = isset( $args['attachments'] ) && is_array( $args['attachments'] ) ? count( $args['attachments'] ) : 0;

		$entry = array(
			'id'          => uniqid( 'mail_' ),
			'timestamp'   => current_time( 'mysql' ),
			'to'          => sanitize_text_field( $to ),
			'subject'     => sanitize_text_field( $subject ),
			'message'     => wp_kses_post( substr( $message, 0, 5000 ) ),
			'headers'     => sanitize_textarea_field( $headers ),
			'attachments' => (int) $attachments,
			'status'      => 'sent',
			'blackholed'  => false,
		);

		// Modo Agujero Negro (Blackhole / Simulación): Previene envíos reales en local o staging
		if ( ! empty( $settings['mail_blackhole_mode'] ) ) {
			$entry['status']     = 'simulated';
			$entry['blackholed'] = true;

			// Reemplaza el destinatario para neutralizar el envío sin generar un fatal error
			add_filter(
				'pre_wp_mail',
				function () {
					return true; // Retorna true indicando que el envío fue "manejado" sin invocar PHPMailer
				},
				10
			);
		}

		self::save_mail_log_entry( $entry );

		return $args;
	}

	/**
	 * Registra fallos si wp_mail genera un WP_Error.
	 *
	 * @param WP_Error $error
	 * @return void
	 */
	public static function handle_mail_failed( $error ) {
		$settings = self::get_settings();
		if ( empty( $settings['mail_debugger_enabled'] ) ) {
			return;
		}

		$log = get_option( self::MAIL_LOG_KEY, array() );
		if ( ! empty( $log ) && isset( $log[0] ) ) {
			$log[0]['status']    = 'failed';
			$log[0]['error_msg'] = $error instanceof WP_Error ? $error->get_error_message() : 'Error desconocido al enviar correo.';
			update_option( self::MAIL_LOG_KEY, $log, false );
		}
	}

	/**
	 * Guarda una entrada en el log rotativo en wp_options.
	 *
	 * @param array $entry
	 * @return void
	 */
	private static function save_mail_log_entry( $entry ) {
		$log = get_option( self::MAIL_LOG_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift( $log, $entry );
		if ( count( $log ) > self::MAX_MAIL_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_MAIL_ENTRIES );
		}

		update_option( self::MAIL_LOG_KEY, $log, false );
	}
}
