<?php
/**
 * Enough WordPress to render a shortcode.
 *
 * No WordPress required: the plugin file is loaded against these stubs, and
 * mavo_get_primary_hub() stands in for Mavo Hub Manager so the hub lookups can
 * be driven from a fixture. Same shape as that plugin's harness.
 */

define( 'ABSPATH', true );

/* ------------------------------------------------------------------ store */

$GLOBALS['MOCK_POSTS']   = [];  // id => [ title, path, status ]
$GLOBALS['MOCK_HUBS']    = [];  // id => [ 'geo' => hub id, 'theme' => hub id ]
$GLOBALS['MOCK_CURRENT'] = 0;
$GLOBALS['MOCK_LANG']    = '';
$GLOBALS['MOCK_EDITOR']  = true;

function mock_post( int $id, string $title, string $path, string $status = 'publish' ): void {
	$GLOBALS['MOCK_POSTS'][ $id ] = [ 'title' => $title, 'path' => $path, 'status' => $status ];
}

/** Put the renderer inside a post, with the hubs it owns. */
function mock_current( int $id, array $hubs = [] ): void {
	$GLOBALS['MOCK_CURRENT']       = $id;
	$GLOBALS['MOCK_HUBS'][ $id ]   = $hubs;
}

function reset_store(): void {
	$GLOBALS['MOCK_POSTS']   = [];
	$GLOBALS['MOCK_HUBS']    = [];
	$GLOBALS['MOCK_CURRENT'] = 0;
	$GLOBALS['MOCK_LANG']    = '';
	$GLOBALS['MOCK_EDITOR']  = true;
}

/* ------------------------------------------------------- WordPress stubs */

function add_action( ...$args ) {}
function add_shortcode( ...$args ) {}
function plugin_dir_url( $file ) { return '/wp-content/plugins/mavo-custom-shortcodes/'; }

function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return (string) $url; }
function esc_url_raw( $url ) { return (string) $url; }

function home_url( $path = '' ) { return 'https://www.mamanvoyage.com' . $path; }
function wp_parse_url( $url, $component = -1 ) {
	return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
}

function current_user_can( $capability ) { return (bool) $GLOBALS['MOCK_EDITOR']; }

function get_the_ID() { return $GLOBALS['MOCK_CURRENT'] ?: false; }
function get_post() { return null; }
function get_the_title( $id ) { return $GLOBALS['MOCK_POSTS'][ $id ]['title'] ?? ''; }
function get_permalink( $id ) { return 'https://www.mamanvoyage.com' . ( $GLOBALS['MOCK_POSTS'][ $id ]['path'] ?? '/' ); }
function get_post_status( $id ) { return $GLOBALS['MOCK_POSTS'][ $id ]['status'] ?? false; }

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$out = [];

	foreach ( $pairs as $key => $default ) {
		$out[ $key ] = array_key_exists( $key, (array) $atts ) ? $atts[ $key ] : $default;
	}

	return $out;
}

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

/* ------------------------------------------------- Mavo Hub Manager stub */

/**
 * Defined here, so the plugin's function_exists() guard sees the API as
 * present. harness-no-hub-manager.php sets MOCK_NO_HUB_MANAGER to leave it
 * undefined, which is the "plugin inactive" case.
 */
if ( empty( $GLOBALS['MOCK_NO_HUB_MANAGER'] ) ) {
	function mavo_get_primary_hub( int $post_id, string $type ) {
		return $GLOBALS['MOCK_HUBS'][ $post_id ][ $type ] ?? null;
	}
}

/* ------------------------------------------------------------ assertions */

$GLOBALS['MOCK_TESTS']  = 0;
$GLOBALS['MOCK_FAILED'] = 0;

function ok( bool $condition, string $label ): void {
	$GLOBALS['MOCK_TESTS']++;

	if ( $condition ) {
		echo "  ok   $label\n";

		return;
	}

	$GLOBALS['MOCK_FAILED']++;
	echo "  FAIL $label\n";
}

function is_same( $expected, $actual, string $label ): void {
	ok(
		$expected === $actual,
		$label . ( $expected === $actual ? '' : ' — expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) )
	);
}

/** Render the strip and collapse whitespace, so markup can be compared whole. */
function render( array $atts ): string {
	return trim( preg_replace( '/\s+/', ' ', mavo_hub_strip_shortcode( $atts ) ) );
}

function contains( array $atts, string $needle, string $label ): void {
	$html = render( $atts );

	ok( str_contains( $html, $needle ), $label . ( str_contains( $html, $needle ) ? '' : ' — got ' . $html ) );
}

function lacks( array $atts, string $needle, string $label ): void {
	ok( ! str_contains( render( $atts ), $needle ), $label );
}

function finish(): void {
	echo sprintf( "\n%d assertions, %d failed\n", $GLOBALS['MOCK_TESTS'], $GLOBALS['MOCK_FAILED'] );

	exit( $GLOBALS['MOCK_FAILED'] ? 1 : 0 );
}

require_once __DIR__ . '/../mavo-custom-shortcodes.php';

reset_store();
