<?php

declare(strict_types=1);

namespace Celemas\Verba\Command;

use Celemas\Cli\Args;
use Celemas\Cli\Command;
use Celemas\Verba\Tool\Domain;
use Celemas\Verba\Tool\Status;

/**
 * `i18n:status` — report translation gaps per domain and locale. `--strict`
 * exits non-zero when anything is missing, untranslated, or obsolete;
 * `--where` lists the source locations of the gaps.
 *
 * @api
 */
final class StatusCommand extends Command
{
	protected string $group = 'i18n';
	protected string $prefix = 'i18n';
	protected string $name = 'status';
	protected string $description = 'Report translation gaps per domain and locale';

	/**
	 * @param list<Domain> $domains
	 */
	public function __construct(
		private readonly array $domains,
	) {}

	#[\Override]
	public function run(Args $args): int
	{
		$strict = $args->has('--strict');
		$where = $args->has('--where');
		$clean = true;

		foreach ($this->domains as $domain) {
			$report = new Status($domain)->run();
			$clean = $clean && $report->clean();
			$this->echoln("i18n: {$report->domain}");

			foreach ($report->locales as $locale => $stat) {
				$this->echoln(sprintf(
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
						$this->echoln('    ' . $location);
					}
				}
			}

			foreach ($report->warnings as $warning) {
				$this->warn('  ' . $warning);
			}
		}

		return $strict && !$clean ? 1 : 0;
	}
}
