<?php

namespace Alley\WP\Light_Sessions;

class Cache_Manager {
	/**
	 * Is the current request flagged as uncached?
	 *
	 * @var bool
	 */
	protected static bool $is_uncached = false;

	/**
	 * Explicitly set the current request as uncached.
	 */
	public static function set_uncached(): void {
		self::$is_uncached = true;
	}

	/**
	 * Was the current request explicitly flagged as uncached?
	 *
	 * @return bool True if the current request was explicitly flagged as
	 *              uncached, false otherwise.
	 */
	public static function is_uncached(): bool {
		return self::$is_uncached;
	}
}
