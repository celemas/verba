<?php

declare(strict_types=1);

namespace Celema\Verba\Command;

use Celema\Console\Args;
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
#[Opt('--strict', 'Exit non-zero when anything is missing, untranslated, or obsolete')]
#[Opt('--where', 'List the source locations of the gaps')]
final class StatusCommand
{
	/**
	 * @param list<Domain> $domains
	 */
	public function __construct(
		private readonly array $domains,
	) {}

	public function __invoke(Args $args, Io $io): int
	{
		$strict = $args->has('--strict');
		$where = $args->has('--where');
		$clean = true;

		foreach ($this->domains as $domain) {
			$report = new Status($domain)->run();
			$clean = $clean && $report->clean();
			$io->echoln("i18n: {$report->domain}");

			foreach ($report->locales as $locale => $stat) {
				$io->echoln(sprintf(
					'  %s  %d/%d translated, %d missing, %d untranslated, %d obsolete',
					$locale,
					$stat['translated'],
					$stat['total'],
					$stat['missing'],
					$stat['untranslated'],
					$stat['obsolete'],
				));

				if ($where) {
					foreach ($stat['locations'] as $location) {
						$io->echoln('    ' . $location);
					}
				}
			}

			foreach ($report->warnings as $warning) {
				$io->warn('  ' . $warning);
			}
		}

		return $strict && !$clean ? 1 : 0;
	}
}
