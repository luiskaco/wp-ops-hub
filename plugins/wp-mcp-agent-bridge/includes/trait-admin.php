<?php
/**
 * Panel de administración y generador de configuración para clientes IA.
 *
 * @package WP_MCP_Agent_Bridge
 */

defined( 'ABSPATH' ) || exit;

trait WP_MCP_Bridge_Admin {

	/**
	 * Registra el menú de administración.
	 *
	 * @return void
	 */
	public static function init_admin() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_admin_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Encola estilos y scripts solo en la página de configuración del plugin.
	 *
	 * @param string $hook_suffix Hook de la pantalla actual en wp-admin.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'tools_page_wp-mcp-agent-bridge' !== $hook_suffix ) {
			return;
		}

		$base_url = plugin_dir_url( WP_MCP_AGENT_BRIDGE_FILE );

		wp_enqueue_style(
			'wp-mcp-agent-bridge-admin',
			$base_url . 'assets/admin.css',
			array(),
			self::VERSION
		);

		wp_enqueue_script(
			'wp-mcp-agent-bridge-admin',
			$base_url . 'assets/admin.js',
			array(),
			self::VERSION,
			true
		);
	}

	/**
	 * Agrega la página bajo Herramientas.
	 *
	 * @return void
	 */
	public static function register_admin_menu() {
		add_management_page(
			__( 'WP MCP Bridge', 'wp-mcp-agent-bridge' ),
			__( 'WP MCP Bridge', 'wp-mcp-agent-bridge' ),
			'manage_options',
			'wp-mcp-agent-bridge',
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
			'wp_mcp_agent_bridge_group',
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
		$defaults = self::get_default_settings();
		$clean    = array();

		$clean['enabled']              = ! empty( $input['enabled'] );
		$clean['allow_writes']         = ! empty( $input['allow_writes'] );
		$clean['allow_deletes']        = ! empty( $input['allow_deletes'] );
		$clean['activity_log_enabled'] = ! empty( $input['activity_log_enabled'] );

		$clean['modules'] = array();
		foreach ( array_keys( $defaults['modules'] ) as $mod ) {
			$clean['modules'][ $mod ] = ! empty( $input['modules'][ $mod ] );
		}

		return $clean;
	}

	/**
	 * Renderiza la interfaz del panel con un diseño limpio y moderno.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$endpoint = rest_url( 'mcp/v1/rpc' );
		$tools    = self::get_active_tools();
		$logs     = get_option( self::LOG_KEY, array() );
		$user     = wp_get_current_user();

		// JSON para clientes (la credencial Base64 se completa en el navegador si el usuario pega su Application Password)
		$mcp_json_snippet = array(
			'mcpServers' => array(
				'wordpress' => array(
					'url'     => $endpoint,
					'headers' => array(
						'Authorization' => 'Basic <BASE64_DE_USUARIO_Y_PASSWORD>',
					),
				),
			),
		);
		$json_str = wp_json_encode( $mcp_json_snippet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		$modules_info = array(
			'core'        => array(
				'name' => __( 'Núcleo (Core)', 'wp-mcp-agent-bridge' ),
				'desc' => __( 'Entradas, páginas, Custom Post Types, taxonomías y plugins.', 'wp-mcp-agent-bridge' ),
				'icon' => 'dashicons-admin-post',
				'tag'  => __( 'Base', 'wp-mcp-agent-bridge' ),
			),
			'media'       => array(
				'name' => __( 'Biblioteca de Medios', 'wp-mcp-agent-bridge' ),
				'desc' => __( 'Gestión y subida de imágenes por URL remota y Base64.', 'wp-mcp-agent-bridge' ),
				'icon' => 'dashicons-admin-media',
				'tag'  => __( 'Archivos', 'wp-mcp-agent-bridge' ),
			),
			'woocommerce' => array(
				'name' => __( 'WooCommerce', 'wp-mcp-agent-bridge' ),
				'desc' => __( 'Productos, inventario, precios, pedidos y clientes.', 'wp-mcp-agent-bridge' ),
				'icon' => 'dashicons-cart',
				'tag'  => __( 'Tienda', 'wp-mcp-agent-bridge' ),
			),
			'seo'         => array(
				'name' => __( 'SEO Multigestor', 'wp-mcp-agent-bridge' ),
				'desc' => __( 'Lectura y optimización con Yoast, Rank Math y AIOSEO.', 'wp-mcp-agent-bridge' ),
				'icon' => 'dashicons-search',
				'tag'  => __( 'Metadatos', 'wp-mcp-agent-bridge' ),
			),
			'cache'       => array(
				'name' => __( 'Caché & Rendimiento', 'wp-mcp-agent-bridge' ),
				'desc' => __( 'Purga de LiteSpeed, WP Rocket, WP Super Cache y W3TC.', 'wp-mcp-agent-bridge' ),
				'icon' => 'dashicons-performance',
				'tag'  => __( 'Velocidad', 'wp-mcp-agent-bridge' ),
			),
		);
		?>
		<?php
		$key             = self::OPTION_KEY;
		$modules_active  = 0;
		foreach ( $modules_info as $mod_key => $mod_data ) {
			if ( ! empty( $settings['modules'][ $mod_key ] ) ) {
				++$modules_active;
			}
		}
		$server_on = ! empty( $settings['enabled'] );
		if ( ! $server_on ) {
			$mode_label = __( 'Apagado', 'wp-mcp-agent-bridge' );
		} elseif ( empty( $settings['allow_writes'] ) ) {
			$mode_label = __( 'Solo lectura', 'wp-mcp-agent-bridge' );
		} elseif ( ! empty( $settings['allow_deletes'] ) ) {
			$mode_label = __( 'Lectura, escritura y borrado', 'wp-mcp-agent-bridge' );
		} else {
			$mode_label = __( 'Lectura y escritura', 'wp-mcp-agent-bridge' );
		}
		?>
		<div class="wrap">
		<div class="mcp">

			<div class="mcp-hero">
				<div>
					<span class="mcp-eyebrow"><span class="dashicons dashicons-rest-api"></span> <?php esc_html_e( 'Model Context Protocol', 'wp-mcp-agent-bridge' ); ?></span>
					<h1 class="mcp-title">
						WP MCP Agent Bridge
						<span class="mcp-version">v<?php echo esc_html( self::VERSION ); ?></span>
					</h1>
					<p class="mcp-sub"><?php esc_html_e( 'Servidor nativo Model Context Protocol para conectar asistentes de IA (Cursor, Antigravity, Claude, VS Code).', 'wp-mcp-agent-bridge' ); ?></p>
				</div>
				<div class="mcp-pill">
					<span class="mcp-dot <?php echo $server_on ? 'mcp-dot--on' : 'mcp-dot--off'; ?>"></span>
					<?php echo $server_on ? esc_html__( 'Servidor activo', 'wp-mcp-agent-bridge' ) : esc_html__( 'Servidor inactivo', 'wp-mcp-agent-bridge' ); ?>
				</div>
			</div>

			<div class="mcp-kpis">
				<div class="mcp-kpi <?php echo $server_on ? 'mcp-kpi--green' : 'mcp-kpi--red'; ?>">
					<span class="mcp-kpi-icon"><span class="dashicons dashicons-shield"></span></span>
					<div><div class="mcp-kpi-value" style="font-size: 17px;"><?php echo esc_html( $mode_label ); ?></div><div class="mcp-kpi-label"><?php esc_html_e( 'Modo del servidor', 'wp-mcp-agent-bridge' ); ?></div></div>
				</div>
				<div class="mcp-kpi">
					<span class="mcp-kpi-icon"><span class="dashicons dashicons-admin-tools"></span></span>
					<div><div class="mcp-kpi-value"><?php echo esc_html( count( $tools ) ); ?></div><div class="mcp-kpi-label"><?php esc_html_e( 'Herramientas expuestas', 'wp-mcp-agent-bridge' ); ?></div></div>
				</div>
				<div class="mcp-kpi mcp-kpi--blue">
					<span class="mcp-kpi-icon"><span class="dashicons dashicons-screenoptions"></span></span>
					<div><div class="mcp-kpi-value"><?php echo esc_html( $modules_active . '/' . count( $modules_info ) ); ?></div><div class="mcp-kpi-label"><?php esc_html_e( 'Módulos activos', 'wp-mcp-agent-bridge' ); ?></div></div>
				</div>
				<div class="mcp-kpi mcp-kpi--amber">
					<span class="mcp-kpi-icon"><span class="dashicons dashicons-visibility"></span></span>
					<div><div class="mcp-kpi-value"><?php echo esc_html( count( (array) $logs ) ); ?></div><div class="mcp-kpi-label"><?php esc_html_e( 'Llamadas registradas', 'wp-mcp-agent-bridge' ); ?></div></div>
				</div>
			</div>

			<div class="mcp-grid mcp-grid--2">
				<!-- COLUMNA IZQUIERDA: Formulario de configuración -->
				<div>
					<form method="post" action="options.php">
						<?php settings_fields( 'wp_mcp_agent_bridge_group' ); ?>

						<div class="mcp-card">
							<h3 class="mcp-card-title"><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Estado y Políticas de Seguridad', 'wp-mcp-agent-bridge' ); ?></h3>

							<div class="mcp-item">
								<input type="checkbox" id="mcp_enabled" name="<?php echo esc_attr( $key ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> />
								<div>
									<label for="mcp_enabled" class="mcp-item-label"><?php esc_html_e( 'Habilitar servidor MCP', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-item-desc"><?php esc_html_e( 'Permite atender solicitudes JSON-RPC en /wp-json/mcp/v1/rpc autenticadas con Application Passwords.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>

							<div class="mcp-item">
								<input type="checkbox" id="mcp_allow_writes" name="<?php echo esc_attr( $key ); ?>[allow_writes]" value="1" <?php checked( $settings['allow_writes'] ); ?> />
								<div>
									<label for="mcp_allow_writes" class="mcp-item-label"><?php esc_html_e( 'Permitir operaciones de escritura', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-item-desc"><?php esc_html_e( 'Permite a la IA crear o modificar contenido (posts, productos, subir medios). Si está desmarcado, el servidor opera en modo solo lectura.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>

							<div class="mcp-item mcp-item--danger">
								<input type="checkbox" id="mcp_allow_deletes" name="<?php echo esc_attr( $key ); ?>[allow_deletes]" value="1" <?php checked( $settings['allow_deletes'] ); ?> />
								<div>
									<label for="mcp_allow_deletes" class="mcp-item-label"><?php esc_html_e( 'Permitir operaciones destructivas (Eliminar)', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-item-desc"><?php esc_html_e( 'Permite a la IA enviar contenido a la papelera o eliminarlo permanentemente. Se recomienda mantener deshabilitado.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>
						</div>

						<div class="mcp-card">
							<h3 class="mcp-card-title"><span class="dashicons dashicons-screenoptions"></span> <?php esc_html_e( 'Módulos y Herramientas Habilitadas', 'wp-mcp-agent-bridge' ); ?></h3>
							<p class="mcp-card-lead"><?php esc_html_e( 'Activa o desactiva qué áreas del sitio puede operar el asistente de IA.', 'wp-mcp-agent-bridge' ); ?></p>

							<?php foreach ( $modules_info as $mod_key => $mod_data ) : ?>
								<div class="mcp-item">
									<input type="checkbox" id="mcp_mod_<?php echo esc_attr( $mod_key ); ?>" name="<?php echo esc_attr( $key ); ?>[modules][<?php echo esc_attr( $mod_key ); ?>]" value="1" <?php checked( ! empty( $settings['modules'][ $mod_key ] ) ); ?> />
									<div>
										<label for="mcp_mod_<?php echo esc_attr( $mod_key ); ?>" class="mcp-item-label">
											<span class="dashicons <?php echo esc_attr( $mod_data['icon'] ); ?>" style="width: 16px; height: 16px; font-size: 16px; vertical-align: text-top; color: var(--a);"></span>
											<?php echo esc_html( $mod_data['name'] ); ?>
											<span class="mcp-tag"><?php echo esc_html( $mod_data['tag'] ); ?></span>
										</label>
										<p class="mcp-item-desc"><?php echo esc_html( $mod_data['desc'] ); ?></p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<div class="mcp-card">
							<h3 class="mcp-card-title"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Auditoría de Actividad', 'wp-mcp-agent-bridge' ); ?></h3>
							<div class="mcp-item">
								<input type="checkbox" id="mcp_activity_log" name="<?php echo esc_attr( $key ); ?>[activity_log_enabled]" value="1" <?php checked( $settings['activity_log_enabled'] ); ?> />
								<div>
									<label for="mcp_activity_log" class="mcp-item-label"><?php esc_html_e( 'Registrar llamadas de herramientas', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-item-desc"><?php esc_html_e( 'Guarda de forma rotativa las últimas 50 llamadas ejecutadas por la IA con usuario, fecha y resultado.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>
						</div>

						<div class="mcp-actions">
							<?php submit_button( __( 'Guardar Configuración', 'wp-mcp-agent-bridge' ), 'primary mcp-btn mcp-btn--primary', 'submit', false ); ?>
						</div>
					</form>
				</div>

				<!-- COLUMNA DERECHA: Conexión con clientes -->
				<div>
					<div class="mcp-card mcp-card--accent">
						<h3 class="mcp-card-title"><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Conexión con Asistentes IA', 'wp-mcp-agent-bridge' ); ?></h3>

						<div class="mcp-field">
							<label for="mcp_endpoint_url"><?php esc_html_e( 'URL del Endpoint MCP (HTTP JSON-RPC)', 'wp-mcp-agent-bridge' ); ?></label>
							<input type="text" id="mcp_endpoint_url" class="mcp-endpoint-input mcp-mono" readonly value="<?php echo esc_attr( $endpoint ); ?>" />
						</div>

						<div class="mcp-field">
							<label for="mcp_app_password_input">
								<?php
								printf(
									/* translators: %s: nombre de usuario actual */
									esc_html__( 'Contraseña de aplicación para "%s" (solo local, no se guarda)', 'wp-mcp-agent-bridge' ),
									esc_html( $user->user_login )
								);
								?>
							</label>
							<input type="text" id="mcp_app_password_input" class="mcp-endpoint-input mcp-mono" autocomplete="off" spellcheck="false" data-username="<?php echo esc_attr( $user->user_login ); ?>" data-endpoint="<?php echo esc_attr( $endpoint ); ?>" placeholder="xxxx xxxx xxxx xxxx" />
						</div>

						<div class="mcp-flex" style="margin-bottom: 8px;">
							<span class="mcp-label" style="margin: 0;"><?php esc_html_e( 'Configuración JSON (.cursor/mcp.json)', 'wp-mcp-agent-bridge' ); ?></span>
							<button type="button" id="mcp_copy_json_btn" class="mcp-btn mcp-btn--soft mcp-copy-btn" data-copied-label="<?php esc_attr_e( '¡Copiado!', 'wp-mcp-agent-bridge' ); ?>">
								<?php esc_html_e( 'Copiar JSON', 'wp-mcp-agent-bridge' ); ?>
							</button>
						</div>

						<textarea id="mcp_json_snippet" class="mcp-codebox" readonly><?php echo esc_textarea( $json_str ); ?></textarea>

						<div class="mcp-note">
							<strong><?php esc_html_e( 'Instrucciones:', 'wp-mcp-agent-bridge' ); ?></strong>
							<ol class="mcp-steps">
								<li><?php esc_html_e( 'Ve a Usuarios > Perfil y crea una "Contraseña de aplicación" (formato xxxx xxxx xxxx xxxx).', 'wp-mcp-agent-bridge' ); ?></li>
								<li><?php esc_html_e( 'Pégala en el campo de arriba: el JSON calculará el encabezado Basic Base64 automáticamente.', 'wp-mcp-agent-bridge' ); ?></li>
								<li><?php esc_html_e( 'Haz clic en "Copiar JSON" y pégalo en Cursor, Windsurf, Claude o Antigravity.', 'wp-mcp-agent-bridge' ); ?></li>
							</ol>
						</div>
					</div>
				</div>
			</div>

			<!-- Registro de auditoría -->
			<div class="mcp-card" style="margin-top: 16px;">
				<h3 class="mcp-card-title"><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Registro en Vivo de Llamadas MCP', 'wp-mcp-agent-bridge' ); ?></h3>

				<?php if ( empty( $logs ) ) : ?>
					<p class="mcp-empty"><?php esc_html_e( 'No hay llamadas registradas todavía. Cuando tu asistente de IA ejecute una herramienta, aparecerá aquí en tiempo real.', 'wp-mcp-agent-bridge' ); ?></p>
				<?php else : ?>
					<div class="mcp-tablewrap">
						<table class="mcp-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Fecha / Hora', 'wp-mcp-agent-bridge' ); ?></th>
									<th><?php esc_html_e( 'Herramienta', 'wp-mcp-agent-bridge' ); ?></th>
									<th><?php esc_html_e( 'Usuario', 'wp-mcp-agent-bridge' ); ?></th>
									<th><?php esc_html_e( 'Estado', 'wp-mcp-agent-bridge' ); ?></th>
									<th><?php esc_html_e( 'Detalle / Mensaje', 'wp-mcp-agent-bridge' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( array_slice( $logs, 0, 15 ) as $log ) : ?>
									<tr>
										<td><?php echo esc_html( $log['timestamp'] ); ?></td>
										<td><code><?php echo esc_html( $log['tool'] ); ?></code></td>
										<td><?php echo esc_html( $log['user_name'] ); ?></td>
										<td>
											<?php if ( ! empty( $log['success'] ) ) : ?>
												<span class="mcp-badge mcp-badge--ok">✔ OK</span>
											<?php else : ?>
												<span class="mcp-badge mcp-badge--fail">✖ Error</span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $log['message'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		</div>
		<?php
	}
}
