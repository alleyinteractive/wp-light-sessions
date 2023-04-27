<?php
/**
 * Cache_Manager class
 *
 * @package wp-light-sessions
 */

namespace Alley\WP\Light_Sessions;

/**
 * Cache state manager for the plugin.
 */
class Safety_Supervisor {
	/**
	 * Is the current request flagged as not cacheable?
	 *
	 * @var bool
	 */
	protected static bool $is_not_cacheable = false;

	/**
	 * Explicitly set the current request as not cacheable.
	 */
	public static function set_not_cacheable(): void {
		self::$is_not_cacheable = true;
	}

	/**
	 * Was the current request explicitly flagged as not cacheable?
	 *
	 * @return bool True if the current request was explicitly flagged as
	 *              not cacheable, false otherwise.
	 */
	public static function is_not_cacheable(): bool {
		return self::$is_not_cacheable;
	}
}
