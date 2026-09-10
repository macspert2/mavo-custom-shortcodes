<?php
/**
 * Everything still degrades cleanly when Mavo Hub Manager is not active.
 */

require_once __DIR__ . '/harness-no-hub-manager.php';

reset_store();

mock_post( 10, 'La France en famille', '/france/' );
mock_post( 20, 'Article', '/article/' );
mock_current( 20 );

echo "Without Mavo Hub Manager\n";

contains(
	[ 'text' => 'Voir {geo:la France}.' ],
	'Mavo Hub Manager is not active',
	'a hub marker says why it cannot be resolved'
);

contains( [], 'Mavo Hub Manager is not active', 'a bare strip says the same, rather than fataling or silently vanishing' );

$GLOBALS['MOCK_EDITOR'] = false;
is_same( '', render( [] ), 'and a visitor sees nothing at all' );
$GLOBALS['MOCK_EDITOR'] = true;

contains(
	[ 'slug' => 'france', 'text' => 'Retrouvez notre {guide France}.' ],
	'<a href="https://www.mamanvoyage.com/france/">guide France</a>',
	'a slug strip keeps working, so nothing already published breaks'
);

finish();
