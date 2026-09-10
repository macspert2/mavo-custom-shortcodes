<?php
/**
 * [mavo_hub_strip] rendering.
 *
 * The interesting part is resolution: a marker points either at the slug
 * attribute or at one of the current post's two primary hubs, and everything
 * that cannot be resolved has to disappear without showing a visitor a broken
 * sentence.
 */

require_once __DIR__ . '/harness.php';

reset_store();

mock_post( 10, 'La France en famille', '/france/' );
mock_post( 11, 'City trips', '/city-trips/' );
mock_post( 12, 'Brouillon', '/brouillon/', 'draft' );
mock_post( 20, 'Article', '/article/' );

mock_current( 20, [ 'geo' => 10, 'theme' => 11 ] );

/* ----------------------------------------------------------- sentence mode */

echo "Sentence mode\n";

is_same(
	'<aside class="mv-hub-strip" aria-label="À lire aussi">'
		. ' <span class="mv-hub-strip__label">À lire aussi</span>'
		. ' <p>Voir <a href="https://www.mamanvoyage.com/france/">la France</a>'
		. ' et <a href="https://www.mamanvoyage.com/city-trips/">nos city trips</a>.</p> </aside>',
	render( [ 'text' => 'Voir {geo:la France} et {theme:nos city trips}.' ] ),
	'one sentence links both hubs, in the order the editor wrote them'
);

contains(
	[ 'text' => 'Voir {theme:nos city trips} puis {geo:la France}.' ],
	'<a href="https://www.mamanvoyage.com/city-trips/">nos city trips</a> puis',
	'the markers are bound by name, not by position'
);

contains(
	[ 'slug' => 'france', 'text' => 'Retrouvez notre {guide France en famille}.' ],
	'<a href="https://www.mamanvoyage.com/france/">guide France en famille</a>',
	'an unnamed marker still points at the slug attribute'
);

contains(
	[ 'slug' => 'en/london-with-kids', 'text' => '{London with kids}' ],
	'href="https://www.mamanvoyage.com/en/london-with-kids/"',
	'a multi-segment slug keeps working'
);

contains(
	[ 'slug' => 'france', 'text' => 'Voir {Paris : la ville}.' ],
	'>Paris : la ville</a>',
	'only geo: and theme: are target prefixes, so a colon in an anchor is safe'
);

contains(
	[ 'text' => '<b>gras</b> {geo:la France}' ],
	'&lt;b&gt;gras&lt;/b&gt;',
	'the surrounding text is escaped'
);

contains(
	[ 'text' => 'Voir {geo:la France}.', 'label' => 'Notre guide' ],
	'<span class="mv-hub-strip__label">Notre guide</span>',
	'the label attribute overrides the default'
);

/* ------------------------------------------------------- unresolved markers */

echo "\nAn unresolvable marker takes the whole strip with it\n";

mock_current( 20, [ 'geo' => 10 ] );

contains(
	[ 'text' => 'Voir {geo:la France} et {theme:nos city trips}.' ],
	'no primary theme hub',
	'a half-linked sentence is suppressed, and the admin comment names the type'
);

lacks(
	[ 'text' => 'Voir {geo:la France} et {theme:nos city trips}.' ],
	'<aside',
	'nothing is rendered'
);

$GLOBALS['MOCK_EDITOR'] = false;
is_same(
	'',
	render( [ 'text' => 'Voir {geo:la France} et {theme:x}.' ] ),
	'a visitor sees nothing at all, not even the comment'
);
$GLOBALS['MOCK_EDITOR'] = true;

contains(
	[ 'text' => 'Voir {geo:la France}.' ],
	'/france/',
	'the hub that does exist still renders on its own'
);

/* --------------------------------------------------------------- list mode */

echo "\nList mode\n";

mock_current( 20, [ 'geo' => 10, 'theme' => 11 ] );

is_same(
	'<aside class="mv-hub-strip" aria-label="À lire aussi">'
		. ' <span class="mv-hub-strip__label">À lire aussi</span>'
		. ' <ul class="mv-hub-strip__links">'
		. '<li><a href="https://www.mamanvoyage.com/france/">La France en famille</a></li>'
		. '<li><a href="https://www.mamanvoyage.com/city-trips/">City trips</a></li>'
		. '</ul> </aside>',
	render( [] ),
	'a bare strip lists both hubs by title, geographic first'
);

is_same( render( [] ), render( [ 'hub' => 'both' ] ), 'hub="both" is the default' );

lacks( [ 'hub' => 'theme' ], '/france/', 'hub="theme" leaves the geographic hub out' );
lacks( [ 'hub' => 'geo' ], '/city-trips/', 'hub="geo" leaves the thematic hub out' );
contains( [ 'hub' => 'GEO' ], '/france/', 'the hub attribute is case-insensitive' );

mock_current( 20, [ 'theme' => 11 ] );
contains( [], 'City trips', 'a missing hub is skipped rather than fatal here' );
lacks( [], '<li><a href="https://www.mamanvoyage.com/france/"', 'and leaves no empty item behind' );

mock_current( 20, [] );
is_same( '', render( [] ), 'a post with neither hub renders no strip' );

mock_current( 20, [ 'geo' => 10, 'theme' => 11 ] );

/* ------------------------------------------------------------ admin errors */

echo "\nAdmin errors\n";

contains(
	[ 'text' => 'Voir {la France}.' ],
	'needs a slug attribute',
	'an unnamed marker with no slug is refused rather than guessed at'
);

contains( [ 'text' => 'Pas de marqueur du tout.' ], 'at least one {anchor text} marker', 'text without a marker is refused' );
contains( [ 'text' => 'Voir {geo: }.' ], 'cannot be empty', 'an empty marker is refused' );
contains( [ 'hub' => 'places' ], 'must be geo, theme or both', 'an unknown hub attribute is refused' );
contains( [ 'slug' => 'france' ], 'needs a text attribute', 'a slug with no text has no anchor to render' );
contains(
	[ 'slug' => 'https://example.com/france/', 'text' => '{France}' ],
	'Invalid slug',
	'an external URL in the slug attribute is still refused'
);
contains(
	[ 'slug' => 'https://www.mamanvoyage.com/france/', 'text' => '{France}' ],
	'href="https://www.mamanvoyage.com/france/"',
	'a full internal URL in the slug attribute is still allowed'
);

/* ----------------------------------------------------------------- guards */

echo "\nGuards\n";

mock_current( 20, [ 'geo' => 12 ] );
contains( [ 'text' => 'Voir {geo:le brouillon}.' ], 'no primary geo hub', 'an unpublished hub is not linked' );
is_same( '', render( [ 'hub' => 'geo' ] ), 'and does not produce a list item either' );

mock_current( 10, [ 'geo' => 10 ] );
is_same( '', render( [ 'hub' => 'geo' ] ), 'a hub page whose meta points at itself does not link to itself' );

$GLOBALS['MOCK_CURRENT'] = 0;
contains( [ 'text' => 'Voir {geo:la France}.' ], 'No post context', 'outside any post the hub cannot be resolved' );

finish();
