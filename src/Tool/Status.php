<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * Reports translation gaps for a domain without touching any files: per locale,
 * how many ordinary and contextual source messages are missing, untranslated,
 * translated, and how many catalog entries are now obsolete.
 *
 * @api
 */
final class Status
{
	public function __construct(
		private readonly Domain $domain,
	) {}

	public function run(): StatusReport
	{
		$extraction = new Extractor($this->domain)->extract();
		$fresh = $extraction['messages'];
		$freshContexts = $extraction['contexts'];

		$locales = [];

		foreach ($this->domain->locales as $locale) {
			$locales[$locale] = $this->inspect($locale, $fresh, $freshContexts);
		}

		return new StatusReport($this->domain->name, $locales, $extraction['warnings']);
	}

	/**
	 * @param array<string, Message> $fresh
	 * @param array<string, array<string, Message>> $freshContexts
	 * @return array{
	 *     missing: int,
	 *     untranslated: int,
	 *     translated: int,
	 *     obsolete: int,
	 *     total: int,
	 *     locations: list<string>,
	 * }
	 */
	private function inspect(string $locale, array $fresh, array $freshContexts): array
	{
		$catalog = CatalogFile::load($this->domain->file($locale));
		$summary = self::inspectSection($fresh, $catalog->messages);
		$obsolete = self::obsolete($catalog->messages, $catalog->obsolete, $fresh);

		foreach ($freshContexts as $context => $contextFresh) {
			$contextSummary = self::inspectSection(
				$contextFresh,
				$catalog->contexts[$context] ?? [],
			);
			$summary = self::combine($summary, $contextSummary);
		}

		$contextNames = array_unique([
			...array_keys($catalog->contexts),
			...array_keys($catalog->obsoleteContexts),
		]);

		foreach ($contextNames as $context) {
			$obsolete += self::obsolete(
				$catalog->contexts[$context] ?? [],
				$catalog->obsoleteContexts[$context] ?? [],
				$freshContexts[$context] ?? [],
			);
		}

		return [
			'missing' => $summary['missing'],
			'untranslated' => $summary['untranslated'],
			'translated' => $summary['translated'],
			'obsolete' => $obsolete,
			'total' => $summary['total'],
			'locations' => $summary['locations'],
		];
	}

	/**
	 * @param array<string, Message> $fresh
	 * @param array<string, string|list<string>|null> $messages
	 * @return array{
	 *     missing: int,
	 *     untranslated: int,
	 *     translated: int,
	 *     total: int,
	 *     locations: list<string>,
	 * }
	 */
	private static function inspectSection(array $fresh, array $messages): array
	{
		$missing = 0;
		$untranslated = 0;
		$translated = 0;
		$locations = [];

		foreach ($fresh as $id => $message) {
			if (!array_key_exists($id, $messages)) {
				$missing++;
				$locations = [...$locations, ...$message->locations];
			} elseif ($messages[$id] === null || $messages[$id] === []) {
				$untranslated++;
				$locations = [...$locations, ...$message->locations];
			} else {
				$translated++;
			}
		}

		return [
			'missing' => $missing,
			'untranslated' => $untranslated,
			'translated' => $translated,
			'total' => count($fresh),
			'locations' => $locations,
		];
	}

	/**
	 * @param array{missing: int, untranslated: int, translated: int, total: int, locations: list<string>} $left
	 * @param array{missing: int, untranslated: int, translated: int, total: int, locations: list<string>} $right
	 * @return array{missing: int, untranslated: int, translated: int, total: int, locations: list<string>}
	 */
	private static function combine(array $left, array $right): array
	{
		return [
			'missing' => $left['missing'] + $right['missing'],
			'untranslated' => $left['untranslated'] + $right['untranslated'],
			'translated' => $left['translated'] + $right['translated'],
			'total' => $left['total'] + $right['total'],
			'locations' => [...$left['locations'], ...$right['locations']],
		];
	}

	/**
	 * @param array<string, string|list<string>|null> $messages
	 * @param array<string, string|list<string>|null> $parked
	 * @param array<string, Message> $fresh
	 */
	private static function obsolete(array $messages, array $parked, array $fresh): int
	{
		$count = count($parked);

		foreach (array_keys($messages) as $id) {
			if (array_key_exists($id, $fresh) || array_key_exists($id, $parked)) {
				continue;
			}

			$count++;
		}

		return $count;
	}
}
