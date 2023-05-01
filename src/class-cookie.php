<?php
/**
 * Cookie class
 *
 * @package wp-light-sessions
 */

namespace Alley\WP\Light_Sessions;

use Alley\WP\Light_Sessions\Exceptions\Invalid_Cookie_Exception;
use Alley\WP\Light_Sessions\Exceptions\Invalid_Token_Exception;
use Alley\WP\Light_Sessions\Exceptions\Invalid_User_Exception;
use WP_User;

/**
 * Class to manage the Light Session cookie.
 *
 * Much of this logic matches 1:1 with WordPress core (see `wp-includes/pluggable.php`).
 */
class Cookie {
	/**
	 * Cookie scheme.
	 *
	 * @var string
	 */
	protected string $scheme = 'logged_in';

	/**
	 * Get the expiration time for the cookie.
	 *
	 * @return int Timestamp.
	 */
	protected function get_expiration(): int {
		/**
		 * Filter the cookie TTL.
		 *
		 * @param int $ttl Cookie TTL in seconds.
		 */
		return time() + (int) apply_filters( 'wp_light_sessions_cookie_expiration', 30 * DAY_IN_SECONDS );
	}

	/**
	 * Generate session data to store in a cookie.
	 *
	 * @param WP_User $user       User for which to create a cookie.
	 * @param int     $expiration Expiration of cookie as unix timestamp.
	 * @return string Cookie value.
	 */
	public function generate( WP_User $user, int $expiration ): string {
		// Generate a password, to backbone the token.
		$new_password = wp_generate_password( 40, false );

		// Generate a token.
		$token = wp_hash_password( $new_password );

		// Take a password fragment to invalidate tokens on password change.
		$pass_frag = substr( $user->user_pass, 8, 4 );

		// Generate a verification key.
		$key = wp_hash( $user->user_login . '|' . $pass_frag . '|' . $expiration . '|' . $token, $this->scheme );

		// If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
		$algo = function_exists( 'hash' ) ? 'sha256' : 'sha1';
		$hash = hash_hmac( $algo, $user->user_login . '|' . $expiration . '|' . $token, $key );

		return $user->user_login . '|' . $expiration . '|' . $token . '|' . $hash;
	}

	/**
	 * Verify session data coming from a cookie.
	 *
	 * @throws Invalid_Cookie_Exception If the cookie is in an invalid format.
	 * @throws Invalid_User_Exception   If the user is invalid (e.g. was deleted).
	 * @throws Invalid_Token_Exception  If the token fails verification.
	 *
	 * @param string $cookie Cookie value to verify.
	 * @return WP_User|null WP_User on success, null on non-fatal error (e.g. expired cookie).
	 */
	public function verify_cookie_value( string $cookie ): ?WP_User {
		if ( empty( $cookie ) ) {
			return null;
		}

		$cookie_elements = wp_parse_auth_cookie( $cookie, $this->scheme );
		if ( ! $cookie_elements ) {
			throw new Invalid_Cookie_Exception();
		}

		$username   = $cookie_elements['username'];
		$hmac       = $cookie_elements['hmac'];
		$token      = $cookie_elements['token'];
		$expiration = $cookie_elements['expiration'];

		// Quick check to see if an honest cookie has expired.
		if ( $expiration < time() ) {
			return null;
		}

		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			throw new Invalid_User_Exception();
		}

		$pass_frag = substr( $user->user_pass, 8, 4 );

		$key = wp_hash( $username . '|' . $pass_frag . '|' . $expiration . '|' . $token, $this->scheme );

		// If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
		$algo = function_exists( 'hash' ) ? 'sha256' : 'sha1';
		$hash = hash_hmac( $algo, $username . '|' . $expiration . '|' . $token, $key );

		if ( ! hash_equals( $hash, $hmac ) ) {
			throw new Invalid_Token_Exception();
		}

		return $user;
	}

	/**
	 * Set a cookie with the user session.
	 *
	 * @param int|null $user_id ID of user whose cookie to set. If absent, uses current user.
	 * @return bool
	 */
	public function set( ?int $user_id = null ): bool {
		if ( $user_id ) {
			$user = get_user_by( 'id', $user_id );
		} else {
			$user = wp_get_current_user();
		}

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		$expiration = $this->get_expiration();
		$value      = $this->generate( $user, $expiration );
		$secure     = $this->is_secure( $user->ID );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie( COOKIE_NAME, $value, $expiration, COOKIEPATH, COOKIE_DOMAIN, $secure, true );

		return true;
	}

	/**
	 * Authenticate the user using the light session cookie.
	 *
	 * @throws Invalid_Cookie_Exception If the cookie is in an invalid format.
	 * @throws Invalid_User_Exception   If the user is invalid (e.g. was deleted).
	 * @throws Invalid_Token_Exception  If the token fails verification.
	 *
	 * @return WP_User|null
	 */
	public function authenticate(): ?WP_User {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		$cookie_value = $_COOKIE[ COOKIE_NAME ] ?? '';

		return $this->verify_cookie_value( $cookie_value );
	}

	/**
	 * Should the cookie be set as secure or not?
	 *
	 * @param int $user_id User ID.
	 * @return bool True if yes, false if no.
	 */
	public static function is_secure( int $user_id ): bool {
		// Determine if the cookie should be secure, following the same logic core does.
		$secure = is_ssl() && 'https' === wp_parse_url( get_option( 'home' ), PHP_URL_SCHEME );

		/**
		 * Filters whether the light session cookie should only be sent over HTTPS.
		 *
		 * @param bool $secure  Whether the cookie should only be sent over HTTPS.
		 * @param int  $user_id User ID.
		 */
		$secure = apply_filters( 'wp_light_sessions_secure_cookie', $secure, $user_id );

		return (bool) $secure;
	}
}
