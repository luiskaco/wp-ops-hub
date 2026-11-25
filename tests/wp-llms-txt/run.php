<?php
/**
 * Pruebas de WP LLMs.txt Manager contra el WordPress local.
 *
 * Uso (PHP con mysqli, mbstring, openssl y curl):
 *   php tests/wp-llms-txt/run.php
 *
 * Carga wp-load.php y prueba los métodos del plugin (los privados, con Reflection).
 * Deja todo como estaba: restaura las opciones del plugin, borra las páginas de prueba
 * y solo borra archivos de la raíz que el propio test creó.
 * Se niega a correr si el host no parece local o si ya hay un llms.txt físico en la raíz.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Solo CLI.\n" );
}

$llt_host = getenv( 'LLT_TEST_HOST' ) ? getenv( 'LLT_TEST_HOST' ) : 'pluginwordpress.test';
if ( ! preg_match( '/(\.test|\.local|localhost)$/', $llt_host ) ) {
	exit( "Rechazado: el host '$llt_host' no parece local.\n" );
}

$_SERVER['HTTP_HOST']      = $llt_host;
$_SERVER['REQUEST_METHOD'] = 'GET';

require dirname( __DIR__, 2 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

if ( ! class_exists( 'WP_LLMs_Txt_Manager' ) ) {
	exit( "WP LLMs.txt Manager no está cargado: actívalo en el WordPress local.\n" );
}

$llt_root = untrailingslashit( ABSPATH );
foreach ( array( 'llms.txt', 'llms-full.txt' ) as $llt_name ) {
	if ( file_exists( $llt_root . '/' . $llt_name ) ) {
		exit( "Rechazado: ya existe $llt_name en la raíz del WordPress local; muévelo antes de correr las pruebas.\n" );
	}
}

// ---------------------------------------------------------------------------
// Mini framework de aserciones y utilidades
// ---------------------------------------------------------------------------
$GLOBALS['llt_pass'] = 0;
$GLOBALS['llt_fail'] = array();

function llt_assert( $condition, $name ) {
	if ( $condition ) {
		++$GLOBALS['llt_pass'];
		echo "  ok   $name\n";
	} else {
		$GLOBALS['llt_fail'][] = $name;
		echo "  FAIL $name\n";
	}
}

function llt_section( $title ) {
	echo "\n$title\n";
}

/** Llama a un método (público o privado) del singleton. */
function llt_call( $method, ...$args ) {
	$ref = new ReflectionMethod( 'WP_LLMs_Txt_Manager', $method );
	$ref->setAccessible( true );
	return $ref->invokeArgs( WP_LLMs_Txt_Manager::get_instance(), $args );
}

/** Lee o reemplaza las opciones en memoria del singleton. */
function llt_options( $set = null ) {
	$ref = new ReflectionProperty( 'WP_LLMs_Txt_Manager', 'options' );
	$ref->setAccessible( true );
	if ( null !== $set ) {
		$ref->setValue( WP_LLMs_Txt_Manager::get_instance(), $set );
	}
	return $ref->getValue( WP_LLMs_Txt_Manager::get_instance() );
}

/** Códigos de los avisos del panel emitidos desde el último reset. */
function llt_notices() {
	return wp_list_pluck( get_settings_errors( 'wp_llms_txt_messages' ), 'code' );
}

function llt_reset_notices() {
	$GLOBALS['wp_settings_errors'] = array();
}

/** Crea un archivo en la raíz y lo anota para borrarlo al final. */
function llt_put_root_file( $name, $body ) {
	$path = untrailingslashit( ABSPATH ) . '/' . $name;
	file_put_contents( $path, $body );
	$GLOBALS['llt_created'][ $name ] = true;
	return $path;
}

function llt_remove_root_files() {
	foreach ( array_keys( $GLOBALS['llt_created'] ) as $name ) {
		$path = untrailingslashit( ABSPATH ) . '/' . $name;
		if ( file_exists( $path ) ) {
			unlink( $path );
		}
	}
}

// Estado original, restaurado pase lo que pase.
$GLOBALS['llt_created']      = array();
$GLOBALS['llt_posts']        = array();
$GLOBALS['llt_orig_options'] = get_option( WP_LLMs_Txt_Manager::OPTION_KEY, null );
$GLOBALS['llt_orig_managed'] = get_option( WP_LLMs_Txt_Manager::MANAGED_FILES_KEY, null );
$GLOBALS['llt_orig_memory']  = llt_options();

register_shutdown_function(
	function () {
		llt_remove_root_files();
		foreach ( $GLOBALS['llt_posts'] as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( array(
			WP_LLMs_Txt_Manager::OPTION_KEY        => $GLOBALS['llt_orig_options'],
			WP_LLMs_Txt_Manager::MANAGED_FILES_KEY => $GLOBALS['llt_orig_managed'],
		) as $key => $value ) {
			if ( null === $value ) {
				delete_option( $key );
			} else {
				update_option( $key, $value );
			}
		}
		llt_options( $GLOBALS['llt_orig_memory'] );
	}
);

// wp_die() lanza una excepción para poder comprobarlo sin cortar el script.
add_filter(
	'wp_die_handler',
	function () {
		return function ( $message ) {
			throw new RuntimeException( is_string( $message ) ? $message : 'wp_die' );
		};
	}
);

echo "\n--- Pruebas: WP LLMs.txt Manager ---\n";

// ---------------------------------------------------------------------------
llt_section( '1. Versión y opciones por defecto' );
// ---------------------------------------------------------------------------
$llt_header = get_file_data( WP_LLMS_TXT_FILE, array( 'Version' => 'Version' ) );
llt_assert( $llt_header['Version'] === WP_LLMs_Txt_Manager::VERSION, 'la cabecera y la constante VERSION coinciden' );

update_option( WP_LLMs_Txt_Manager::OPTION_KEY, 'basura-no-array' );
$llt_defaults = WP_LLMs_Txt_Manager::get_instance()->get_options();
llt_assert( '' === $llt_defaults['llms_txt'] && '' === $llt_defaults['llms_full_txt'], 'opción corrupta: contenidos vacíos por defecto' );
llt_assert( false === $llt_defaults['sync_physical_file'], 'por defecto no escribe archivos físicos' );
llt_assert( true === $llt_defaults['enable_full_txt'], 'por defecto /llms-full.txt está habilitado' );

// ---------------------------------------------------------------------------
llt_section( '2. Sanitización del Markdown' );
// ---------------------------------------------------------------------------
$llt_clean = llt_call( 'sanitize_markdown_text', "  # Título\r\n\r\n> Resumen\0\r- [Link](https://x.test): *a* **b**  \n" );
llt_assert( false === strpos( $llt_clean, "\0" ), 'quita bytes nulos' );
llt_assert( false === strpos( $llt_clean, "\r" ), 'normaliza saltos de línea a LF' );
llt_assert( "# Título\n\n> Resumen\n- [Link](https://x.test): *a* **b**" === $llt_clean, 'conserva la sintaxis Markdown y recorta espacios' );

// ---------------------------------------------------------------------------
llt_section( '3. Sin redirección a barra final' );
// ---------------------------------------------------------------------------
$llt_home = untrailingslashit( home_url( '/' ) );
$llt_mgr  = WP_LLMs_Txt_Manager::get_instance();
llt_assert( false === $llt_mgr->prevent_canonical_redirect( $llt_home . '/llms.txt/', $llt_home . '/llms.txt' ), '/llms.txt no se redirige' );
llt_assert( false === $llt_mgr->prevent_canonical_redirect( $llt_home . '/llms-full.txt/', $llt_home . '/llms-full.txt' ), '/llms-full.txt no se redirige' );
llt_assert( 'https://x.test/a/' === $llt_mgr->prevent_canonical_redirect( 'https://x.test/a/', $llt_home . '/blog/llms.txt' ), 'un llms.txt fuera de la raíz sigue la redirección normal' );
llt_assert( 'https://x.test/b/' === $llt_mgr->prevent_canonical_redirect( 'https://x.test/b/', $llt_home . '/llms.txt.bak' ), 'otras URLs no se tocan' );

// ---------------------------------------------------------------------------
llt_section( '4. Borrador automático' );
// ---------------------------------------------------------------------------
$llt_page_args = array(
	'post_type'    => 'page',
	'post_content' => 'x',
	'menu_order'   => -99999,
);
$GLOBALS['llt_posts'][] = wp_insert_post( $llt_page_args + array( 'post_title' => 'LLT Pagina Publica', 'post_status' => 'publish', 'post_excerpt' => '<b>Resumen</b> publico' ) );
$GLOBALS['llt_posts'][] = wp_insert_post( $llt_page_args + array( 'post_title' => 'LLT Pagina Secreta', 'post_status' => 'publish', 'post_password' => 'clave-de-prueba' ) );
$GLOBALS['llt_posts'][] = wp_insert_post( $llt_page_args + array( 'post_title' => 'LLT Pagina Borrador', 'post_status' => 'draft' ) );

$llt_draft = $llt_mgr->build_default_llms_txt();
llt_assert( 0 === strpos( $llt_draft, '# ' . get_bloginfo( 'name' ) ), 'empieza con el nombre del sitio como título' );
llt_assert( false !== strpos( $llt_draft, '[LLT Pagina Publica](' ), 'incluye las páginas publicadas' );
llt_assert( false !== strpos( $llt_draft, '): Resumen publico' ), 'usa el extracto sin HTML' );
llt_assert( false === strpos( $llt_draft, 'LLT Pagina Secreta' ), 'excluye las páginas con contraseña' );
llt_assert( false === strpos( $llt_draft, 'LLT Pagina Borrador' ), 'excluye los borradores' );

// ---------------------------------------------------------------------------
llt_section( '5. Panel: permisos, nonce y guardado' );
// ---------------------------------------------------------------------------
$llt_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$llt_admin  = $llt_admins ? (int) $llt_admins[0] : 0;
llt_assert( $llt_admin > 0, 'hay un administrador en el WordPress local' );

// Sin permisos.
wp_set_current_user( 0 );
$_POST = array( 'wp_llms_txt_nonce' => 'x', 'wp_llms_txt_submit' => '1' );
$llt_died = false;
try {
	$llt_mgr->handle_admin_actions();
} catch ( RuntimeException $e ) {
	$llt_died = true;
}
llt_assert( $llt_died, 'un usuario sin manage_options no puede guardar' );

// Nonce inválido.
wp_set_current_user( $llt_admin );
$llt_died = false;
try {
	$llt_mgr->handle_admin_actions();
} catch ( RuntimeException $e ) {
	$llt_died = true;
}
llt_assert( $llt_died, 'un nonce inválido corta la acción' );

// El borrador no se guarda hasta pulsar «Guardar».
update_option( WP_LLMs_Txt_Manager::OPTION_KEY, array( 'llms_txt' => 'GUARDADO' ) );
llt_options( $llt_mgr->get_options() );
llt_reset_notices();
$_POST = array(
	'wp_llms_txt_nonce'          => wp_create_nonce( 'wp_llms_txt_save_action' ),
	'wp_llms_txt_generate_draft' => '1',
);
$llt_mgr->handle_admin_actions();
$llt_saved = get_option( WP_LLMs_Txt_Manager::OPTION_KEY );
llt_assert( 'GUARDADO' === $llt_saved['llms_txt'], 'generar borrador no toca lo guardado' );
llt_assert( 0 === strpos( llt_options()['llms_txt'], '# ' ), 'el borrador queda solo en el editor' );

// Guardado real.
$_POST = array(
	'wp_llms_txt_nonce'  => wp_create_nonce( 'wp_llms_txt_save_action' ),
	'wp_llms_txt_submit' => '1',
	'llms_txt'           => wp_slash( "# Sitio\r\n\r\n> Hola \"mundo\"\0" ),
	'llms_full_txt'      => '',
);
$llt_mgr->handle_admin_actions();
$llt_saved = get_option( WP_LLMs_Txt_Manager::OPTION_KEY );
llt_assert( "# Sitio\n\n> Hola \"mundo\"" === $llt_saved['llms_txt'], 'guarda el contenido sanitizado y sin barras escapadas' );
llt_assert( false === $llt_saved['enable_full_txt'] && false === $llt_saved['sync_physical_file'], 'las casillas sin marcar se guardan como false' );
$_POST = array();

// ---------------------------------------------------------------------------
llt_section( '6. Archivos físicos: nunca pisar ni borrar lo ajeno' );
// ---------------------------------------------------------------------------
update_option( WP_LLMs_Txt_Manager::MANAGED_FILES_KEY, array(), false );
llt_options( array( 'llms_txt' => 'CONTENIDO PLUGIN', 'llms_full_txt' => 'FULL PLUGIN', 'sync_physical_file' => true, 'enable_full_txt' => true ) );

// Archivo ajeno previo.
$llt_foreign = llt_put_root_file( 'llms.txt', 'AJENO' );
llt_reset_notices();
llt_call( 'write_physical_files' );
$GLOBALS['llt_created']['llms-full.txt'] = true;
llt_assert( 'AJENO' === file_get_contents( $llt_foreign ), 'no sobrescribe un llms.txt ajeno' );
llt_assert( in_array( 'foreign_file_llms.txt', llt_notices(), true ), 'avisa que el archivo ajeno no se sobrescribió' );
llt_assert( 'FULL PLUGIN' === file_get_contents( $llt_root . '/llms-full.txt' ), 'escribe el llms-full.txt que no existía' );
$llt_found = llt_call( 'get_foreign_physical_files' );
llt_assert( isset( $llt_found['llms.txt'] ) && ! isset( $llt_found['llms-full.txt'] ), 'distingue el archivo ajeno del creado por el plugin' );

llt_call( 'remove_physical_files' );
llt_assert( file_exists( $llt_foreign ), 'al desactivar la sincronización no borra el archivo ajeno' );
llt_assert( ! file_exists( $llt_root . '/llms-full.txt' ), 'borra el archivo que creó el plugin' );
unlink( $llt_foreign );

// Archivo propio modificado a mano después.
llt_call( 'write_physical_files' );
$llt_own = $llt_root . '/llms.txt';
llt_assert( 'CONTENIDO PLUGIN' === file_get_contents( $llt_own ), 'escribe llms.txt cuando la raíz está libre' );
$llt_managed = get_option( WP_LLMs_Txt_Manager::MANAGED_FILES_KEY );
llt_assert( md5_file( $llt_own ) === $llt_managed['llms.txt'], 'guarda el hash del archivo creado' );
file_put_contents( $llt_own, 'EDITADO A MANO' );
llt_call( 'remove_physical_files' );
llt_assert( 'EDITADO A MANO' === file_get_contents( $llt_own ), 'no borra un archivo propio que alguien editó después' );
llt_remove_root_files();

// llms-full deshabilitado: se retira su copia.
update_option( WP_LLMs_Txt_Manager::MANAGED_FILES_KEY, array(), false );
llt_call( 'write_physical_files' );
llt_options( array( 'llms_txt' => 'CONTENIDO PLUGIN', 'llms_full_txt' => 'FULL PLUGIN', 'sync_physical_file' => true, 'enable_full_txt' => false ) );
llt_call( 'write_physical_files' );
llt_assert( ! file_exists( $llt_root . '/llms-full.txt' ), 'deshabilitar llms-full retira su archivo físico' );
llt_call( 'remove_physical_files' );
llt_remove_root_files();

// ---------------------------------------------------------------------------
llt_section( '7. Importar un archivo ajeno al editor' );
// ---------------------------------------------------------------------------
update_option( WP_LLMs_Txt_Manager::MANAGED_FILES_KEY, array(), false );
update_option( WP_LLMs_Txt_Manager::OPTION_KEY, array( 'llms_txt' => 'EN BD' ) );
llt_options( array( 'llms_txt' => 'ANTES', 'llms_full_txt' => 'ANTES FULL', 'sync_physical_file' => false, 'enable_full_txt' => true ) );

llt_reset_notices();
llt_call( 'import_foreign_file', '../wp-config.php' );
llt_assert( in_array( 'import_not_found', llt_notices(), true ) && 'ANTES' === llt_options()['llms_txt'], 'rechaza nombres fuera de la lista (path traversal)' );

llt_put_root_file( 'llms.txt', "\xEF\xBB\xBF# Importado\r\nlinea \xFF rota\0\r\n" );
llt_reset_notices();
llt_call( 'import_foreign_file', 'llms.txt' );
$llt_imported = llt_options()['llms_txt'];
llt_assert( in_array( 'import_done', llt_notices(), true ), 'importa el llms.txt ajeno' );
llt_assert( 0 === strpos( $llt_imported, '# Importado' ), 'quita el BOM' );
llt_assert( false === strpos( $llt_imported, "\r" ) && false === strpos( $llt_imported, "\0" ), 'normaliza saltos y quita nulos' );
llt_assert( 1 === preg_match( '//u', $llt_imported ), 'el resultado es UTF-8 válido' );
llt_assert( file_exists( $llt_root . '/llms.txt' ), 'importar no borra el archivo físico' );
llt_assert( 'EN BD' === get_option( WP_LLMs_Txt_Manager::OPTION_KEY )['llms_txt'], 'importar no guarda en la base de datos' );

llt_put_root_file( 'llms-full.txt', str_repeat( 'a', WP_LLMs_Txt_Manager::MAX_IMPORT_BYTES + 1 ) );
llt_reset_notices();
llt_call( 'import_foreign_file', 'llms-full.txt' );
llt_assert( in_array( 'import_too_big', llt_notices(), true ) && 'ANTES FULL' === llt_options()['llms_full_txt'], 'rechaza archivos de más de 2 MB' );
llt_remove_root_files();

// ---------------------------------------------------------------------------
llt_section( '8. Endpoints por HTTP' );
// ---------------------------------------------------------------------------
$llt_marker = '# LLT ' . wp_generate_password( 12, false );
update_option( WP_LLMs_Txt_Manager::OPTION_KEY, array( 'llms_txt' => $llt_marker, 'llms_full_txt' => '', 'sync_physical_file' => false, 'enable_full_txt' => false ) );

$llt_res = wp_remote_get( home_url( '/llms.txt' ), array( 'redirection' => 0, 'timeout' => 15 ) );
if ( is_wp_error( $llt_res ) ) {
	llt_assert( false, 'el sitio local responde por HTTP (' . $llt_res->get_error_message() . ')' );
} else {
	llt_assert( 200 === wp_remote_retrieve_response_code( $llt_res ), '/llms.txt responde 200 sin redirección' );
	llt_assert( 0 === strpos( wp_remote_retrieve_header( $llt_res, 'content-type' ), 'text/plain' ), 'se sirve como text/plain' );
	llt_assert( 'nosniff' === wp_remote_retrieve_header( $llt_res, 'x-content-type-options' ), 'envía X-Content-Type-Options: nosniff' );
	llt_assert( $llt_marker === wp_remote_retrieve_body( $llt_res ), 'entrega el contenido guardado' );

	$llt_full = wp_remote_get( home_url( '/llms-full.txt' ), array( 'redirection' => 0, 'timeout' => 15 ) );
	llt_assert( 404 === wp_remote_retrieve_response_code( $llt_full ), '/llms-full.txt deshabilitado responde 404' );
}

// ---------------------------------------------------------------------------
echo "\n----------------------------------------\n";
$llt_total = $GLOBALS['llt_pass'] + count( $GLOBALS['llt_fail'] );
echo "Total: $llt_total | ok: {$GLOBALS['llt_pass']} | fallidas: " . count( $GLOBALS['llt_fail'] ) . "\n";
exit( $GLOBALS['llt_fail'] ? 1 : 0 );
