<?php

namespace Alley\WP\Light_Sessions;

use JetBrains\PhpStorm\NoReturn;

class Light_Sessions {
	public function boot() {
		add_action( 'init', [ $this, 'register_route' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'parse_query', [ $this, 'maybe_intercept_request' ] );
		add_action( 'set_logged_in_cookie', [ $this, 'maybe_intercept_set_logged_in_cookie' ], 10, 4 );
	}

	/**
	 * Register URI route for converting sessions.
	 */
	public function register_route(): void {
		add_rewrite_rule(
			'^convert-session/([^/]+)/?$',
			'index.php?wpls_convert_session=1&wpls_nonce=$matches[1]',
			'top'
		);
	}

	/**
	 * Add the query vars for converting sessions.
	 *
	 * @param array $qv The current query vars.
	 * @return array The modified query vars.
	 */
	public function add_query_var( array $qv ): array {
		$qv[] = 'wpls_convert_session';
		$qv[] = 'wpls_nonce';

		return $qv;
	}


	/**
	 * Lazily load the rest of the plugin and convert the session.
	 *
	 * @param int $user_id User ID whose session to set.
	 */
	public function do_convert_session( int $user_id ): void {
		$app = load();
		$app['auth']->convert_session( $user_id );
	}

	/**
	 * Check if the current request is to convert a session, and if so, run that process.
	 */
	public function maybe_intercept_request(): void {
		if ( '1' === get_query_var( 'wpls_convert_session' ) ) {
			// Check nonce.
			if ( false === wp_verify_nonce( get_query_var( 'wpls_nonce' ), 'wpls_convert_session' ) ) {
				wp_die( __( 'The request data timed out or is invalid.', 'wp_light_sessions' ) );
			}

			$this->do_convert_session();
		}
	}

	/**
	 * Redirect to the convert session endpoint.
	 */
	public function redirect_to_convert_session(): void {
		$nonce = wp_create_nonce( 'wpls_convert_session' );
		$url = home_url( "/convert-session/{$nonce}/" );
		if ( ! empty( $_REQUEST['redirect_to'] ) && ! str_contains( $_REQUEST['redirect_to'], '/wp-admin/' ) ) {
			$url = add_query_arg( 'redirect_to', $_REQUEST['redirect_to'], $url );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * On set_logged_in_cookie, check if this user should get a light session and run that process.
	 *
	 * @param string $logged_in_cookie The logged-in cookie value. Ignored in this method.
	 * @param int    $expire           The time the login grace period expires as a UNIX timestamp.
	 *                                 Default is 12 hours past the cookie's expiration time. Ignored in this method.
	 * @param int    $expiration       The time when the logged-in authentication cookie expires as a UNIX timestamp.
	 *                                 Default is 14 days from now. Ignored in this method.
	 * @param int    $user_id          User ID.
	 */
	public function maybe_intercept_set_logged_in_cookie( $logged_in_cookie, $expire, $expiration, $user_id ) {
		// do_action( 'set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user_id, 'logged_in', $token );
		if ( true === apply_filters( 'wp_light_sessions_auth_as_light_session', false, $user_id ) ) {
			$this->do_convert_session( $user_id );
		}
	}
}
