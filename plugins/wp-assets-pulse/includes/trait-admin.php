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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
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
	 * Encola el CSS del panel solo en la página de WP Assets Pulse.
	 *
	 * @param string $hook_suffix Hook de la pantalla actual en wp-admin.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_wp-assets-pulse' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wp-assets-pulse-admin',
			plugin_dir_url( WP_ASSETS_PULSE_FILE ) . 'assets/admin.css',
			array(),
			self::VERSION
		);
	}

	/**
	 * Renderiza el panel de ajustes.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$key      = self::OPTION_KEY;

		$toggles = array(
			'disable_emojis',
			'disable_block_css',
			'disable_global_styles',
			'defer_js_enabled',
		);
		$active  = 0;
		foreach ( $toggles as $toggle ) {
			if ( ! empty( $settings[ $toggle ] ) ) {
				++$active;
			}
		}
		$domains = array_filter( array_map( 'trim', explode( "\n", (string) $settings['preconnect_domains'] ) ) );
		?>
		<div class="wrap">
		<div class="pulse">
			<div class="pulse-hero">
				<div>
					<span class="pulse-eyebrow"><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Rendimiento front-end', 'wp-assets-pulse' ); ?></span>
					<h1 class="pulse-title">
						WP Assets Pulse
						<span class="pulse-version">v<?php echo esc_html( self::VERSION ); ?></span>
					</h1>
					<p class="pulse-sub"><?php esc_html_e( 'Optimización de entrega de recursos en front-end, eliminación de bloqueos de renderizado y debloat.', 'wp-assets-pulse' ); ?></p>
				</div>
				<div class="pulse-pill">
					<span class="pulse-dot <?php echo $active > 0 ? 'pulse-dot--on' : ''; ?>"></span>
					<?php
					/* translators: 1: optimizaciones activas, 2: total */
					echo esc_html( sprintf( __( '%1$d de %2$d optimizaciones activas', 'wp-assets-pulse' ), $active, count( $toggles ) ) );
					?>
				</div>
			</div>

			<div class="pulse-kpis">
				<div class="pulse-kpi">
					<span class="pulse-kpi-icon"><span class="dashicons dashicons-yes-alt"></span></span>
					<div><div class="pulse-kpi-value"><?php echo esc_html( $active ); ?></div><div class="pulse-kpi-label"><?php esc_html_e( 'Optimizaciones activas', 'wp-assets-pulse' ); ?></div></div>
				</div>
				<div class="pulse-kpi pulse-kpi--blue">
					<span class="pulse-kpi-icon"><span class="dashicons dashicons-media-code"></span></span>
					<div><div class="pulse-kpi-value"><?php echo ! empty( $settings['defer_js_enabled'] ) ? esc_html__( 'Sí', 'wp-assets-pulse' ) : esc_html__( 'No', 'wp-assets-pulse' ); ?></div><div class="pulse-kpi-label"><?php esc_html_e( 'Scripts con defer', 'wp-assets-pulse' ); ?></div></div>
				</div>
				<div class="pulse-kpi pulse-kpi--green">
					<span class="pulse-kpi-icon"><span class="dashicons dashicons-admin-links"></span></span>
					<div><div class="pulse-kpi-value"><?php echo esc_html( count( $domains ) ); ?></div><div class="pulse-kpi-label"><?php esc_html_e( 'Dominios con preconnect', 'wp-assets-pulse' ); ?></div></div>
				</div>
			</div>

			<form method="post" action="options.php" class="pulse-body">
				<?php settings_fields( 'wp_assets_pulse_group' ); ?>

				<div class="pulse-card">
					<h3 class="pulse-card-title"><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Eliminación de recursos innecesarios (Debloat)', 'wp-assets-pulse' ); ?></h3>

					<div class="pulse-item">
						<input type="checkbox" id="pulse_emojis" name="<?php echo esc_attr( $key ); ?>[disable_emojis]" value="1" <?php checked( $settings['disable_emojis'] ); ?> />
						<div>
							<label for="pulse_emojis" class="pulse-item-label"><?php esc_html_e( 'Desactivar Emojis de WordPress', 'wp-assets-pulse' ); ?></label>
							<p class="pulse-item-desc"><?php esc_html_e( 'Remueve wp-emoji-release.min.js y estilos CSS innecesarios. Los navegadores modernos muestran emojis nativamente.', 'wp-assets-pulse' ); ?></p>
						</div>
					</div>

					<div class="pulse-item">
						<input type="checkbox" id="pulse_blocks" name="<?php echo esc_attr( $key ); ?>[disable_block_css]" value="1" <?php checked( $settings['disable_block_css'] ); ?> />
						<div>
							<label for="pulse_blocks" class="pulse-item-label"><?php esc_html_e( 'Desactivar CSS de Bloques Gutenberg en Front-End', 'wp-assets-pulse' ); ?></label>
							<p class="pulse-item-desc"><?php esc_html_e( 'Remueve wp-block-library.css y classic-theme-styles.css en sitios maquetados con Elementor, Divi o plantillas clásicas.', 'wp-assets-pulse' ); ?></p>
						</div>
					</div>

					<div class="pulse-item">
						<input type="checkbox" id="pulse_styles" name="<?php echo esc_attr( $key ); ?>[disable_global_styles]" value="1" <?php checked( $settings['disable_global_styles'] ); ?> />
						<div>
							<label for="pulse_styles" class="pulse-item-label"><?php esc_html_e( 'Desactivar Estilos Globales Inline y Filtros SVG', 'wp-assets-pulse' ); ?></label>
							<p class="pulse-item-desc"><?php esc_html_e( 'Suprime los filtros SVG automáticos y CSS global inyectado por theme.json en temas que no usan FSE.', 'wp-assets-pulse' ); ?></p>
						</div>
					</div>
				</div>

				<div class="pulse-card">
					<h3 class="pulse-card-title"><span class="dashicons dashicons-media-code"></span> <?php esc_html_e( 'Optimización de JavaScript (Defer)', 'wp-assets-pulse' ); ?></h3>

					<div class="pulse-item">
						<input type="checkbox" id="pulse_defer" name="<?php echo esc_attr( $key ); ?>[defer_js_enabled]" value="1" <?php checked( $settings['defer_js_enabled'] ); ?> />
						<div>
							<label for="pulse_defer" class="pulse-item-label"><?php esc_html_e( 'Cargar Scripts con atributo Defer', 'wp-assets-pulse' ); ?></label>
							<p class="pulse-item-desc"><?php esc_html_e( 'Añade defer a los scripts encolados en front-end para evitar bloqueo de renderizado en el navegador.', 'wp-assets-pulse' ); ?></p>
						</div>
					</div>

					<div class="pulse-field">
						<label for="pulse_exclusions"><?php esc_html_e( 'Exclusiones de Defer (un handle por línea)', 'wp-assets-pulse' ); ?></label>
						<textarea id="pulse_exclusions" name="<?php echo esc_attr( $key ); ?>[defer_js_exclusions]" rows="3" class="pulse-mono"><?php echo esc_textarea( $settings['defer_js_exclusions'] ); ?></textarea>
						<p class="pulse-hint"><?php esc_html_e( 'Por defecto se excluye jquery y jquery-core para evitar errores con plugins tradicionales.', 'wp-assets-pulse' ); ?></p>
					</div>
				</div>

				<div class="pulse-card">
					<h3 class="pulse-card-title"><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Preconexiones y Fuentes (Resource Hints)', 'wp-assets-pulse' ); ?></h3>

					<div class="pulse-field">
						<label for="pulse_preconnect"><?php esc_html_e( 'Dominios para Preconnect (un dominio por línea)', 'wp-assets-pulse' ); ?></label>
						<textarea id="pulse_preconnect" name="<?php echo esc_attr( $key ); ?>[preconnect_domains]" rows="3" class="pulse-mono"><?php echo esc_textarea( $settings['preconnect_domains'] ); ?></textarea>
						<p class="pulse-hint"><?php esc_html_e( 'Acelera la descarga de fuentes y recursos externos estableciendo la conexión anticipada (ej: https://fonts.gstatic.com).', 'wp-assets-pulse' ); ?></p>
					</div>
				</div>

				<div class="pulse-actions">
					<?php submit_button( __( 'Guardar Optimización', 'wp-assets-pulse' ), 'primary pulse-btn pulse-btn--primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		</div>
		<?php
	}
}
