<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * The outcome of a sync: per-locale counts and any scanner warnings.
 *
 * @api
 */
final class SyncReport
{
	/**
	 * @param array<string, array{added: int, obsolete: int, total: int, changed: bool}> $locales
	 * @param list<string> $warnings
	 */
	public function __construct(
		public readonly string $domain,
		public readonly array $locales,
		public readonly array $warnings,
	) {}
}
