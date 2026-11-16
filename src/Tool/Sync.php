<?php

declare(strict_types=1);

namespace Celema\Verba\Tool;

use Closure;
use RuntimeException;

/**
 * Reconciles a domain's catalog files against the current source: fresh ids are
 * added as untranslated, existing translations are kept, a reappearing id is
 * restored from its ordinary or contextual obsolete section, and a vanished id
 * is parked there unless pruning. Rewrites are deterministic.
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
		return $this->prepare()();
	}

	/**
	 * Syncs several domains. Every domain is extracted and its catalogs are
	 * read before the first catalog is written, so an invalid catalog in any
	 * domain leaves all of them untouched.
	 *
	 * @param list<Domain> $domains
	 * @return list<SyncReport>
	 */
	public static function all(array $domains, bool $prune = false): array
	{
		$writes = [];

		foreach ($domains as $domain) {
			$writes[] = new self($domain, $prune)->prepare();
		}

		$reports = [];

		foreach ($writes as $write) {
			$reports[] = $write();
		}

		return $reports;
	}

	/**
	 * Extracts the messages and reads every catalog, then returns the step
	 * that writes the catalogs.
	 *
	 * @return Closure(): SyncReport
	 */
	private function prepare(): Closure
	{
		$extraction = new Extractor($this->domain)->extract();
		$files = new CatalogFiles($this->domain);

		/** @var array<array-key, int> $depths */
		$depths = [];

		foreach ($this->domain->locales as $locale) {
			$depths[$locale] = count($files->chain($locale));
		}

		asort($depths);

		return function () use ($extraction, $files, $depths): SyncReport {
			$results = [];

			foreach (array_keys($depths) as $locale) {
				$results[$locale] = $this->reconcile(
					$files,
					(string) $locale,
					$extraction['messages'],
					$extraction['contexts'],
				);
			}

			$locales = [];

			foreach ($this->domain->locales as $locale) {
				$locales[$locale] = $results[$locale];
			}

			return new SyncReport($this->domain->name, $locales, $extraction['warnings']);
		};
	}

	/**
	 * @param array<array-key, Message> $fresh
	 * @param array<array-key, array<array-key, Message>> $freshContexts
	 * @return array{added: int, obsolete: int, total: int, changed: bool, vanished: list<string>}
	 */
	private function reconcile(CatalogFiles $files, string $locale, array $fresh, array $freshContexts): array
	{
		$file = $this->domain->file($locale);
		$parents = $files->chain($locale);
		$catalog = array_shift($parents);
		$section = $this->reconcileSection($fresh, $catalog->messages, $catalog->obsolete, $parents);
		$messages = $section['messages'];
		$added = $section['added'];
		$contexts = [];

		foreach ($freshContexts as $context => $contextFresh) {
			$section = $this->reconcileSection(
				$contextFresh,
				$catalog->contexts[$context] ?? [],
				$catalog->obsoleteContexts[$context] ?? [],
				$parents,
			);
			$contexts[$context] = $section['messages'];
			$added += $section['added'];
		}

		$vanished = self::vanished($catalog->messages, $fresh);

		foreach ($catalog->contexts as $context => $contextMessages) {
			$vanished = [...$vanished, ...self::vanished($contextMessages, $freshContexts[$context] ?? [], $context)];
		}

		if ($this->prune) {
			$obsolete = [];
			$obsoleteContexts = [];
		} else {
			$obsolete = self::parkSection($catalog->messages, $catalog->obsolete, $fresh);
			$obsoleteContexts = $this->parkContexts($catalog, $freshContexts);
		}

		$next = new CatalogFile(
			$messages,
			$obsolete,
			$catalog->plural,
			$contexts,
			$obsoleteContexts,
			$catalog->extends,
		);
		$files->replace($locale, $next);
		$rendered = $next->render();
		$changed = !is_file($file) || file_get_contents($file) !== $rendered;

		if ($changed) {
			$this->write($file, $rendered);
		}

		return [
			'added' => $added,
			'obsolete' => count($obsolete) + self::contextSize($obsoleteContexts),
			'total' => count($messages) + self::contextSize($contexts),
			'changed' => $changed,
			'vanished' => $vanished,
		];
	}

	/**
	 * The ids of a live section that the source no longer holds, which this
	 * run parks or, when pruning, drops. A contextual id reads `[context] id`.
	 *
	 * @param array<array-key, string|list<string>|null> $messages
	 * @param array<array-key, Message> $fresh
	 * @return list<string>
	 */
	private static function vanished(array $messages, array $fresh, int|string|null $context = null): array
	{
		$ids = [];

		foreach (array_keys($messages) as $id) {
			if (array_key_exists($id, $fresh)) {
				continue;
			}

			$ids[] = $context === null ? (string) $id : "[{$context}] {$id}";
		}

		return $ids;
	}

	/**
	 * @param array<array-key, Message> $fresh
	 * @param array<array-key, string|list<string>|null> $messages
	 * @param array<array-key, string|list<string>|null> $obsolete
	 * @param array<array-key, CatalogFile> $parents
	 * @return array{messages: array<array-key, string|list<string>|null>, added: int}
	 */
	private function reconcileSection(array $fresh, array $messages, array $obsolete, array $parents): array
	{
		$next = [];
		$added = 0;

		foreach ($fresh as $id => $message) {
			$entry = array_key_exists($id, $messages) ? $messages[$id] : $obsolete[$id] ?? null;

			if (
				!$message->coveredBy($entry)
				&& array_any($parents, static fn(CatalogFile $parent): bool => $parent->covers($message))
			) {
				continue;
			}

			if (array_key_exists($id, $messages)) {
				$next[$id] = $messages[$id];
			} elseif (array_key_exists($id, $obsolete)) {
				$next[$id] = $obsolete[$id];
			} else {
				$next[$id] = null;
				$added++;
			}
		}

		return ['messages' => $next, 'added' => $added];
	}

	/**
	 * @param array<array-key, string|list<string>|null> $messages
	 * @param array<array-key, string|list<string>|null> $parked
	 * @param array<array-key, Message> $fresh
	 * @return array<array-key, string|list<string>|null>
	 */
	private static function parkSection(array $messages, array $parked, array $fresh): array
	{
		$obsolete = [];

		foreach ($messages as $id => $value) {
			if (array_key_exists($id, $fresh)) {
				continue;
			}

			$obsolete[$id] = $value;
		}

		foreach ($parked as $id => $value) {
			if (array_key_exists($id, $fresh) || array_key_exists($id, $obsolete)) {
				continue;
			}

			$obsolete[$id] = $value;
		}

		return $obsolete;
	}

	/**
	 * @param array<array-key, array<array-key, Message>> $fresh
	 * @return array<array-key, array<array-key, string|list<string>|null>>
	 */
	private function parkContexts(CatalogFile $catalog, array $fresh): array
	{
		$contexts = [];
		$names = array_unique([
			...array_keys($catalog->contexts),
			...array_keys($catalog->obsoleteContexts),
		]);

		foreach ($names as $context) {
			$messages = self::parkSection(
				$catalog->contexts[$context] ?? [],
				$catalog->obsoleteContexts[$context] ?? [],
				$fresh[$context] ?? [],
			);

			if ($messages !== []) {
				$contexts[$context] = $messages;
			}
		}

		return $contexts;
	}

	/** @param array<array-key, array<array-key, string|list<string>|null>> $contexts */
	private static function contextSize(array $contexts): int
	{
		return array_sum(array_map(count(...), $contexts));
	}

	private function write(string $file, string $contents): void
	{
		$dir = dirname($file);
		$temp = null;
		$error = null;

		// Filesystem functions warn before returning failure; this method checks every result below.
		set_error_handler(static function (int $_severity, string $message) use (&$error): bool {
			$error = $message;

			return true;
		});

		try {
			if (!is_dir($dir)) {
				$error = null;

				if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
					throw self::filesystemError("Cannot create catalog directory '{$dir}'", $error);
				}
			}

			$temp = sprintf('%s.%s.tmp', $file, bin2hex(random_bytes(6)));
			$error = null;

			if (file_put_contents($temp, $contents) !== strlen($contents)) {
				throw self::filesystemError("Cannot write temporary catalog for '{$file}'", $error);
			}

			$error = null;

			if (!rename($temp, $file)) {
				throw self::filesystemError("Cannot replace catalog '{$file}'", $error);
			}
		} finally {
			if ($temp !== null && is_file($temp)) {
				unlink($temp);
			}

			restore_error_handler();
		}
	}

	private static function filesystemError(string $message, ?string $cause): RuntimeException
	{
		return new RuntimeException($cause === null ? $message : $message . ': ' . $cause);
	}
}
