<?php
/**
 * Essential Hardening: plantilla HTML común para todos los correos del plugin.
 *
 * Cada aviso describe su contenido como datos (título, filas, listas, botón) y este trait
 * arma la versión HTML (estilos en línea y tablas, compatibles con Gmail y Outlook) y la
 * versión de texto plano que viaja junto a ella. Todo dato variable se escapa aquí.
 *
 * Este archivo es parte de la clase Essential_Hardening (se incorpora con `use`).
 */

defined( 'ABSPATH' ) || exit;

trait Essential_Hardening_Email {

	/**
	 * Envía un correo con la plantilla del plugin.
	 *
	 * @param string|array $to      Destinatario.
	 * @param string       $subject Asunto.
	 * @param array        $mail {
	 *     @type string $level    alert | warning | info. Define el color y la etiqueta.
	 *     @type string $heading  Título dentro del correo.
	 *     @type string $greeting Saludo (opcional, por defecto "Hola,").
	 *     @type string $intro    Párrafo inicial.
	 *     @type array  $rows     Pares [etiqueta, valor] mostrados como tabla.
	 *     @type array  $lists    Listas: [ 'title' => string, 'items' => string[], 'more' => int ].
	 *     @type string $code     Código destacado (2FA).
	 *     @type array  $button   [ 'label' => string, 'url' => string ].
	 *     @type array  $notes    Párrafos finales (qué hacer).
	 *     @type string $reason   Por qué llega este correo (pie).
	 * }
	 */
	private function send_template_email( $to, string $subject, array $mail ): bool {
		$html = $this->render_email_html( $mail );
		$text = $this->render_email_text( $mail );

		// Versión de texto plano para clientes sin HTML (multipart/alternative)
		$set_alt = static function ( $phpmailer ) use ( $text ) {
			$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		};
		add_action( 'phpmailer_init', $set_alt );
		$sent = $this->safe_wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		remove_action( 'phpmailer_init', $set_alt );

		return $sent;
	}

	/** Colores y etiqueta por nivel de gravedad. */
	private function get_email_level( string $level ): array {
		$levels = array(
			'alert'   => array( 'color' => '#a3173f', 'soft' => '#fde8ee', 'label' => __( 'Alerta de seguridad', 'essential-hardening' ) ),
			'warning' => array( 'color' => '#8a5a00', 'soft' => '#fff4d6', 'label' => __( 'Aviso', 'essential-hardening' ) ),
			'info'    => array( 'color' => '#0f766e', 'soft' => '#ccfbf1', 'label' => __( 'Información', 'essential-hardening' ) ),
		);
		return $levels[ $level ] ?? $levels['info'];
	}

	private function render_email_html( array $mail ): string {
		$level     = $this->get_email_level( (string) ( $mail['level'] ?? 'info' ) );
		$site_name = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
		$font      = "font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
		$ink       = '#1f2430';
		$muted     = '#5b6475';
		$line      = '#e4e7ee';

		$h  = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
		$h .= '<title>' . esc_html( (string) ( $mail['heading'] ?? '' ) ) . '</title></head>';
		$h .= '<body style="margin:0;padding:0;background:#f6f7fb;' . $font . '">';
		$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f6f7fb;"><tr><td align="center" style="padding:24px 12px;">';
		$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border:1px solid ' . $line . ';border-radius:12px;overflow:hidden;">';

		// Encabezado
		$h .= '<tr><td style="background:#0f766e;padding:20px 28px;">';
		$h .= '<div style="' . $font . 'font-size:13px;color:#ccfbf1;letter-spacing:.4px;">Essential Hardening &amp; Security</div>';
		$h .= '<div style="' . $font . 'font-size:18px;font-weight:700;color:#ffffff;margin-top:4px;">' . esc_html( $site_name ) . '</div>';
		$h .= '</td></tr>';

		// Etiqueta de gravedad y título
		$h .= '<tr><td style="padding:24px 28px 8px;">';
		$h .= '<span style="display:inline-block;' . $font . 'font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:' . $level['color'] . ';background:' . $level['soft'] . ';padding:4px 10px;border-radius:999px;">' . esc_html( $level['label'] ) . '</span>';
		$h .= '<h1 style="' . $font . 'font-size:21px;line-height:1.35;color:' . $ink . ';margin:14px 0 0;">' . esc_html( (string) ( $mail['heading'] ?? '' ) ) . '</h1>';
		$h .= '</td></tr>';

		$h .= '<tr><td style="padding:8px 28px 4px;' . $font . 'font-size:15px;line-height:1.6;color:' . $ink . ';">';
		$h .= '<p style="margin:0 0 12px;">' . esc_html( (string) ( $mail['greeting'] ?? __( 'Hola,', 'essential-hardening' ) ) ) . '</p>';
		if ( ! empty( $mail['intro'] ) ) {
			$h .= '<p style="margin:0 0 16px;">' . esc_html( (string) $mail['intro'] ) . '</p>';
		}

		// Código destacado (2FA)
		if ( ! empty( $mail['code'] ) ) {
			$h .= '<div style="text-align:center;margin:8px 0 20px;"><span style="display:inline-block;font-family:Consolas,Menlo,monospace;font-size:32px;font-weight:700;letter-spacing:8px;color:' . $ink . ';background:#f6f7fb;border:1px solid ' . $line . ';border-radius:10px;padding:14px 22px;">' . esc_html( (string) $mail['code'] ) . '</span></div>';
		}

		// Tabla de datos
		if ( ! empty( $mail['rows'] ) ) {
			$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid ' . $line . ';border-radius:8px;border-collapse:separate;margin:0 0 16px;">';
			foreach ( $mail['rows'] as $i => $row ) {
				$border = $i > 0 ? 'border-top:1px solid ' . $line . ';' : '';
				$h     .= '<tr>';
				$h     .= '<td style="' . $border . 'padding:10px 12px;' . $font . 'font-size:13px;color:' . $muted . ';width:38%;vertical-align:top;">' . esc_html( (string) $row[0] ) . '</td>';
				$h     .= '<td style="' . $border . 'padding:10px 12px;' . $font . 'font-size:14px;color:' . $ink . ';word-break:break-word;">' . esc_html( (string) $row[1] ) . '</td>';
				$h     .= '</tr>';
			}
			$h .= '</table>';
		}

		// Listas (IPs, plugins, archivos)
		foreach ( (array) ( $mail['lists'] ?? array() ) as $list ) {
			$h .= '<p style="margin:16px 0 6px;font-size:13px;font-weight:700;color:' . $ink . ';text-transform:uppercase;letter-spacing:.3px;">' . esc_html( (string) $list['title'] ) . '</p>';
			$h .= '<ul style="margin:0 0 12px;padding:0 0 0 20px;">';
			foreach ( (array) $list['items'] as $item ) {
				$h .= '<li style="margin:0 0 4px;font-size:14px;color:' . $ink . ';word-break:break-word;">' . esc_html( (string) $item ) . '</li>';
			}
			if ( ! empty( $list['more'] ) ) {
				/* translators: %d: elementos no listados */
				$h .= '<li style="margin:0;font-size:14px;color:' . $muted . ';">' . esc_html( sprintf( __( '… y %d más', 'essential-hardening' ), (int) $list['more'] ) ) . '</li>';
			}
			$h .= '</ul>';
		}

		// Botón de acción
		if ( ! empty( $mail['button']['url'] ) ) {
			$h .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:12px 0 18px;"><tr><td style="background:#0f766e;border-radius:8px;">';
			$h .= '<a href="' . esc_url( (string) $mail['button']['url'] ) . '" style="display:inline-block;padding:12px 22px;' . $font . 'font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;">' . esc_html( (string) $mail['button']['label'] ) . '</a>';
			$h .= '</td></tr></table>';
		}

		foreach ( (array) ( $mail['notes'] ?? array() ) as $note ) {
			$h .= '<p style="margin:0 0 10px;font-size:14px;color:' . $muted . ';">' . esc_html( (string) $note ) . '</p>';
		}
		$h .= '</td></tr>';

		// Pie
		$h .= '<tr><td style="padding:16px 28px 22px;border-top:1px solid ' . $line . ';' . $font . 'font-size:12px;line-height:1.5;color:' . $muted . ';">';
		if ( ! empty( $mail['reason'] ) ) {
			$h .= '<p style="margin:0 0 6px;">' . esc_html( (string) $mail['reason'] ) . '</p>';
		}
		$h .= '<p style="margin:0;">' . esc_html__( 'Enviado por Essential Hardening & Security. Los avisos se activan o desactivan en Ajustes → Essential Hardening.', 'essential-hardening' ) . '</p>';
		$h .= '</td></tr>';

		$h .= '</table></td></tr></table></body></html>';
		return $h;
	}

	private function render_email_text( array $mail ): string {
		$level = $this->get_email_level( (string) ( $mail['level'] ?? 'info' ) );
		$t     = '[' . $level['label'] . '] ' . ( $mail['heading'] ?? '' ) . "\n\n";
		$t    .= ( $mail['greeting'] ?? __( 'Hola,', 'essential-hardening' ) ) . "\n\n";
		if ( ! empty( $mail['intro'] ) ) {
			$t .= $mail['intro'] . "\n\n";
		}
		if ( ! empty( $mail['code'] ) ) {
			$t .= '    ' . $mail['code'] . "\n\n";
		}
		foreach ( (array) ( $mail['rows'] ?? array() ) as $row ) {
			$t .= '● ' . $row[0] . ': ' . $row[1] . "\n";
		}
		if ( ! empty( $mail['rows'] ) ) {
			$t .= "\n";
		}
		foreach ( (array) ( $mail['lists'] ?? array() ) as $list ) {
			$t .= $list['title'] . ":\n";
			foreach ( (array) $list['items'] as $item ) {
				$t .= '  - ' . $item . "\n";
			}
			if ( ! empty( $list['more'] ) ) {
				$t .= sprintf( "  … y %d más\n", (int) $list['more'] );
			}
			$t .= "\n";
		}
		if ( ! empty( $mail['button']['url'] ) ) {
			$t .= $mail['button']['label'] . ': ' . $mail['button']['url'] . "\n\n";
		}
		foreach ( (array) ( $mail['notes'] ?? array() ) as $note ) {
			$t .= $note . "\n";
		}
		$t .= "\n--\n" . ( empty( $mail['reason'] ) ? '' : $mail['reason'] . "\n" ) . 'Essential Hardening & Security';
		return wp_specialchars_decode( $t, ENT_QUOTES );
	}
}
