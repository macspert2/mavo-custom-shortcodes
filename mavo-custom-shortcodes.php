<?php
/**
 * Plugin Name: Mavo Custom Shortcodes
 * Description: Provides a compact shortcode strip linking posts to their owning hub pages. With place for more shortcodes later.
 * Version: 0.1.0
 * Author: Maman Voyage
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MAVO_CUSTOM_SHORTCODES_VERSION', '0.1.0' );
define( 'MAVO_CUSTOM_SHORTCODES_URL', plugin_dir_url( __FILE__ ) );

add_action( 'wp_enqueue_scripts', 'mavo_custom_shortcodes_enqueue_assets' );
add_shortcode( 'mavo_hub_strip', 'mavo_hub_strip_shortcode' );

/**
 * Enqueue CSS only on singular frontend pages/posts.
 */
function mavo_custom_shortcodes_enqueue_assets() {
    if ( ! is_singular() ) {
        return;
    }

    wp_enqueue_style(
        'mavo-custom-shortcodes',
        MAVO_CUSTOM_SHORTCODES_URL . 'assets/css/mavo-custom-shortcodes.css',
        array(),
        MAVO_CUSTOM_SHORTCODES_VERSION
    );
}

/**
 * Shortcode:
 * [mavo_hub_strip slug="france" text="Retrouvez aussi notre {guide France en famille}."]
 */
function mavo_hub_strip_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'slug' => '',
            'text' => '',
        ),
        $atts,
        'mavo_hub_strip'
    );

    $slug = trim( (string) $atts['slug'] );
    $text = trim( (string) $atts['text'] );

    if ( $slug === '' || $text === '' ) {
        return mavo_hub_strip_admin_error( 'Missing required slug or text attribute.' );
    }

    $parsed = mavo_hub_strip_parse_text( $text );

    if ( is_wp_error( $parsed ) ) {
        return mavo_hub_strip_admin_error( $parsed->get_error_message() );
    }

    $url = mavo_hub_strip_build_url_from_slug( $slug );

    if ( ! $url ) {
        return mavo_hub_strip_admin_error( 'Invalid slug.' );
    }

    $label = mavo_hub_strip_get_default_label();

    $before = $parsed['before'];
    $anchor = $parsed['anchor'];
    $after  = $parsed['after'];

    ob_start();
    ?>
    <aside class="mv-hub-strip" aria-label="<?php echo esc_attr( $label ); ?>">
        <span class="mv-hub-strip__label"><?php echo esc_html( $label ); ?></span>
        <p>
            <?php echo esc_html( $before ); ?><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $anchor ); ?></a><?php echo esc_html( $after ); ?>
        </p>
    </aside>
    <?php
    return trim( ob_get_clean() );
}

/**
 * Parse text containing exactly one {anchor}.
 */
function mavo_hub_strip_parse_text( $text ) {
    $matches = array();

    preg_match_all( '/\{([^{}]+)\}/u', $text, $matches, PREG_OFFSET_CAPTURE );

    if ( count( $matches[0] ) !== 1 ) {
        return new WP_Error(
            'mavo_hub_strip_invalid_anchor',
            'The text attribute must contain exactly one {anchor text} marker.'
        );
    }

    $full_match   = $matches[0][0][0];
    $match_offset = $matches[0][0][1];
    $anchor       = trim( $matches[1][0][0] );

    if ( $anchor === '' ) {
        return new WP_Error(
            'mavo_hub_strip_empty_anchor',
            'The {anchor text} marker cannot be empty.'
        );
    }

    $before = substr( $text, 0, $match_offset );
    $after  = substr( $text, $match_offset + strlen( $full_match ) );

    return array(
        'before' => $before,
        'anchor' => $anchor,
        'after'  => $after,
    );
}

/**
 * Build a local URL from a page/post path.
 *
 * slug="france" => /france/
 * slug="/france/" => /france/
 * slug="en/london-with-kids" => /en/london-with-kids/
 */
function mavo_hub_strip_build_url_from_slug( $slug ) {
    $slug = trim( $slug );

    if ( $slug === '' ) {
        return '';
    }

    // Do not allow full external URLs in this first version.
    if ( preg_match( '#^https?://#i', $slug ) ) {
        $host      = (string) wp_parse_url( home_url(), PHP_URL_HOST );
        $slug_host = (string) wp_parse_url( $slug, PHP_URL_HOST );

        if ( $slug_host === '' ) {
            return '';
        }

        if ( strtolower( preg_replace( '#^www\.#', '', $host ) ) !== strtolower( preg_replace( '#^www\.#', '', $slug_host ) ) ) {
            return '';
        }

        return esc_url_raw( $slug );
    }

    $slug = '/' . trim( $slug, '/' ) . '/';

    return home_url( $slug );
}

/**
 * Default strip label by current language.
 */
function mavo_hub_strip_get_default_label() {
    $lang = '';

    if ( function_exists( 'pll_current_language' ) ) {
        $lang = pll_current_language( 'slug' );
    }

    switch ( $lang ) {
        case 'en':
            return 'Read also';

        case 'de':
            return 'Auch lesenswert';

        case 'fr':
        default:
            return 'À lire aussi';
    }
}

/**
 * Frontend-safe error handling.
 *
 * Show nothing to visitors, but leave a helpful comment for admins.
 */
function mavo_hub_strip_admin_error( $message ) {
    if ( current_user_can( 'edit_posts' ) ) {
        return "\n<!-- mavo_hub_strip error: " . esc_html( $message ) . " -->\n";
    }

    return '';
}
