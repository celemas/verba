<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * The outcome of a status check: per-locale gaps and any scanner warnings.
 *
 * @api
 */
final class StatusReport
{
	/**
	 * @param array<string, array{
	 *     missing: int,
	 *     untranslated: int,
	 *     translated: int,
	 *     obsolete: int,
	 *     total: int,
	 *     locations: list<string>,
	 * }> $locales
	 * @param list<string> $warnings
	 */
	public function __construct(
		public readonly string $domain,
		public readonly array $locales,
		public readonly array $warnings,
	) {}

	/**
	 * True when every locale is fully translated with nothing missing or stale.
	 */
	public function clean(): bool
	{
		foreach ($this->locales as $stat) {
			if ($stat['missing'] > 0 || $stat['untranslated'] > 0 || $stat['obsolete'] > 0) {
				return false;
			}
		}

		return true;
	}
}
