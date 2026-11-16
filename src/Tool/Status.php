<?php

declare(strict_types=1);

namespace Celema\Verba\Tool;

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
		$files = new CatalogFiles($this->domain);

		$locales = [];

		foreach ($this->domain->locales as $locale) {
			$locales[$locale] = self::inspect($files, $locale, $fresh, $freshContexts);
		}

		return new StatusReport($this->domain->name, $locales, $extraction['warnings']);
	}

	/**
	 * @param array<array-key, Message> $fresh
	 * @param array<array-key, array<array-key, Message>> $freshContexts
	 * @return array{
	 *     missing: int,
	 *     untranslated: int,
	 *     translated: int,
	 *     inherited: int,
	 *     obsolete: int,
	 *     total: int,
	 *     locations: list<string>,
	 *     parked: list<string>,
	 *     vanished: list<string>,
	 * }
	 */
	private static function inspect(CatalogFiles $files, string $locale, array $fresh, array $freshContexts): array
	{
		$parents = $files->chain($locale);
		$catalog = array_shift($parents);
		$summary = self::inspectSection($fresh, $catalog->messages, $parents);
		$stale = self::stale($catalog->messages, $catalog->obsolete, $fresh);

		foreach ($freshContexts as $context => $contextFresh) {
			$contextSummary = self::inspectSection(
				$contextFresh,
				$catalog->contexts[$context] ?? [],
				$parents,
			);
			$summary = self::combine($summary, $contextSummary);
		}

		$contextNames = array_unique([
			...array_keys($catalog->contexts),
			...array_keys($catalog->obsoleteContexts),
		]);

		foreach ($contextNames as $context) {
			$contextStale = self::stale(
				$catalog->contexts[$context] ?? [],
				$catalog->obsoleteContexts[$context] ?? [],
				$freshContexts[$context] ?? [],
				$context,
			);
			$stale['parked'] = [...$stale['parked'], ...$contextStale['parked']];
			$stale['vanished'] = [...$stale['vanished'], ...$contextStale['vanished']];
		}

		return [
			'missing' => $summary['missing'],
			'untranslated' => $summary['untranslated'],
			'translated' => $summary['translated'],
			'inherited' => $summary['inherited'],
			'obsolete' => count($stale['parked']) + count($stale['vanished']),
			'total' => $summary['total'],
			'locations' => $summary['locations'],
			'parked' => $stale['parked'],
			'vanished' => $stale['vanished'],
		];
	}

	/**
	 * @param array<array-key, Message> $fresh
	 * @param array<array-key, string|list<string>|null> $messages
	 * @param array<array-key, CatalogFile> $parents
	 * @return array{
	 *     missing: int,
	 *     untranslated: int,
	 *     translated: int,
	 *     inherited: int,
	 *     total: int,
	 *     locations: list<string>,
	 * }
	 */
	private static function inspectSection(array $fresh, array $messages, array $parents): array
	{
		$missing = 0;
		$untranslated = 0;
		$translated = 0;
		$inherited = 0;
		$locations = [];

		foreach ($fresh as $id => $message) {
			$entry = $messages[$id] ?? null;
			$covered = is_string($entry) || $message->plural !== null && is_array($entry) && $entry !== [];

			if (!$covered && array_any($parents, static fn(CatalogFile $parent): bool => $parent->covers($message))) {
				$inherited++;
			} elseif (!array_key_exists($id, $messages)) {
				$missing++;
				$locations = [...$locations, ...$message->locations];
			} elseif (!$covered) {
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
			'inherited' => $inherited,
			'total' => count($fresh),
			'locations' => $locations,
		];
	}

	/**
	 * @param array{missing: int, untranslated: int, translated: int, inherited: int, total: int, locations: list<string>} $left
	 * @param array{missing: int, untranslated: int, translated: int, inherited: int, total: int, locations: list<string>} $right
	 * @return array{missing: int, untranslated: int, translated: int, inherited: int, total: int, locations: list<string>}
	 */
	private static function combine(array $left, array $right): array
	{
		return [
			'missing' => $left['missing'] + $right['missing'],
			'untranslated' => $left['untranslated'] + $right['untranslated'],
			'translated' => $left['translated'] + $right['translated'],
			'inherited' => $left['inherited'] + $right['inherited'],
			'total' => $left['total'] + $right['total'],
			'locations' => [...$left['locations'], ...$right['locations']],
		];
	}

	/**
	 * The catalog entries the source no longer holds: those a sync already
	 * parked in the obsolete section, and those still in the live section
	 * because no sync ran since they vanished. A contextual id reads
	 * `[context] id`.
	 *
	 * @param array<array-key, string|list<string>|null> $messages
	 * @param array<array-key, string|list<string>|null> $parked
	 * @param array<array-key, Message> $fresh
	 * @return array{parked: list<string>, vanished: list<string>}
	 */
	private static function stale(
		array $messages,
		array $parked,
		array $fresh,
		int|string|null $context = null,
	): array {
		// A numeric id arrives as an integer key.
		$label = static fn(int|string $id): string => $context === null ? (string) $id : "[{$context}] {$id}";
		$vanished = [];

		foreach (array_keys($messages) as $id) {
			if (array_key_exists($id, $fresh) || array_key_exists($id, $parked)) {
				continue;
			}

			$vanished[] = $label($id);
		}

		return ['parked' => array_map($label, array_keys($parked)), 'vanished' => $vanished];
	}
}
