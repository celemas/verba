<?php

declare(strict_types=1);

namespace Celema\Verba\Command;

use Celema\Console\Args;
use Celema\Console\Command;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\Sync;

/**
 * `i18n:sync` — extract messages and reconcile every domain's catalog files.
 *
 * @api
 */
final class SyncCommand extends Command
{
	protected string $group = 'i18n';
	protected string $prefix = 'i18n';
	protected string $name = 'sync';
	protected string $description = 'Extract messages and reconcile catalog files';

	/**
	 * @param list<Domain> $domains
	 */
	public function __construct(
		private readonly array $domains,
	) {}

	#[\Override]
	public function run(Args $args): int
	{
		$prune = $args->has('--prune');

		foreach ($this->domains as $domain) {
			$report = new Sync($domain, $prune)->run();
			$this->echoln("i18n: {$report->domain}");

			foreach ($report->locales as $locale => $stat) {
				$this->echoln(sprintf(
					'  %s  %d messages, %d added, %d obsolete%s',
					$locale,
					$stat['total'],
					$stat['added'],
					$stat['obsolete'],
					$stat['changed'] ? '' : ' (unchanged)',
				));
			}

			foreach ($report->warnings as $warning) {
				$this->warn('  ' . $warning);
			}
		}

		return 0;
	}
}
