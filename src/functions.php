<?php
/**
 * Assorted functions for the plugin
 *
 * @package wp-light-sessions
 */

namespace Alley\WP\Light_Sessions;

use WP_User;

/**
 * Explicitly set the current request as not cacheable.
 */
function set_request_as_not_cacheable(): void {
	if ( ! class_exists( '\Alley\WP\Light_Sessions\Cache_Manager' ) ) {
		load();
	}
	Cache_Manager::set_not_cacheable();
}
add_action( 'wp_light_sessions_request_is_not_cacheable', __NAMESPACE__ . '\set_request_as_not_cacheable' );

/**
 * Get the current user, either through normal means or from the light session.
 *
 * @param WP_User|null $user Filtered current user.
 * @return WP_User|null
 */
function get_current_user( ?WP_User $user = null ): ?WP_User {
	if ( $user ) {
		return $user;
	}

	$app = load();
	return $app['auth']->get_current_user();
}
add_filter( 'wp_light_sessions_get_current_user', __NAMESPACE__ . '\get_current_user' );
