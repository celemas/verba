<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * Reports translation gaps for a domain without touching any files: per locale,
 * how many source messages are missing from the catalog, present but
 * untranslated, translated, and how many catalog entries are now obsolete.
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

		$locales = [];

		foreach ($this->domain->locales as $locale) {
			$locales[$locale] = $this->inspect($locale, $fresh);
		}

		return new StatusReport($this->domain->name, $locales, $extraction['warnings']);
	}

	/**
	 * @param array<string, Message> $fresh
	 * @return array{
	 *     missing: int,
	 *     untranslated: int,
	 *     translated: int,
	 *     obsolete: int,
	 *     total: int,
	 *     locations: list<string>,
	 * }
	 */
	private function inspect(string $locale, array $fresh): array
	{
		$catalog = CatalogFile::load($this->domain->file($locale));

		$missing = 0;
		$untranslated = 0;
		$translated = 0;
		$locations = [];

		foreach ($fresh as $id => $message) {
			if (!array_key_exists($id, $catalog->messages)) {
				$missing++;
				$locations = [...$locations, ...$message->locations];
			} elseif ($catalog->messages[$id] === null) {
				$untranslated++;
				$locations = [...$locations, ...$message->locations];
			} else {
				$translated++;
			}
		}

		$obsolete = count($catalog->obsolete);

		foreach (array_keys($catalog->messages) as $id) {
			if (array_key_exists($id, $fresh) || array_key_exists($id, $catalog->obsolete)) {
				continue;
			}

			$obsolete++;
		}

		return [
			'missing' => $missing,
			'untranslated' => $untranslated,
			'translated' => $translated,
			'obsolete' => $obsolete,
			'total' => count($fresh),
			'locations' => $locations,
		];
	}
}
