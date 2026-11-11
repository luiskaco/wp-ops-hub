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
		$endpoint = rest_url( 'mcp/v1' );
		$tools    = self::get_active_tools();
		$logs     = get_option( self::LOG_KEY, array() );
		$user     = wp_get_current_user();

		// JSON para clientes
		$mcp_json_snippet = array(
			'mcpServers' => array(
				'wordpress' => array(
					'url'     => $endpoint,
					'headers' => array(
						'Authorization' => 'Basic ' . base64_encode( $user->user_login . ':TU_APPLICATION_PASSWORD_AQUI' ),
					),
				),
			),
		);
		$json_str = wp_json_encode( $mcp_json_snippet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		$modules_info = array(
			'core'        => array(
				'name' => 'Núcleo (Core)',
				'desc' => 'Entradas, páginas, Custom Post Types, taxonomías y plugins.',
				'icon' => 'dashicons-admin-post',
				'tag'  => 'Base',
			),
			'media'       => array(
				'name' => 'Biblioteca de Medios',
				'desc' => 'Gestión y subida de imágenes por URL remota y Base64.',
				'icon' => 'dashicons-admin-media',
				'tag'  => 'Archivos',
			),
			'woocommerce' => array(
				'name' => 'WooCommerce',
				'desc' => 'Productos, inventario, precios, pedidos y clientes.',
				'icon' => 'dashicons-cart',
				'tag'  => 'Tienda',
			),
			'seo'         => array(
				'name' => 'SEO Multigestor',
				'desc' => 'Lectura y optimización con Yoast, Rank Math y AIOSEO.',
				'icon' => 'dashicons-search',
				'tag'  => 'Metadatos',
			),
			'cache'       => array(
				'name' => 'Caché & Rendimiento',
				'desc' => 'Purga de LiteSpeed, WP Rocket, WP Super Cache y W3TC.',
				'icon' => 'dashicons-performance',
				'tag'  => 'Velocidad',
			),
		);
		?>
		<div class="wrap mcp-wrap">
			<style>
				.mcp-wrap {
					max-width: 1200px;
					margin: 20px auto 40px;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
				}
				.mcp-header-card {
					background: linear-gradient(135deg, #1d2327 0%, #2c3338 100%);
					border-top: 4px solid #98825b;
					color: #fff;
					padding: 24px 30px;
					border-radius: 8px 8px 0 0;
					display: flex;
					align-items: center;
					justify-content: space-between;
					box-shadow: 0 2px 4px rgba(0,0,0,0.06);
				}
				.mcp-header-title {
					display: flex;
					align-items: center;
					gap: 12px;
				}
				.mcp-header-title h1 {
					color: #fff;
					margin: 0;
					font-size: 22px;
					font-weight: 700;
				}
				.mcp-badge {
					background: #98825b;
					color: #ffffff;
					font-size: 11px;
					font-weight: 600;
					padding: 3px 8px;
					border-radius: 12px;
					text-transform: uppercase;
					letter-spacing: 0.5px;
				}
				.mcp-status-pill {
					display: inline-flex;
					align-items: center;
					gap: 6px;
					background: rgba(255, 255, 255, 0.1);
					border: 1px solid rgba(255, 255, 255, 0.15);
					padding: 6px 14px;
					border-radius: 20px;
					font-size: 13px;
					font-weight: 500;
				}
				.mcp-dot {
					width: 8px;
					height: 8px;
					border-radius: 50%;
					display: inline-block;
				}
				.mcp-dot-online { background: #46b450; box-shadow: 0 0 6px #46b450; }
				.mcp-dot-offline { background: #dc3232; }

				.mcp-layout {
					display: grid;
					grid-template-columns: 1fr;
					gap: 20px;
					background: #ede2ce;
					border: 1px solid #d5c8b2;
					border-top: none;
					padding: 24px;
					border-radius: 0 0 8px 8px;
				}
				@media (min-width: 900px) {
					.mcp-layout {
						grid-template-columns: 7fr 5fr;
					}
				}
				.mcp-box {
					background: #ffffff;
					border: 1px solid #d5c8b2;
					border-radius: 6px;
					padding: 20px 24px;
					box-shadow: 0 1px 3px rgba(0,0,0,0.04);
					margin-bottom: 20px;
				}
				.mcp-box:last-child {
					margin-bottom: 0;
				}
				.mcp-box-title {
					margin: 0 0 16px 0;
					padding-bottom: 10px;
					border-bottom: 2px solid #edd1c1;
					font-size: 15px;
					font-weight: 600;
					color: #1d2327;
					display: flex;
					align-items: center;
					gap: 8px;
				}
				.mcp-card-item {
					display: flex;
					align-items: flex-start;
					gap: 12px;
					background: #ffffff;
					border: 1px solid #e2dcd5;
					border-radius: 6px;
					padding: 12px 14px;
					margin-bottom: 10px;
					transition: border 0.15s, background 0.15s;
				}
				.mcp-card-item:hover {
					background: #fdfaf6;
					border-color: #98825b;
				}
				.mcp-card-item input[type="checkbox"] {
					margin-top: 3px;
					transform: scale(1.15);
					cursor: pointer;
				}
				.mcp-card-label {
					font-weight: 600;
					font-size: 13.5px;
					color: #1d2327;
					cursor: pointer;
					display: block;
				}
				.mcp-card-desc {
					font-size: 12px;
					color: #646970;
					margin: 3px 0 0;
					line-height: 1.4;
				}
				.mcp-tag {
					font-size: 10px;
					font-weight: 600;
					padding: 2px 7px;
					border-radius: 4px;
					background: #d0ceed;
					color: #3f3b6c;
					margin-left: 6px;
					text-transform: uppercase;
					letter-spacing: 0.3px;
				}
				.mcp-code-block {
					background: #1e1e1e;
					color: #9cdcfe;
					padding: 14px;
					border-radius: 5px;
					font-family: Consolas, Monaco, "Courier New", monospace;
					font-size: 12px;
					line-height: 1.5;
					overflow-x: auto;
					position: relative;
				}
				.mcp-copy-btn {
					float: right;
					background: #98825b;
					color: #fff;
					border: none;
					padding: 5px 12px;
					border-radius: 4px;
					cursor: pointer;
					font-size: 11px;
					font-weight: 600;
					transition: background 0.15s;
				}
				.mcp-copy-btn:hover {
					background: #7a6845;
				}
				.mcp-endpoint-input {
					width: 100%;
					font-family: monospace;
					background: #fdfaf6;
					border: 1px solid #d5c8b2;
					padding: 7px 10px;
					border-radius: 4px;
					margin: 4px 0 12px;
					font-size: 12.5px;
					color: #2c3338;
				}
				.mcp-table {
					width: 100%;
					border-collapse: collapse;
					font-size: 12.5px;
				}
				.mcp-table th, .mcp-table td {
					padding: 10px 12px;
					text-align: left;
					border-bottom: 1px solid #edd1c1;
				}
				.mcp-table th {
					background: #fdfaf6;
					font-weight: 600;
					color: #50575e;
				}
				.mcp-badge-ok {
					background: #e7f5ea;
					color: #185a25;
					font-weight: 600;
					padding: 2px 7px;
					border-radius: 4px;
					font-size: 11px;
				}
				.mcp-badge-err {
					background: #fbeae5;
					color: #8a2414;
					font-weight: 600;
					padding: 2px 7px;
					border-radius: 4px;
					font-size: 11px;
				}
				.mcp-btn-submit {
					background: #98825b !important;
					border-color: #7a6845 !important;
					color: #ffffff !important;
					padding: 6px 20px !important;
					font-weight: 600 !important;
					border-radius: 4px !important;
					box-shadow: none !important;
				}
				.mcp-btn-submit:hover {
					background: #7a6845 !important;
				}
			</style>

			<div class="mcp-header-card">
				<div>
					<div class="mcp-header-title">
						<span class="dashicons dashicons-rest-api" style="font-size: 28px; width: 28px; height: 28px; color: #72aee6;"></span>
						<h1>WP MCP Agent Bridge</h1>
						<span class="mcp-badge">v<?php echo esc_html( self::VERSION ); ?></span>
					</div>
					<p style="margin: 6px 0 0; color: #c3c4c7; font-size: 13px;">
						<?php esc_html_e( 'Servidor nativo Model Context Protocol para conectar asistentes de IA (Cursor, Antigravity, Claude, VS Code).', 'wp-mcp-agent-bridge' ); ?>
					</p>
				</div>
				<div>
					<div class="mcp-status-pill">
						<span class="mcp-dot <?php echo ! empty( $settings['enabled'] ) ? 'mcp-dot-online' : 'mcp-dot-offline'; ?>"></span>
						<span><?php echo ! empty( $settings['enabled'] ) ? esc_html__( 'Servidor Activo', 'wp-mcp-agent-bridge' ) : esc_html__( 'Servidor Inactivo', 'wp-mcp-agent-bridge' ); ?></span>
					</div>
				</div>
			</div>

			<div class="mcp-layout">
				<!-- COLUMNA IZQUIERDA: Formulario de Configuración -->
				<div>
					<form method="post" action="options.php">
						<?php settings_fields( 'wp_mcp_agent_bridge_group' ); ?>

						<!-- Caja 1: Estado y Gobernanza -->
						<div class="mcp-box">
							<h3 class="mcp-box-title">
								<span class="dashicons dashicons-shield"></span>
								<?php esc_html_e( '1. Estado y Políticas de Seguridad', 'wp-mcp-agent-bridge' ); ?>
							</h3>

							<div class="mcp-card-item">
								<input type="checkbox" id="mcp_enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> />
								<div>
									<label for="mcp_enabled" class="mcp-card-label"><?php esc_html_e( 'Habilitar servidor MCP', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-card-desc"><?php esc_html_e( 'Permite atender solicitudes JSON-RPC en /wp-json/mcp/v1 autenticadas con Application Passwords.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>

							<div class="mcp-card-item">
								<input type="checkbox" id="mcp_allow_writes" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allow_writes]" value="1" <?php checked( $settings['allow_writes'] ); ?> />
								<div>
									<label for="mcp_allow_writes" class="mcp-card-label"><?php esc_html_e( 'Permitir operaciones de escritura', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-card-desc"><?php esc_html_e( 'Permite a la IA crear o modificar contenido (posts, productos, subir medios). Si está desmarcado, el servidor opera en modo solo lectura.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>

							<div class="mcp-card-item" style="border-left: 3px solid #d63638;">
								<input type="checkbox" id="mcp_allow_deletes" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allow_deletes]" value="1" <?php checked( $settings['allow_deletes'] ); ?> />
								<div>
									<label for="mcp_allow_deletes" class="mcp-card-label" style="color: #d63638;"><?php esc_html_e( 'Permitir operaciones destructivas (Eliminar)', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-card-desc"><?php esc_html_e( 'Permite a la IA enviar contenido a la papelera o eliminarlo permanentemente. Se recomienda mantener deshabilitado.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>
						</div>

						<!-- Caja 2: Módulos de Herramientas -->
						<div class="mcp-box">
							<h3 class="mcp-box-title">
								<span class="dashicons dashicons-screenoptions"></span>
								<?php esc_html_e( '2. Módulos y Herramientas Habilitadas', 'wp-mcp-agent-bridge' ); ?>
							</h3>
							<p style="font-size: 12px; color: #646970; margin-top: 0; margin-bottom: 14px;">
								<?php esc_html_e( 'Activa o desactiva qué áreas del sitio puede operar el asistente de IA.', 'wp-mcp-agent-bridge' ); ?>
							</p>

							<?php foreach ( $modules_info as $mod_key => $mod_data ) : ?>
								<div class="mcp-card-item">
									<input type="checkbox" id="mcp_mod_<?php echo esc_attr( $mod_key ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[modules][<?php echo esc_attr( $mod_key ); ?>]" value="1" <?php checked( ! empty( $settings['modules'][ $mod_key ] ) ); ?> />
									<div>
										<label for="mcp_mod_<?php echo esc_attr( $mod_key ); ?>" class="mcp-card-label">
											<span class="dashicons <?php echo esc_attr( $mod_data['icon'] ); ?>" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-top; margin-right: 4px; color: #2271b1;"></span>
											<?php echo esc_html( $mod_data['name'] ); ?>
											<span class="mcp-tag"><?php echo esc_html( $mod_data['tag'] ); ?></span>
										</label>
										<p class="mcp-card-desc"><?php echo esc_html( $mod_data['desc'] ); ?></p>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Caja 3: Auditoría -->
						<div class="mcp-box">
							<h3 class="mcp-box-title">
								<span class="dashicons dashicons-list-view"></span>
								<?php esc_html_e( '3. Auditoría de Actividad', 'wp-mcp-agent-bridge' ); ?>
							</h3>
							<div class="mcp-card-item">
								<input type="checkbox" id="mcp_activity_log" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[activity_log_enabled]" value="1" <?php checked( $settings['activity_log_enabled'] ); ?> />
								<div>
									<label for="mcp_activity_log" class="mcp-card-label"><?php esc_html_e( 'Registrar llamadas de herramientas', 'wp-mcp-agent-bridge' ); ?></label>
									<p class="mcp-card-desc"><?php esc_html_e( 'Guarda de forma rotativa las últimas 50 llamadas ejecutadas por la IA con usuario, fecha y resultado.', 'wp-mcp-agent-bridge' ); ?></p>
								</div>
							</div>
						</div>

						<div style="margin-top: 15px;">
							<?php submit_button( __( 'Guardar Configuración', 'wp-mcp-agent-bridge' ), 'primary', 'submit', false, array( 'class' => 'button button-primary mcp-btn-submit' ) ); ?>
						</div>
					</form>
				</div>

				<!-- COLUMNA DERECHA: Conexión con Clientes -->
				<div>
					<div class="mcp-box" style="border-top: 3px solid #2271b1;">
						<h3 class="mcp-box-title">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e( 'Conexión con Asistentes IA', 'wp-mcp-agent-bridge' ); ?>
						</h3>

						<label style="font-size: 12px; font-weight: 600; color: #50575e;"><?php esc_html_e( 'URL del Endpoint MCP (HTTP JSON-RPC):', 'wp-mcp-agent-bridge' ); ?></label>
						<input type="text" class="mcp-endpoint-input" readonly value="<?php echo esc_attr( $endpoint ); ?>" onclick="this.select();" />

						<div style="display: flex; justify-content: space-between; align-items: center; margin: 12px 0 6px;">
							<span style="font-size: 12px; font-weight: 600; color: #50575e;"><?php esc_html_e( 'Configuración JSON (.cursor/mcp.json):', 'wp-mcp-agent-bridge' ); ?></span>
							<button type="button" class="mcp-copy-btn" onclick="navigator.clipboard.writeText(document.getElementById('mcp_json_snippet').value); this.innerText='¡Copiado!'; setTimeout(() => this.innerText='Copiar', 2000);">
								<?php esc_html_e( 'Copiar JSON', 'wp-mcp-agent-bridge' ); ?>
							</button>
						</div>

						<textarea id="mcp_json_snippet" readonly style="width: 100%; height: 160px; font-family: monospace; font-size: 11.5px; background: #1e1e1e; color: #9cdcfe; border-radius: 4px; padding: 10px;"><?php echo esc_textarea( $json_str ); ?></textarea>

						<div style="background: #eef2fa; border-left: 3px solid #2271b1; padding: 10px 12px; border-radius: 4px; margin-top: 14px; font-size: 12px; color: #1a4282;">
							<strong><?php esc_html_e( 'Instrucciones:', 'wp-mcp-agent-bridge' ); ?></strong>
							<ol style="margin: 6px 0 0 16px; padding: 0;">
								<li><?php esc_html_e( 'Ve a Usuarios > Perfil y crea una "Contraseña de aplicación".', 'wp-mcp-agent-bridge' ); ?></li>
								<li><?php esc_html_e( 'Reemplaza TU_APPLICATION_PASSWORD_AQUI con tu contraseña.', 'wp-mcp-agent-bridge' ); ?></li>
								<li><?php esc_html_e( 'Pega el bloque en Cursor, Windsurf, Claude o Antigravity.', 'wp-mcp-agent-bridge' ); ?></li>
							</ol>
						</div>

						<div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid #f0f0f1; font-size: 12.5px; color: #646970;">
							<span><?php esc_html_e( 'Herramientas expuestas actualmente:', 'wp-mcp-agent-bridge' ); ?></span>
							<strong style="color: #2271b1; font-size: 14px; float: right;"><?php echo count( $tools ); ?></strong>
						</div>
					</div>
				</div>
			</div>

			<!-- Registro de Auditoría Inferior -->
			<div class="mcp-box" style="margin-top: 24px;">
				<h3 class="mcp-box-title">
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Registro en Vivo de Llamadas MCP', 'wp-mcp-agent-bridge' ); ?>
				</h3>

				<?php if ( empty( $logs ) ) : ?>
					<p style="font-size: 13px; color: #646970; font-style: italic; margin: 10px 0;">
						<?php esc_html_e( 'No hay llamadas registradas todavía. Cuando tu asistente de IA ejecute una herramienta, aparecerá aquí en tiempo real.', 'wp-mcp-agent-bridge' ); ?>
					</p>
				<?php else : ?>
					<table class="mcp-table">
						<thead>
							<tr>
								<th style="width: 160px;"><?php esc_html_e( 'Fecha / Hora', 'wp-mcp-agent-bridge' ); ?></th>
								<th style="width: 180px;"><?php esc_html_e( 'Herramienta', 'wp-mcp-agent-bridge' ); ?></th>
								<th style="width: 120px;"><?php esc_html_e( 'Usuario', 'wp-mcp-agent-bridge' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Estado', 'wp-mcp-agent-bridge' ); ?></th>
								<th><?php esc_html_e( 'Detalle / Mensaje', 'wp-mcp-agent-bridge' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $logs, 0, 15 ) as $log ) : ?>
								<tr>
									<td><?php echo esc_html( $log['timestamp'] ); ?></td>
									<td><code style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-weight: 600;"><?php echo esc_html( $log['tool'] ); ?></code></td>
									<td><?php echo esc_html( $log['user_name'] ); ?></td>
									<td>
										<?php if ( ! empty( $log['success'] ) ) : ?>
											<span class="mcp-badge-ok">✔ OK</span>
										<?php else : ?>
											<span class="mcp-badge-err">✖ Error</span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $log['message'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
