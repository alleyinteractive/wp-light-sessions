<?php
/**
 * Assorted functions for the plugin
 *
 * @package wp-light-sessions
 */

namespace Alley\WP\Light_Sessions;

use WP_User;

/**
 * Explicitly tag the current request as safe to use session data.
 */
function set_request_as_safe_to_use_light_sessions(): void {
	if ( ! class_exists( '\Alley\WP\Light_Sessions\Safety_Supervisor' ) ) {
		load();
	}
	Safety_Supervisor::set_not_cacheable();
}
add_action( 'wp_light_sessions_request_is_session_safe', __NAMESPACE__ . '\set_request_as_safe_to_use_light_sessions' );

/**
 * Set the current request as not cacheable. This will send nocache headers so long as headers have not been sent.
 *
 * @return bool True on success, false on failure.
 */
function set_request_as_not_cacheable(): bool {
	if ( headers_sent() ) {
		_doing_it_wrong(
			__FUNCTION__,
			__( 'Headers already sent, cannot set request to be not cacheable', 'wp-light-sessions' ),
			'0.1'
		);
		return false;
	}

	nocache_headers();

	/**
	 * Fire an action indicating that the current request is safe to use session data.
	 */
	do_action( 'wp_light_sessions_request_is_session_safe' );

	return true;
}
add_action( 'wp_light_sessions_set_request_as_not_cacheable', __NAMESPACE__ . '\set_request_as_not_cacheable' );

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
