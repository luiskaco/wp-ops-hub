<?php
/**
 * Interfaz administrativa de WP Assets Pulse.
 *
 * @package WP_Assets_Pulse
 */

defined( 'ABSPATH' ) || exit;

trait WP_Assets_Pulse_Admin {

	/**
	 * Inicializa el menú y registro de opciones.
	 *
	 * @return void
	 */
	public static function init_admin() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_admin_settings' ) );
	}

	/**
	 * Agrega la página bajo Ajustes.
	 *
	 * @return void
	 */
	public static function register_admin_menu() {
		add_options_page(
			__( 'Assets Pulse', 'wp-assets-pulse' ),
			__( 'Assets Pulse', 'wp-assets-pulse' ),
			'manage_options',
			'wp-assets-pulse',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Registra los ajustes de la opción en WordPress.
	 *
	 * @return void
	 */
	public static function register_admin_settings() {
		register_setting(
			'wp_assets_pulse_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitiza las opciones enviadas desde el formulario.
	 *
	 * @param array $input
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$clean = array();

		$clean['disable_emojis']        = ! empty( $input['disable_emojis'] );
		$clean['disable_block_css']     = ! empty( $input['disable_block_css'] );
		$clean['disable_global_styles'] = ! empty( $input['disable_global_styles'] );

		$clean['defer_js_enabled']      = ! empty( $input['defer_js_enabled'] );
		$clean['defer_js_exclusions']   = ! empty( $input['defer_js_exclusions'] ) ? sanitize_textarea_field( $input['defer_js_exclusions'] ) : '';

		$clean['preconnect_domains']    = ! empty( $input['preconnect_domains'] ) ? sanitize_textarea_field( $input['preconnect_domains'] ) : '';

		return $clean;
	}

	/**
	 * Renderiza la interfaz con la identidad visual corporativa unificada.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		?>
		<div class="wrap pulse-wrap">
			<style>
				.pulse-wrap {
					max-width: 1000px;
					margin: 20px auto 40px;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				}
				.pulse-header {
					background: linear-gradient(135deg, #1d2327 0%, #2c3338 100%);
					border-top: 4px solid #98825b;
					color: #ffffff;
					padding: 24px 30px;
					border-radius: 8px 8px 0 0;
					display: flex;
					align-items: center;
					justify-content: space-between;
					box-shadow: 0 2px 4px rgba(0,0,0,0.06);
				}
				.pulse-header h1 {
					color: #ffffff;
					margin: 0;
					font-size: 22px;
					font-weight: 700;
					display: flex;
					align-items: center;
					gap: 12px;
				}
				.pulse-badge {
					background: #98825b;
					color: #ffffff;
					font-size: 11px;
					padding: 3px 8px;
					border-radius: 12px;
					font-weight: 600;
					letter-spacing: 0.5px;
					text-transform: uppercase;
				}
				.pulse-content {
					background: #ede2ce;
					border: 1px solid #d5c8b2;
					border-top: none;
					padding: 28px;
					border-radius: 0 0 8px 8px;
				}
				.pulse-card {
					background: #ffffff;
					border: 1px solid #d5c8b2;
					border-radius: 8px;
					padding: 24px;
					margin-bottom: 24px;
					box-shadow: 0 1px 3px rgba(0,0,0,0.04);
				}
				.pulse-card-title {
					margin: 0 0 16px;
					padding-bottom: 10px;
					border-bottom: 2px solid #edd1c1;
					font-size: 16px;
					font-weight: 600;
					color: #1d2327;
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.pulse-item {
					display: flex;
					align-items: flex-start;
					gap: 12px;
					background: #fdfaf6;
					border: 1px solid #e2dcd5;
					border-radius: 6px;
					padding: 12px 14px;
					margin-bottom: 12px;
					transition: border 0.15s, background 0.15s;
				}
				.pulse-item:hover {
					background: #ffffff;
					border-color: #98825b;
				}
				.pulse-item input[type="checkbox"] {
					margin-top: 3px;
					transform: scale(1.15);
					cursor: pointer;
				}
				.pulse-label {
					font-weight: 600;
					font-size: 13.5px;
					color: #1d2327;
					cursor: pointer;
				}
				.pulse-desc {
					font-size: 12px;
					color: #646970;
					margin: 3px 0 0;
					line-height: 1.4;
				}
				.pulse-btn-submit {
					background: #98825b !important;
					border-color: #7a6845 !important;
					color: #ffffff !important;
					font-weight: 600 !important;
					border-radius: 4px !important;
					padding: 6px 20px !important;
					box-shadow: none !important;
				}
				.pulse-btn-submit:hover {
					background: #7a6845 !important;
				}
			</style>

			<div class="pulse-header">
				<div>
					<h1>
						<span class="dashicons dashicons-dashboard" style="font-size: 26px; width: 26px; height: 26px; color: #cedded;"></span>
						WP Assets Pulse
						<span class="pulse-badge">v<?php echo esc_html( self::VERSION ); ?></span>
					</h1>
					<p style="margin: 6px 0 0; color: #c3c4c7; font-size: 13px;">
						<?php esc_html_e( 'Optimización de entrega de recursos en front-end, eliminación de bloqueos de renderizado y debloat.', 'wp-assets-pulse' ); ?>
					</p>
				</div>
			</div>

			<div class="pulse-content">
				<form method="post" action="options.php">
					<?php settings_fields( 'wp_assets_pulse_group' ); ?>

					<!-- Tarjeta 1: Debloat -->
					<div class="pulse-card">
						<h3 class="pulse-card-title">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( '1. Eliminación de Recursos Innecesarios (Debloat)', 'wp-assets-pulse' ); ?>
						</h3>

						<div class="pulse-item">
							<input type="checkbox" id="pulse_emojis" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[disable_emojis]" value="1" <?php checked( $settings['disable_emojis'] ); ?> />
							<div>
								<label for="pulse_emojis" class="pulse-label"><?php esc_html_e( 'Desactivar Emojis de WordPress', 'wp-assets-pulse' ); ?></label>
								<p class="pulse-desc"><?php esc_html_e( 'Remueve wp-emoji-release.min.js y estilos CSS innecesarios. Los navegadores modernos muestran emojis nativamente.', 'wp-assets-pulse' ); ?></p>
							</div>
						</div>

						<div class="pulse-item">
							<input type="checkbox" id="pulse_blocks" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[disable_block_css]" value="1" <?php checked( $settings['disable_block_css'] ); ?> />
							<div>
								<label for="pulse_blocks" class="pulse-label"><?php esc_html_e( 'Desactivar CSS de Bloques Gutenberg en Front-End', 'wp-assets-pulse' ); ?></label>
								<p class="pulse-desc"><?php esc_html_e( 'Remueve wp-block-library.css y classic-theme-styles.css en sitios maquetados con Elementor, Divi o plantillas clásicas.', 'wp-assets-pulse' ); ?></p>
							</div>
						</div>

						<div class="pulse-item">
							<input type="checkbox" id="pulse_styles" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[disable_global_styles]" value="1" <?php checked( $settings['disable_global_styles'] ); ?> />
							<div>
								<label for="pulse_styles" class="pulse-label"><?php esc_html_e( 'Desactivar Estilos Globales Inline y Filtros SVG', 'wp-assets-pulse' ); ?></label>
								<p class="pulse-desc"><?php esc_html_e( 'Suprime los filtros SVG automáticos y CSS global inyectado por theme.json en temas que no usan FSE.', 'wp-assets-pulse' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Tarjeta 2: JavaScript Loading -->
					<div class="pulse-card">
						<h3 class="pulse-card-title">
							<span class="dashicons dashicons-media-code"></span>
							<?php esc_html_e( '2. Optimización de JavaScript (Defer)', 'wp-assets-pulse' ); ?>
						</h3>

						<div class="pulse-item">
							<input type="checkbox" id="pulse_defer" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[defer_js_enabled]" value="1" <?php checked( $settings['defer_js_enabled'] ); ?> />
							<div>
								<label for="pulse_defer" class="pulse-label"><?php esc_html_e( 'Cargar Scripts con atributo Defer', 'wp-assets-pulse' ); ?></label>
								<p class="pulse-desc"><?php esc_html_e( 'Añade defer a los scripts encolados en front-end para evitar bloqueo de renderizado en el navegador.', 'wp-assets-pulse' ); ?></p>
							</div>
						</div>

						<div style="margin-top: 15px;">
							<label for="pulse_exclusions" style="display: block; font-size: 13px; font-weight: 600; color: #1d2327; margin-bottom: 5px;">
								<?php esc_html_e( 'Exclusiones de Defer (un handle por línea):', 'wp-assets-pulse' ); ?>
							</label>
							<textarea id="pulse_exclusions" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[defer_js_exclusions]" rows="3" class="large-text" style="font-family: monospace; border: 1px solid #d5c8b2; border-radius: 4px;"><?php echo esc_textarea( $settings['defer_js_exclusions'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Por defecto se excluye jquery y jquery-core para evitar errores con plugins tradicionales.', 'wp-assets-pulse' ); ?></p>
						</div>
					</div>

					<!-- Tarjeta 3: Resource Hints -->
					<div class="pulse-card">
						<h3 class="pulse-card-title">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e( '3. Preconexiones y Fuentes (Resource Hints)', 'wp-assets-pulse' ); ?>
						</h3>

						<label for="pulse_preconnect" style="display: block; font-size: 13px; font-weight: 600; color: #1d2327; margin-bottom: 5px;">
							<?php esc_html_e( 'Dominios para Preconnect (un dominio por línea):', 'wp-assets-pulse' ); ?>
						</label>
						<textarea id="pulse_preconnect" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[preconnect_domains]" rows="3" class="large-text" style="font-family: monospace; border: 1px solid #d5c8b2; border-radius: 4px;"><?php echo esc_textarea( $settings['preconnect_domains'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Acelera la descarga de fuentes y recursos externos estableciendo la conexión anticipada (ej: https://fonts.gstatic.com).', 'wp-assets-pulse' ); ?></p>
					</div>

					<?php submit_button( __( 'Guardar Optimización', 'wp-assets-pulse' ), 'primary', 'submit', true, array( 'class' => 'button button-primary pulse-btn-submit' ) ); ?>
				</form>
			</div>
		</div>
		<?php
	}
}
