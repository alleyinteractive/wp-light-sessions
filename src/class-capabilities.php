<?php
/**
 * Capabilities class
 *
 * @package wp-light-sessions
 */

namespace Alley\WP\Light_Sessions;

use WP_User;
use function Alley\WP\Light_Sessions\get_current_user as get_ls_user;

/**
 * Class to manage capabilities handling with Light Sessions.
 */
class Capabilities {
	/**
	 * Initialize the object.
	 */
	public function __construct() {
		add_filter( 'user_has_cap', [ $this, 'filter_user_has_cap' ], 1, 4 );
	}

	/**
	 * Get the capabilities that the plugin allows using.
	 *
	 * @return array WordPress capabilities.
	 */
	public function get_allowed_capabilities(): array {
		/**
		 * Filter the allowlist of capabilities that work with light sessions.
		 *
		 * @param array $capabilities WordPress capabilities.
		 */
		return apply_filters( 'wp_light_sessions_capabilities_allowlist', [ 'use_light_sessions' ] );
	}

	/**
	 * Filter user_has_cap to intercept checks for allowed light session capabilities.
	 *
	 * @param bool[]   $allcaps Array of key/value pairs where keys represent a capability name
	 *                          and boolean values represent whether the user has that capability.
	 * @param string[] $caps    Required primitive capabilities for the requested capability.
	 * @param array    $args {
	 *     Arguments that accompany the requested capability check.
	 *
	 *     @type string    $0 Requested capability.
	 *     @type int       $1 Concerned user ID.
	 *     @type mixed  ...$2 Optional second and further parameters, typically object ID.
	 * }
	 * @param WP_User  $user    The user object.
	 * @return bool[] Filtered capabilities.
	 */
	public function filter_user_has_cap( $allcaps, $caps, $args, $user ): array {
		if ( ! empty( $user->ID ) ) {
			// If the user is already known, nothing additional needs to be done.
			return $allcaps;
		}

		$allowed_caps = $this->get_allowed_capabilities();
		$overlap      = array_intersect( $caps, $allowed_caps );

		// If any of the allowed capabilities were checked, check for light session.
		if ( ! empty( $overlap ) ) {
			$ls_user = get_ls_user();

			// If there is a light session user, update allcaps for allowed capabilities.
			if ( $ls_user instanceof WP_User ) {
				foreach ( $overlap as $cap ) {
					$allcaps[ $cap ] = true;
				}
			}
		}

		return $allcaps;
	}
}
