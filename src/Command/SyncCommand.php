<?php

declare(strict_types=1);

namespace Celema\Verba\Command;

use Celema\Console\Args;
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
#[Opt('--prune', 'Drop obsolete messages from the catalogs')]
final class SyncCommand
{
	/**
	 * @param list<Domain> $domains
	 */
	public function __construct(
		private readonly array $domains,
	) {}

	public function __invoke(Args $args, Io $io): int
	{
		$prune = $args->has('--prune');

		foreach ($this->domains as $domain) {
			$report = new Sync($domain, $prune)->run();
			$io->echoln("i18n: {$report->domain}");

			foreach ($report->locales as $locale => $stat) {
				$io->echoln(sprintf(
					'  %s  %d messages, %d added, %d obsolete%s',
					$locale,
					$stat['total'],
					$stat['added'],
					$stat['obsolete'],
					$stat['changed'] ? '' : ' (unchanged)',
				));
			}

			foreach ($report->warnings as $warning) {
				$io->warn('  ' . $warning);
			}
		}

		return 0;
	}
}
