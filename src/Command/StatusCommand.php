<?php

declare(strict_types=1);

namespace Celema\Verba\Command;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\Status;

/**
 * `i18n:status` — report translation gaps per domain and locale.
 *
 * @api
 */
#[Command('i18n:status', 'Report translation gaps per domain and locale')]
final class StatusCommand
{
	/**
	 * @param list<Domain> $domains
	 */
	public function __construct(
		private readonly array $domains,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Exit non-zero when anything is missing, untranslated, or obsolete')]
		bool $strict = false,
		#[Opt('List the source locations of the gaps and the obsolete ids')]
		bool $where = false,
	): int {
		$clean = true;

		foreach ($this->domains as $domain) {
			$report = new Status($domain)->run();
			$clean = $clean && $report->clean();
			$io->line('i18n: %s', $report->domain);

			foreach ($report->locales as $locale => $stat) {
				$io->line(
					'  %s  %d/%d translated, %d missing, %d untranslated, %d obsolete',
					$locale,
					$stat['translated'],
					$stat['total'],
					$stat['missing'],
					$stat['untranslated'],
					$stat['obsolete'],
				);

				if ($where) {
					foreach ($stat['locations'] as $location) {
						$io->line('    %s', $location);
					}

					// Vanished ids are still in the live section; a sync parks them.
					foreach ($stat['vanished'] as $id) {
						$io->line('    vanished: %s', $id);
					}

					foreach ($stat['parked'] as $id) {
						$io->line('    parked: %s', $id);
					}
				}
			}

			foreach ($report->warnings as $warning) {
				$io->warn('  %s', $warning);
			}
		}

		return $strict && !$clean ? 1 : 0;
	}
}
