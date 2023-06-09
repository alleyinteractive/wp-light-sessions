<?php
/**
 * Auth class
 *
 * @package wp-light-sessions
 */

namespace Alley\WP\Light_Sessions;

/* phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized */
/* phpcs:disable WordPress.Security.NonceVerification.Recommended */
/* phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie */

use Alley\WP\Light_Sessions\Exceptions\Light_Sessions_Exception;
use WP_User;

/**
 * Class to manage the Auth session process.
 */
class Auth {

	/**
	 * Instantiate the class with the Cookie object.
	 *
	 * @param Cookie $cookie Cookie object.
	 */
	public function __construct(
		protected Cookie $cookie
	) {
	}

	/**
	 * Convert the current session to a Light Session.
	 *
	 * @param int|null $user_id Optional. ID of user whose session to convert.
	 */
	public function convert_session( ?int $user_id = null ): void {
		$this->cookie->set( $user_id );
		$this->clear_core_cookies();
		$this->redirect();
	}

	/**
	 * Clear WordPress Core cookies.
	 */
	public function clear_core_cookies(): void {
		wp_clear_auth_cookie();

		// Unset test cookie.
		$secure = ( 'https' === wp_parse_url( wp_login_url(), PHP_URL_SCHEME ) );
		setcookie( TEST_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $secure );

		if ( SITECOOKIEPATH !== COOKIEPATH ) {
			setcookie( TEST_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $secure );
		}
	}

	/**
	 * Clear the light session cookie.
	 *
	 * @param int $user_id User ID.
	 */
	public function clear_light_session_cookies( int $user_id ): void {
		$secure = Cookie::is_secure( $user_id );
		setcookie( COOKIE_NAME, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $secure );
		setcookie( JS_COOKIE_NAME, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $secure );
	}

	/**
	 * Redirect the request to the redirect_to request param or the homepage.
	 */
	public function redirect(): void {
		if ( ! empty( $_REQUEST['redirect_to'] ) && ! str_contains( $_REQUEST['redirect_to'], '/wp-admin/' ) ) {
			$redirect_to = $_REQUEST['redirect_to'];
		} else {
			$redirect_to = home_url();
		}

		/**
		 * Filters the redirect URL.
		 *
		 * @param string $redirect_to Redirect URL.
		 */
		$redirect_to = apply_filters( 'wp_light_sessions_auth_redirect', $redirect_to );

		wp_safe_redirect( esc_url_raw( $redirect_to ) );
		exit;
	}

	/**
	 * Get the current user, either through normal means or from the light session.
	 *
	 * @return WP_User|null
	 */
	public function get_current_user(): ?WP_User {
		// First see if the current user is already authenticated.
		if ( is_user_logged_in() ) {
			return wp_get_current_user();
		}

		// Ensure the current request is not cacheable.
		if ( ! Safety_Supervisor::is_not_cacheable() ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The request must be declared as not cacheable before accessing the current user.', 'wp_light_sessions' ),
				'0.1'
			);

			/**
			 * Fires if the user could not be accessed because the request wasn't declared as not cacheable.
			 *
			 * @param Auth $auth This object.
			 */
			do_action( 'wp_light_sessions_caching_error', $this );
			return null;
		}

		try {
			return $this->cookie->authenticate();
		} catch ( Light_Sessions_Exception $e ) {
			/**
			 * Fires if the session failed authentication.
			 *
			 * @param Light_Sessions_Exception $e    Exception.
			 * @param Auth                     $auth This object.
			 */
			do_action( 'wp_light_sessions_authentication_error', $e, $this );
			return null;
		}
	}
}
