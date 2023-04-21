<?php
/**
 * Plugin Name: WP Light Sessions
 * Plugin URI: https://github.com/alleyinteractive/wp-light-sessions
 * Description: Lightweight, cache-flexible user sessions for WordPress
 * Version: 0.1.0
 * Author: Matthew Boynes
 * Author URI: https://alley.com/
 * Requires at least: 6.0
 * Tested up to: 6.2
 *
 * Text Domain: wp-light-sessions
 * Domain Path: /languages/
 *
 * @package wp-light-sessions
 */

namespace Alley\WP\Light_Sessions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the plugin's main files.
require_once __DIR__ . '/src/class-light-sessions.php';
require_once __DIR__ . '/src/functions.php';

/**
 * Instantiate the plugin.
 */
function main(): void {
	// Create the core plugin object.
	$plugin = new Light_Sessions();

	/**
	 * Announce that the plugin has been initialized and share the instance.
	 *
	 * @param Light_Sessions $plugin Plugin class instance.
	 */
	do_action( 'wp_light_sessions_init', $plugin );

	$plugin->boot();
}

/**
 * Load the plugin files in full when necessary and return the instantiated objects.
 *
 * @return array {
 *     Instantiated objects.
 *
 *     @type Auth   $auth   Auth object.
 *     @type Cookie $cookie Cookie object.
 * }
 */
function load(): array {
	static $auth, $caps, $cookie;

	if (
		! $auth instanceof Auth
		|| ! $caps instanceof Capabilities
		|| ! $cookie instanceof Cookie
	) {
		require_once __DIR__ . '/src/class-auth.php';
		require_once __DIR__ . '/src/class-capabilities.php';
		require_once __DIR__ . '/src/class-cookie.php';
		require_once __DIR__ . '/src/class-cache-manager.php';
		require_once __DIR__ . '/src/exceptions/class-light-sessions-exception.php';
		require_once __DIR__ . '/src/exceptions/class-invalid-cookie-exception.php';
		require_once __DIR__ . '/src/exceptions/class-invalid-token-exception.php';
		require_once __DIR__ . '/src/exceptions/class-invalid-user-exception.php';

		$cookie = new Cookie();
		$auth   = new Auth( $cookie );
		$caps   = new Capabilities();
	}

	return [
		'auth'   => $auth,
		'caps'   => $caps,
		'cookie' => $cookie,
	];
}

add_action( 'after_setup_theme', __NAMESPACE__ . '\main' );
