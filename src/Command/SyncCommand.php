<?php

declare(strict_types=1);

namespace Celema\Verba\Command;

use Celema\Console\Command;
use Celema\Console\Io;
use Celema\Console\Opt;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\Sync;

/**
 * `i18n:sync` — extract messages and reconcile every domain's catalog files.
 *
 * @api
 */
#[Command('i18n:sync', 'Extract messages and reconcile catalog files')]
final class SyncCommand
{
	/**
	 * @param list<Domain> $domains
	 */
	public function __construct(
		private readonly array $domains,
	) {}

	public function __invoke(
		Io $io,
		#[Opt('Drop obsolete messages from the catalogs')]
		bool $prune = false,
	): int {
		foreach ($this->domains as $domain) {
			$report = new Sync($domain, $prune)->run();
			$io->line('i18n: %s', $report->domain);

			foreach ($report->locales as $locale => $stat) {
				$io->line(
					'  %s  %d messages, %d added, %d obsolete%s',
					$locale,
					$stat['total'],
					$stat['added'],
					$stat['obsolete'],
					$stat['changed'] ? '' : ' (unchanged)',
				);

				foreach ($stat['vanished'] as $id) {
					$io->line('    %s: %s', $prune ? 'dropped' : 'parked', $id);
				}
			}

			foreach ($report->warnings as $warning) {
				$io->warn('  %s', $warning);
			}
		}

		return 0;
	}
}
