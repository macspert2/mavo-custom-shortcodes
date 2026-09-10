<?php
/**
 * The harness, minus the Mavo Hub Manager stub.
 *
 * function_exists() is what the plugin checks, and a function cannot be
 * undefined once declared, so the "plugin inactive" case needs its own process
 * and its own entry point rather than a flag.
 */

$GLOBALS['MOCK_NO_HUB_MANAGER'] = true;

require_once __DIR__ . '/harness.php';
