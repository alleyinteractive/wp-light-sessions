<?php
/**
 * Auth class
 */

namespace Alley\WP\Light_Sessions;

use Alley\WP\Light_Sessions\Exceptions\Light_Sessions_Exception;
use WP_User;

/**
 * Class to manage the Auth session process.
 */
class Auth {
	/**
	 * Cookie object.
	 *
	 * @var Cookie
	 */
	protected Cookie $cookie;

	/**
	 * Instantiate the class with the Cookie object.
	 *
	 * @param Cookie $cookie Cookie object.
	 */
	public function __construct( Cookie $cookie ) {
		$this->cookie = $cookie;
	}

	/**
	 * Convert the current session to a Light Session.
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
		$secure = ( 'https' === parse_url( wp_login_url(), PHP_URL_SCHEME ) );
		setcookie( TEST_COOKIE, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, $secure );

		if ( SITECOOKIEPATH !== COOKIEPATH ) {
			setcookie( TEST_COOKIE, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, $secure );
		}
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

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Get the current user, either through normal means or from the light session.
	 *
	 * @return WP_User|null
	 */
	public function get_current_user() {
		// First see if core has cookies for the current user.
		$user = wp_get_current_user();
		if ( $user instanceof WP_User && ! empty( $user->ID ) ) {
			return $user;
		}

		// Ensure the current request is uncached.
		if ( ! Cache_Manager::is_uncached() ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'The request must be declared as uncached before accessing the current user.', 'wp_light_sessions' ),
				'0.1'
			);

			/**
			 * Fires if the user could not be accessed because the request wasn't declared as uncached.
			 *
			 * @param Auth $auth This object.
			 */
			do_action( 'wp_light_sessions_authentication_error', $this );
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
