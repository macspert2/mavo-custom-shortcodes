<?php
/**
 * Plugin Name: Mavo Custom Shortcodes
 * Description: Provides a compact shortcode strip linking posts to their owning hub pages. With place for more shortcodes later.
 * Version: 0.2.0
 * Author: Maman Voyage
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MAVO_CUSTOM_SHORTCODES_VERSION', '0.2.0' );
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
 * Shortcode: [mavo_hub_strip]
 *
 * Sentence mode — text with one or more {anchor} markers:
 *
 *   [mavo_hub_strip text="Voir aussi notre {geo:guide de la France} et nos {theme:city trips}."]
 *   [mavo_hub_strip slug="france" text="Retrouvez aussi notre {guide France en famille}."]
 *
 *   {geo:…}   links to the post's primary geographic hub
 *   {theme:…} links to the post's primary thematic hub
 *   {…}       links to the slug attribute (the original behaviour)
 *
 * List mode — no text, hub titles as links:
 *
 *   [mavo_hub_strip]            both hubs, whichever exist
 *   [mavo_hub_strip hub="geo"]  geographic only
 *
 * Optional label="..." overrides the default language-based label.
 * The hub attribute applies to list mode only; in sentence mode the markers
 * decide what is linked.
 */
function mavo_hub_strip_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'slug'  => '',
            'text'  => '',
            'label' => '',
            'hub'   => '',
        ),
        $atts,
        'mavo_hub_strip'
    );

    $slug  = trim( (string) $atts['slug'] );
    $text  = trim( (string) $atts['text'] );
    $label = trim( (string) $atts['label'] );
    $hub   = strtolower( trim( (string) $atts['hub'] ) );

    if ( $label === '' ) {
        $label = mavo_hub_strip_get_default_label();
    }

    $body = ( $text === '' )
        ? mavo_hub_strip_render_list( $hub, $slug )
        : mavo_hub_strip_render_sentence( $text, $slug );

    if ( is_wp_error( $body ) ) {
        return mavo_hub_strip_admin_error( $body->get_error_message() );
    }

    if ( $body === '' ) {
        return '';
    }

    ob_start();
    ?>
    <aside class="mv-hub-strip" aria-label="<?php echo esc_attr( $label ); ?>">
        <span class="mv-hub-strip__label"><?php echo esc_html( $label ); ?></span>
        <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
    </aside>
    <?php
    return trim( ob_get_clean() );
}

/**
 * Sentence mode: the editor's own wording, with the markers turned into links.
 *
 * Every marker must resolve. A "Voir aussi X et Y" where Y has no hub reads
 * worse than no strip at all, so a single unresolved marker suppresses the
 * whole strip and leaves an admin comment naming what was missing.
 *
 * @return string|WP_Error Inner HTML, '' to render nothing, or an error.
 */
function mavo_hub_strip_render_sentence( $text, $slug ) {
    $parts = mavo_hub_strip_parse_text( $text );

    if ( is_wp_error( $parts ) ) {
        return $parts;
    }

    $html = '';

    foreach ( $parts as $part ) {
        if ( $part['type'] === 'text' ) {
            $html .= esc_html( $part['value'] );
            continue;
        }

        $url = mavo_hub_strip_resolve_target( $part['target'], $slug );

        if ( is_wp_error( $url ) ) {
            return $url;
        }

        // No hub of that type on this post: the sentence would read broken,
        // so the whole strip goes and the editor gets told why.
        if ( $url === '' ) {
            return new WP_Error(
                'mavo_hub_strip_unresolved_marker',
                sprintf(
                    'Post %1$d has no primary %2$s hub, so the {%2$s:...} marker cannot be linked.',
                    mavo_hub_strip_current_post_id(),
                    $part['target']
                )
            );
        }

        $html .= '<a href="' . esc_url( $url ) . '">' . esc_html( $part['anchor'] ) . '</a>';
    }

    return '<p>' . $html . '</p>';
}

/**
 * List mode: no prose, just the hub titles as links.
 *
 * Missing hubs are skipped rather than fatal — nothing was written about them.
 * When neither hub exists the strip disappears.
 *
 * @return string|WP_Error
 */
function mavo_hub_strip_render_list( $hub, $slug ) {
    if ( $slug !== '' ) {
        return new WP_Error(
            'mavo_hub_strip_slug_without_text',
            'The slug attribute needs a text attribute; without text the strip lists the post\'s own hubs.'
        );
    }

    $types = mavo_hub_strip_hub_types( $hub );

    if ( is_wp_error( $types ) ) {
        return $types;
    }

    $items = '';

    foreach ( $types as $type ) {
        $hub_id = mavo_hub_strip_resolve_hub_id( $type );

        if ( is_wp_error( $hub_id ) ) {
            return $hub_id;
        }

        if ( ! $hub_id ) {
            continue;
        }

        $title = trim( (string) get_the_title( $hub_id ) );

        if ( $title === '' ) {
            continue;
        }

        $items .= '<li><a href="' . esc_url( (string) get_permalink( $hub_id ) ) . '">' . esc_html( $title ) . '</a></li>';
    }

    if ( $items === '' ) {
        return '';
    }

    return '<ul class="mv-hub-strip__links">' . $items . '</ul>';
}

/**
 * The hub types a list-mode strip should show.
 *
 * @return array|WP_Error
 */
function mavo_hub_strip_hub_types( $hub ) {
    if ( $hub === '' || $hub === 'both' ) {
        return array( 'geo', 'theme' );
    }

    if ( $hub === 'geo' || $hub === 'theme' ) {
        return array( $hub );
    }

    return new WP_Error(
        'mavo_hub_strip_invalid_hub',
        'The hub attribute must be geo, theme or both.'
    );
}

/**
 * Resolve one marker to a URL.
 *
 * @return string|WP_Error URL, '' when there is no hub of that type, or an error.
 */
function mavo_hub_strip_resolve_target( $target, $slug ) {
    if ( $target === 'slug' ) {
        if ( $slug === '' ) {
            return new WP_Error(
                'mavo_hub_strip_missing_slug',
                'An unnamed {anchor} marker needs a slug attribute. Use {geo:anchor} or {theme:anchor} to link to the post\'s own hub.'
            );
        }

        $url = mavo_hub_strip_build_url_from_slug( $slug );

        if ( ! $url ) {
            return new WP_Error( 'mavo_hub_strip_invalid_slug', 'Invalid slug.' );
        }

        return $url;
    }

    $hub_id = mavo_hub_strip_resolve_hub_id( $target );

    if ( is_wp_error( $hub_id ) ) {
        return $hub_id;
    }

    return $hub_id ? (string) get_permalink( $hub_id ) : '';
}

/**
 * The current post's primary hub of one type, via the Mavo Hub Manager API.
 *
 * @return int|WP_Error Hub post ID, 0 when there is none usable, or an error.
 */
function mavo_hub_strip_resolve_hub_id( $type ) {
    if ( ! function_exists( 'mavo_get_primary_hub' ) ) {
        return new WP_Error(
            'mavo_hub_strip_no_hub_manager',
            'Mavo Hub Manager is not active, so hub markers cannot be resolved.'
        );
    }

    $post_id = mavo_hub_strip_current_post_id();

    if ( ! $post_id ) {
        return new WP_Error(
            'mavo_hub_strip_no_post',
            'No post context, so the hub of the current post cannot be resolved.'
        );
    }

    $hub_id = (int) mavo_get_primary_hub( $post_id, $type );

    if ( ! $hub_id ) {
        return 0;
    }

    // A strip on a hub page must not link to itself.
    if ( $hub_id === $post_id ) {
        return 0;
    }

    if ( get_post_status( $hub_id ) !== 'publish' ) {
        return 0;
    }

    return $hub_id;
}

/**
 * The post the strip is rendering inside, in or out of the loop.
 */
function mavo_hub_strip_current_post_id() {
    $post_id = get_the_ID();

    if ( $post_id ) {
        return (int) $post_id;
    }

    $post = get_post();

    return $post ? (int) $post->ID : 0;
}

/**
 * Parse text containing one or more {anchor} markers into a render list.
 *
 * A marker may name its target: {geo:...} and {theme:...} point at the post's
 * own hubs, anything else is an anchor for the slug attribute. Only those two
 * literal prefixes are special, so an anchor like {Paris : la ville} is left
 * alone.
 *
 * @return array|WP_Error List of [ 'type' => 'text'|'link', ... ] parts.
 */
function mavo_hub_strip_parse_text( $text ) {
    $matches = array();

    if ( ! preg_match_all( '/\{([^{}]+)\}/u', $text, $matches, PREG_OFFSET_CAPTURE ) ) {
        return new WP_Error(
            'mavo_hub_strip_invalid_anchor',
            'The text attribute must contain at least one {anchor text} marker.'
        );
    }

    $parts  = array();
    $cursor = 0;

    foreach ( $matches[0] as $index => $match ) {
        $full_match   = $match[0];
        $match_offset = $match[1];
        $inner        = $matches[1][ $index ][0];

        $target = 'slug';
        $anchor = trim( $inner );

        $named = array();
        if ( preg_match( '/^(geo|theme)\s*:\s*(.*)$/us', $inner, $named ) ) {
            $target = strtolower( $named[1] );
            $anchor = trim( $named[2] );
        }

        if ( $anchor === '' ) {
            return new WP_Error(
                'mavo_hub_strip_empty_anchor',
                'The {anchor text} marker cannot be empty.'
            );
        }

        $parts[] = array(
            'type'  => 'text',
            'value' => substr( $text, $cursor, $match_offset - $cursor ),
        );

        $parts[] = array(
            'type'   => 'link',
            'target' => $target,
            'anchor' => $anchor,
        );

        $cursor = $match_offset + strlen( $full_match );
    }

    $parts[] = array(
        'type'  => 'text',
        'value' => substr( $text, $cursor ),
    );

    return $parts;
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
