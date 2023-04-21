<?php
/**
 * Test Bootstrap
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Visit {@see https://mantle.alley.co/testing/test-framework.html} to learn more.
 */
\Mantle\Testing\manager()
	->maybe_rsync_plugin()
	// Load the main file of the plugin.
	->loaded( fn () => require_once __DIR__ . '/../wp-light-sessions.php' )
	->install();
