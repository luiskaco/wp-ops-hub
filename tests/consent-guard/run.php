<?php
/**
 * Pruebas de Consent Guard contra el WordPress local.
 *
 * Uso (PHP 8.4+ con mysqli):
 *   php tests/consent-guard/run.php
 *
 * Carga wp-load.php, ejecuta las clases del plugin y deja la base de datos como estaba:
 * restaura los ajustes, borra las filas de registro y los contadores que crea.
 * Se niega a correr si el host no parece local.
 */

declare(strict_types=1);

use ConsentGuard\Category;
use ConsentGuard\ConsentLog;
use ConsentGuard\CookieDatabase;
use ConsentGuard\CookieRegistry;
use ConsentGuard\Frontend;
use ConsentGuard\Ip;
use ConsentGuard\RestController;
use ConsentGuard\ScriptBlocker;
use ConsentGuard\Services;
use ConsentGuard\Settings;
use ConsentGuard\Shortcodes;

if ( 'cli' !== PHP_SAPI ) {
	exit( "Solo CLI.\n" );
}

$host = getenv( 'CG_TEST_HOST' ) ?: 'pluginwordpress.test';
if ( ! preg_match( '/(\.test|\.local|localhost)$/', $host ) ) {
	exit( "Rechazado: el host '$host' no parece local.\n" );
}

$_SERVER['HTTP_HOST']      = $host;
$_SERVER['REMOTE_ADDR']    = '203.0.113.77'; // IP de documentación (RFC 5737).
$_SERVER['REQUEST_METHOD'] = 'GET';

require dirname( __DIR__, 2 ) . '/wp-load.php';

if ( ! class_exists( Settings::class ) ) {
	exit( "Consent Guard no está cargado: actívalo en el WordPress local (requiere PHP 8.4+).\n" );
}

// ---------------------------------------------------------------------------
// Mini framework de aserciones
// ---------------------------------------------------------------------------
$GLOBALS['cg_pass'] = 0;
$GLOBALS['cg_fail'] = array();

function cg_assert( bool $condition, string $name ): void {
	if ( $condition ) {
		++$GLOBALS['cg_pass'];
		echo "  ok   $name\n";
	} else {
		$GLOBALS['cg_fail'][] = $name;
		echo "  FAIL $name\n";
	}
}

function cg_section( string $title ): void {
	echo "\n$title\n";
}

/**
 * Busca el primer <script> cuyo src o contenido incluye $needle y devuelve sus atributos de interés.
 *
 * @return array{type: ?string, category: ?string, orig_type: ?string}|null
 */
function cg_script( string $html, string $needle ): ?array {
	$p = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( array( 'tag_name' => 'SCRIPT' ) ) ) {
		$src = (string) $p->get_attribute( 'src' );
		if ( str_contains( $src, $needle ) || str_contains( (string) $p->get_modifiable_text(), $needle ) ) {
			$get = static fn ( string $name ): ?string => is_string( $p->get_attribute( $name ) ) ? $p->get_attribute( $name ) : null;
			return array(
				'type'      => $get( 'type' ),
				'category'  => $get( 'data-cg-category' ),
				'orig_type' => $get( 'data-cg-type' ),
			);
		}
	}
	return null;
}

function cg_blocked( ?array $script, string $category ): bool {
	return null !== $script && 'text/plain' === $script['type'] && $category === $script['category'];
}

$original_settings = get_option( Settings::OPTION );
$original_cookies  = get_option( CookieRegistry::OPTION );
$test_ids          = array();
$rl_key            = 'cg_rl_' . substr( hash_hmac( 'sha256', Ip::anonymize( '203.0.113.77' ), wp_salt( 'nonce' ) ), 0, 32 );

try {
	// -----------------------------------------------------------------------
	cg_section( 'Ajustes: saneamiento estricto' );
	// -----------------------------------------------------------------------
	$clean = Settings::sanitizeTab(
		'banner',
		array(
			'enabled'      => '1',
			'layout'       => 'evil',
			'position'     => 'bottom-right',
			'title'        => '<script>alert(1)</script>Hola',
			'message'      => 'Texto <a href="javascript:alert(1)">x</a> <a href="https://ok.test/p" onclick="alert(1)">ok</a><img src=x onerror=alert(1)><strong>b</strong>',
			'color_bg'     => 'red;background:url(x)',
			'color_text'   => '#123456',
			'privacy_url'  => 'javascript:alert(1)',
			'consent_days' => '9999',
		)
	);
	cg_assert( 'box' === $clean['layout'], 'layout inválido vuelve al valor por defecto' );
	cg_assert( 'bottom-right' === $clean['position'], 'posición válida se respeta' );
	cg_assert( ! str_contains( $clean['title'], '<' ), 'el título no admite HTML' );
	cg_assert( ! str_contains( $clean['message'], 'javascript:' ), 'el mensaje elimina enlaces javascript:' );
	cg_assert( ! str_contains( $clean['message'], 'onclick' ) && ! str_contains( $clean['message'], 'onerror' ), 'el mensaje elimina atributos de eventos' );
	cg_assert( ! str_contains( $clean['message'], '<img' ), 'el mensaje elimina etiquetas no permitidas' );
	cg_assert( str_contains( $clean['message'], 'href="https://ok.test/p"' ) && str_contains( $clean['message'], '<strong>' ), 'el mensaje conserva enlaces https y negrita' );
	cg_assert( '#ffffff' === $clean['color_bg'], 'color inválido (inyección CSS) vuelve al valor por defecto' );
	cg_assert( '#123456' === $clean['color_text'], 'color hexadecimal válido se respeta' );
	cg_assert( '' === $clean['privacy_url'], 'URL javascript: se descarta' );
	cg_assert( Settings::MAX_CONSENT_DAYS === $clean['consent_days'], 'días de consentimiento limitados a 395' );

	$rules = Settings::parseRules( "abc | analytics\nwidget.ejemplo.com | functional\nads.test | necessary\nfoo.test | inventada\npixel.test | Marketing" );
	cg_assert( 2 === count( $rules ), 'reglas: se descartan patrones cortos, categorías inválidas y «necessary»' );
	cg_assert( 'marketing' === ( $rules[1]['category'] ?? '' ), 'reglas: la categoría no distingue mayúsculas' );

	$bad_tab = Settings::sanitizeTab( 'banner', array( 'title' => array( 'x' ) ) );
	cg_assert( is_string( $bad_tab['title'] ) && '' !== $bad_tab['title'], 'un array donde se espera texto no rompe el guardado' );

	// -----------------------------------------------------------------------
	cg_section( 'Bloqueo de scripts' );
	// -----------------------------------------------------------------------
	Settings::save( array_merge( Settings::defaults(), array( 'gcm_mode' => 'basic' ) ) );
	$rules = Services::rules();
	$html  = '<!doctype html><html><head>'
		. '<script async src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>'
		. '<script>window.dataLayer=window.dataLayer||[];gtag("config","G-TEST");</script>'
		. '<script type="module" src="https://connect.facebook.net/en_US/fbevents.js"></script>'
		. '<script type="application/ld+json">{"gtag(":"no tocar"}</script>'
		. '<script id="consent-guard-gcm">gtag("consent","default",{});</script>'
		. '<script src="/wp-includes/js/jquery/jquery.min.js"></script>'
		. '<script data-cg-category="necessary" src="https://static.hotjar.com/c/hotjar-1.js"></script>'
		. '<script data-cg-category="functional">console.log("widget")</script>'
		. '<script type="text/plain" data-cg-category="analytics">ya bloqueado</script>'
		. '</head><body></body></html>';
	$out = ScriptBlocker::process( $html, $rules );

	cg_assert( cg_blocked( cg_script( $out, 'googletagmanager.com/gtag/js' ), 'analytics' ), 'gtag.js externo queda bloqueado como analytics' );
	cg_assert( cg_blocked( cg_script( $out, 'gtag("config"' ), 'analytics' ), 'gtag en línea queda bloqueado' );
	$fb = cg_script( $out, 'connect.facebook.net' );
	cg_assert( cg_blocked( $fb, 'marketing' ) && 'module' === $fb['orig_type'], 'Meta Pixel queda bloqueado y conserva type=module' );
	cg_assert( 'application/ld+json' === cg_script( $out, '"no tocar"' )['type'], 'JSON-LD no se toca' );
	cg_assert( null === cg_script( $out, 'gtag("consent"' )['type'], 'el script propio no se bloquea' );
	cg_assert( null === cg_script( $out, 'jquery.min.js' )['type'], 'scripts normales no se tocan' );
	cg_assert( null === cg_script( $out, 'static.hotjar.com' )['type'], 'marcado a mano como necesario: no se bloquea' );
	cg_assert( cg_blocked( cg_script( $out, 'console.log("widget")' ), 'functional' ), 'marcado a mano como funcional: se bloquea' );
	cg_assert( cg_blocked( cg_script( $out, 'ya bloqueado' ), 'analytics' ) && null === cg_script( $out, 'ya bloqueado' )['orig_type'], 'un script ya bloqueado queda igual' );
	cg_assert( 'sin scripts' === ScriptBlocker::process( 'sin scripts', $rules ), 'HTML sin scripts vuelve intacto' );

	Settings::save( array_merge( Settings::defaults(), array( 'gcm_mode' => 'advanced' ) ) );
	$adv = ScriptBlocker::process( $html, Services::rules() );
	cg_assert( null === cg_script( $adv, 'googletagmanager.com/gtag/js' )['type'], 'Consent Mode avanzado: Google no se bloquea' );
	cg_assert( cg_blocked( cg_script( $adv, 'connect.facebook.net' ), 'marketing' ), 'Consent Mode avanzado: Meta sigue bloqueado' );
	Settings::save( Settings::defaults() );

	// -----------------------------------------------------------------------
	cg_section( 'IP anonimizada' );
	// -----------------------------------------------------------------------
	cg_assert( '203.0.113.0' === Ip::anonymize( '203.0.113.77' ), 'IPv4 a /24' );
	cg_assert( '2001:db8:abcd::' === Ip::anonymize( '2001:db8:abcd:12:3456::1' ), 'IPv6 a /48' );
	cg_assert( '' === Ip::anonymize( 'no-es-ip' ) && '' === Ip::anonymize( '' ), 'texto inválido devuelve vacío' );
	$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.9';
	cg_assert( '203.0.113.77' === Ip::client(), 'X-Forwarded-For se ignora (no se puede falsificar la IP)' );
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

	// -----------------------------------------------------------------------
	cg_section( 'Endpoint REST de consentimiento' );
	// -----------------------------------------------------------------------
	delete_transient( $rl_key );
	$send = static function ( array $body, ?string $origin = null ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/consent-guard/v1/consent' );
		$request->set_header( 'content-type', 'application/json' );
		if ( null !== $origin ) {
			$request->set_header( 'origin', $origin );
		}
		$request->set_body( (string) wp_json_encode( $body ) );
		return rest_do_request( $request );
	};

	$id1        = bin2hex( random_bytes( 16 ) );
	$test_ids[] = $id1;
	$res        = $send( array( 'id' => $id1, 'action' => 'custom', 'categories' => array( 'analytics' ), 'revision' => 1 ), home_url() );
	cg_assert( 201 === $res->get_status(), 'consentimiento válido se guarda (201)' );

	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ConsentLog::table() . ' WHERE consent_id = %s', $id1 ) ); // phpcs:ignore
	cg_assert( $row && 'analytics' === $row->categories && '203.0.113.0' === $row->ip_anon, 'la fila guarda categorías e IP anonimizada' );

	$id2        = bin2hex( random_bytes( 16 ) );
	$test_ids[] = $id2;
	$send( array( 'id' => $id2, 'action' => 'accept_all', 'categories' => array(), 'revision' => 99 ) );
	$row2 = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ConsentLog::table() . ' WHERE consent_id = %s', $id2 ) ); // phpcs:ignore
	cg_assert( $row2 && 'functional,analytics,marketing' === $row2->categories, '«aceptar todo» guarda todas las categorías aunque el cliente mande otra cosa' );
	cg_assert( $row2 && 1 === (int) $row2->revision, 'una revisión futura se recorta a la actual' );

	cg_assert( 400 === $send( array( 'id' => 'xyz', 'action' => 'custom', 'categories' => array(), 'revision' => 1 ) )->get_status(), 'ID con formato inválido: 400' );
	cg_assert( 400 === $send( array( 'id' => $id1, 'action' => 'custom', 'categories' => array( 'necessary' ), 'revision' => 1 ) )->get_status(), 'categoría no opcional: 400' );
	cg_assert( 400 === $send( array( 'id' => $id1, 'action' => 'hack', 'categories' => array(), 'revision' => 1 ) )->get_status(), 'acción desconocida: 400' );
	cg_assert( 400 === $send( array( 'id' => $id1, 'action' => 'custom', 'categories' => array( '<script>' ), 'revision' => 1 ) )->get_status(), 'categoría con HTML: 400' );
	cg_assert( 403 === $send( array( 'id' => $id1, 'action' => 'custom', 'categories' => array(), 'revision' => 1 ), 'https://evil.example' )->get_status(), 'Origin de otro dominio: 403' );

	set_transient( $rl_key, RestController::RATE_LIMIT, RestController::RATE_WINDOW );
	cg_assert( 429 === $send( array( 'id' => $id1, 'action' => 'custom', 'categories' => array(), 'revision' => 1 ) )->get_status(), 'por encima del límite de peticiones: 429' );
	delete_transient( $rl_key );

	Settings::save( array_merge( Settings::defaults(), array( 'log_consents' => false ) ) );
	cg_assert( 403 === $send( array( 'id' => $id1, 'action' => 'custom', 'categories' => array(), 'revision' => 1 ) )->get_status(), 'registro desactivado: 403' );
	Settings::save( Settings::defaults() );
	delete_transient( $rl_key );

	// -----------------------------------------------------------------------
	cg_section( 'Exportación CSV y cookies' );
	// -----------------------------------------------------------------------
	cg_assert( "'=HYPERLINK(1)" === ConsentLog::csvCell( '=HYPERLINK(1)' ) && "'+1" === ConsentLog::csvCell( '+1' ), 'CSV: se neutralizan fórmulas' );
	cg_assert( 'analytics' === ConsentLog::csvCell( 'analytics' ), 'CSV: texto normal intacto' );

	cg_assert( '' === CookieRegistry::sanitizeName( 'a b' ) && '' === CookieRegistry::sanitizeName( '<x>' ) && '' === CookieRegistry::sanitizeName( 'a;b' ), 'nombres de cookie inválidos se rechazan' );
	cg_assert( '_ga_' === CookieRegistry::sanitizeName( '_ga_' ), 'nombre con comodín válido' );

	$scripts = \ConsentGuard\Scanner::thirdPartyScripts( '<script id="consent-guard-gcm">gtag("consent")</script><script src="https://cdn.test/a.js"></script><p>gtag( en texto</p>' );
	cg_assert( ! str_contains( $scripts, 'gtag(' ) && str_contains( $scripts, 'cdn.test/a.js' ), 'escáner: ignora el script propio y el texto, mira scripts ajenos' );

	$ga = CookieDatabase::find( '_ga' );
	cg_assert( null !== $ga && Category::Analytics === $ga['category'], 'Open Cookie Database: _ga es analítica' );
	$ga4 = CookieDatabase::find( '_ga_ABC123XYZ' );
	cg_assert( null !== $ga4 && Category::Analytics === $ga4['category'], 'Open Cookie Database: _ga_<id> se reconoce por comodín' );

	// -----------------------------------------------------------------------
	cg_section( 'Salida escapada' );
	// -----------------------------------------------------------------------
	update_option( CookieRegistry::OPTION, array(), false );
	CookieRegistry::upsert(
		array(
			'name'        => '_evil',
			'category'    => 'analytics',
			'description' => '<img src=x onerror=alert(1)>desc',
			'source'      => '"><script>alert(2)</script>',
		)
	);

	// Ajustes manipulados directamente en la base (sin pasar por el saneamiento).
	Settings::save(
		array_merge(
			Settings::defaults(),
			array(
				'title'        => '<script>alert(3)</script>',
				'color_bg'     => 'red;}</style><script>alert(4)</script>',
				'layout'       => '"><script>alert(5)</script>',
				'privacy_url'  => 'javascript:alert(6)',
				'message'      => '<iframe src=//evil></iframe>ok',
				'accept_label' => '<b onmouseover=alert(7)>',
			)
		)
	);
	ob_start();
	Frontend::render();
	$banner = (string) ob_get_clean();
	// Se buscan etiquetas y atributos reales; el texto escapado (&lt;script&gt;) es inofensivo.
	cg_assert( ! preg_match( '/<script|<iframe|<b\s|href="javascript:|"><script/i', $banner ), 'el banner escapa todo lo que viene de la base de datos' );
	cg_assert( str_contains( $banner, '&lt;b onmouseover=alert(7)&gt;' ), 'las etiquetas de los botones se muestran como texto' );
	cg_assert( str_contains( $banner, '--cg-bg:#ffffff' ), 'un color manipulado se reemplaza por el valor por defecto' );

	$table = Shortcodes::cookieTable();
	cg_assert( ! preg_match( '/<script|<img/i', $table ), 'el shortcode de cookies escapa la salida' );
	$button = Shortcodes::preferencesButton( array( 'label' => '<img src=x onerror=alert(1)>' ) );
	cg_assert( ! str_contains( $button, '<img' ), 'el shortcode del botón escapa la etiqueta' );

	$config = Frontend::config();
	cg_assert( array( 'functional', 'analytics', 'marketing' ) === $config['categories'], 'la configuración del JS solo expone categorías opcionales' );
	cg_assert( in_array( '_evil', $config['cookies']['analytics'] ?? array(), true ), 'la configuración incluye las cookies a borrar por categoría' );
} finally {
	// Restaurar el estado.
	if ( false === $original_settings ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original_settings, true );
	}
	if ( false === $original_cookies ) {
		delete_option( CookieRegistry::OPTION );
	} else {
		update_option( CookieRegistry::OPTION, $original_cookies, false );
	}
	Settings::flush();
	delete_transient( $rl_key );
	foreach ( $test_ids as $consent_id ) {
		$GLOBALS['wpdb']->delete( ConsentLog::table(), array( 'consent_id' => $consent_id ), array( '%s' ) );
	}
}

$fails = count( $GLOBALS['cg_fail'] );
echo "\nResultado: {$GLOBALS['cg_pass']} ok, {$fails} fallos.\n";
exit( $fails > 0 ? 1 : 0 );
