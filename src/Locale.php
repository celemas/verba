<?php

declare(strict_types=1);

namespace Celema\Verba;

/**
 * Locale ids as catalog filename segments.
 *
 * @internal
 */
final class Locale
{
	/**
	 * An id may contain only ASCII letters, digits, hyphens, and underscores,
	 * so it cannot name a path outside the catalog directory.
	 */
	public static function valid(string $id): bool
	{
		return preg_match('/^[A-Za-z0-9_-]+$/D', $id) === 1;
	}
}
