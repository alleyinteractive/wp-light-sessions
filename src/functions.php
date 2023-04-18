<?php

namespace Alley\WP\Light_Sessions;

use WP_User;

/**
 * Explicitly set the current request as uncached.
 */
function set_request_as_uncached(): void {
	if ( ! class_exists( '\Alley\WP\Light_Sessions\Cache_Manager' ) ) {
		load();
	}
	Cache_Manager::set_uncached();
}
add_action( 'wp_light_sessions_is_uncached_request', __NAMESPACE__ . '\set_request_as_uncached' );

/**
 * Get the current user, either through normal means or from the light session.
 *
 * @param WP_User|null $user Filtered current user.
 * @return WP_User|null
 */
function get_current_user( $user ): ?WP_User {
	if ( $user ) {
		return $user;
	}

	$app = load();
	return $app['auth']->get_current_user();
}
add_filter( 'wp_light_sessions_get_current_user', __NAMESPACE__ . '\get_current_user' );
