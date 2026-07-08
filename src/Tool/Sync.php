<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * Reconciles a domain's catalog files against the current source: fresh ids are
 * added as untranslated, existing translations are kept, a reappearing id is
 * restored from the obsolete section, and a vanished id is parked there (unless
 * pruning). Rewrites are deterministic, so a second run changes nothing.
 *
 * @api
 */
final class Sync
{
	public function __construct(
		private readonly Domain $domain,
		private readonly bool $prune = false,
	) {}

	public function run(): SyncReport
	{
		$extraction = new Extractor($this->domain)->extract();
		$fresh = $extraction['messages'];

		$locales = [];

		foreach ($this->domain->locales as $locale) {
			$locales[$locale] = $this->reconcile($locale, $fresh);
		}

		return new SyncReport($this->domain->name, $locales, $extraction['warnings']);
	}

	/**
	 * @param array<string, Message> $fresh
	 * @return array{added: int, obsolete: int, total: int, changed: bool}
	 */
	private function reconcile(string $locale, array $fresh): array
	{
		$file = $this->domain->file($locale);
		$catalog = CatalogFile::load($file);

		$messages = [];
		$added = 0;

		foreach (array_keys($fresh) as $id) {
			if (array_key_exists($id, $catalog->messages)) {
				$messages[$id] = $catalog->messages[$id];
			} elseif (array_key_exists($id, $catalog->obsolete)) {
				$messages[$id] = $catalog->obsolete[$id];
			} else {
				$messages[$id] = null;
				$added++;
			}
		}

		$obsolete = $this->prune ? [] : $this->park($catalog, $fresh);

		$next = new CatalogFile($messages, $obsolete, $catalog->plural);
		$rendered = $next->render();
		$changed = !is_file($file) || file_get_contents($file) !== $rendered;

		if ($changed) {
			$this->write($file, $rendered);
		}

		return [
			'added' => $added,
			'obsolete' => count($obsolete),
			'total' => count($messages),
			'changed' => $changed,
		];
	}

	/**
	 * @param array<string, Message> $fresh
	 * @return array<string, string|list<string>|null>
	 */
	private function park(CatalogFile $catalog, array $fresh): array
	{
		$obsolete = [];

		foreach ($catalog->messages as $id => $value) {
			if (array_key_exists($id, $fresh)) {
				continue;
			}

			$obsolete[$id] = $value;
		}

		foreach ($catalog->obsolete as $id => $value) {
			if (array_key_exists($id, $fresh) || array_key_exists($id, $obsolete)) {
				continue;
			}

			$obsolete[$id] = $value;
		}

		return $obsolete;
	}

	private function write(string $file, string $contents): void
	{
		$dir = dirname($file);

		if (!is_dir($dir)) {
			mkdir($dir, 0o755, true);
		}

		file_put_contents($file, $contents);
	}
}
