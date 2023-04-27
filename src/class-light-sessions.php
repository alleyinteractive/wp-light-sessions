<?php
/**
 * Light_Sessions class
 *
 * @package wp-light-sessions
 */

/* phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized */
/* phpcs:disable WordPress.Security.NonceVerification.Recommended */
namespace Alley\WP\Light_Sessions;

use WP_User;

use function Alley\WP\Light_Sessions\get_current_user as get_ls_user;

/**
 * Main class for the plugin.
 */
class Light_Sessions {
	const NONCE_NAME = 'wpls_convert_session';

	/**
	 * Boot the plugin's functionality.
	 */
	public function boot(): void {
		add_action( 'init', [ $this, 'register_route' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'parse_request', [ $this, 'maybe_intercept_request' ] );
		add_action( 'set_logged_in_cookie', [ $this, 'maybe_intercept_set_logged_in_cookie' ], 10, 4 );
		add_action( 'wp_light_sessions_convert_session', [ $this, 'redirect_to_convert_session' ] );
		add_action( 'wp_light_sessions_request_is_session_safe', [ $this, 'maybe_set_current_user' ] );
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
	 * @param int|null $user_id User ID whose session to set.
	 */
	public function do_convert_session( ?int $user_id = null ): void {
		$app = load();
		$app['auth']->convert_session( $user_id );
	}

	/**
	 * Check if the current request is to convert a session, and if so, run that process.
	 */
	public function maybe_intercept_request( $wp ): void {
		if ( isset( $wp->query_vars['wpls_convert_session'], $wp->query_vars['wpls_nonce'] ) && '1' === $wp->query_vars['wpls_convert_session'] ) {
			// Check nonce.
			if ( false === wp_verify_nonce( $wp->query_vars['wpls_nonce'], self::NONCE_NAME ) ) {
				wp_die( esc_html__( 'The request data timed out or is invalid.', 'wp_light_sessions' ) );
			}

			$this->do_convert_session();
		}
	}

	/**
	 * Create a nonce for converting the session.
	 *
	 * @return string Nonce.
	 */
	public static function get_nonce(): string {
		return wp_create_nonce( self::NONCE_NAME );
	}

	/**
	 * Redirect to the convert session endpoint.
	 *
	 * @param string|null $redirect_to URL to which to redirect.
	 */
	public function redirect_to_convert_session( ?string $redirect_to = null ): void {
		$url   = home_url( "/convert-session/{$this->get_nonce()}/" );
		if ( ! empty( $redirect_to ) ) {
			$url = add_query_arg( 'redirect_to', $redirect_to, $url );
		} elseif ( ! empty( $_REQUEST['redirect_to'] ) && ! str_contains( $_REQUEST['redirect_to'], '/wp-admin/' ) ) {
			$url = add_query_arg( 'redirect_to', $_REQUEST['redirect_to'], $url );
		}

		wp_safe_redirect( esc_url_raw( $url ) );
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
		/**
		 * Filter whether the user's session should be converted to a light session vs core WordPress session.
		 *
		 * If the filter returns true, the session will be immediately converted.
		 *
		 * @param bool $do_convert True if yes, false if no.
		 * @param int  $user_id    User ID.
		 */
		if ( true === apply_filters( 'wp_light_sessions_convert_to_light_session', false, $user_id ) ) {
			$this->do_convert_session( $user_id );
		}
	}

	/**
	 * Set the current global user from the light session user.
	 *
	 * @return void
	 */
	public function maybe_set_current_user(): void {
		// If the current user is already set, no additional work needs to be done.
		if ( is_user_logged_in() ) {
			return;
		}

		$ls_user = get_ls_user();
		if ( $ls_user ) {
			wp_set_current_user( $ls_user->ID );
		}
	}
}
