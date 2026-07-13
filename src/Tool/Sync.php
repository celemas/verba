<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

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
		$extraction = new Extractor($this->domain)->extract();
		$fresh = $extraction['messages'];
		$freshContexts = $extraction['contexts'];

		$locales = [];

		foreach ($this->domain->locales as $locale) {
			$locales[$locale] = $this->reconcile($locale, $fresh, $freshContexts);
		}

		return new SyncReport($this->domain->name, $locales, $extraction['warnings']);
	}

	/**
	 * @param array<string, Message> $fresh
	 * @param array<string, array<string, Message>> $freshContexts
	 * @return array{added: int, obsolete: int, total: int, changed: bool}
	 */
	private function reconcile(string $locale, array $fresh, array $freshContexts): array
	{
		$file = $this->domain->file($locale);
		$catalog = CatalogFile::load($file);
		$section = $this->reconcileSection($fresh, $catalog->messages, $catalog->obsolete);
		$messages = $section['messages'];
		$added = $section['added'];
		$contexts = [];

		foreach ($freshContexts as $context => $contextFresh) {
			$section = $this->reconcileSection(
				$contextFresh,
				$catalog->contexts[$context] ?? [],
				$catalog->obsoleteContexts[$context] ?? [],
			);
			$contexts[$context] = $section['messages'];
			$added += $section['added'];
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
		);
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
		];
	}

	/**
	 * @param array<string, Message> $fresh
	 * @param array<string, string|list<string>|null> $messages
	 * @param array<string, string|list<string>|null> $obsolete
	 * @return array{messages: array<string, string|list<string>|null>, added: int}
	 */
	private function reconcileSection(array $fresh, array $messages, array $obsolete): array
	{
		$next = [];
		$added = 0;

		foreach (array_keys($fresh) as $id) {
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
	 * @param array<string, string|list<string>|null> $messages
	 * @param array<string, string|list<string>|null> $parked
	 * @param array<string, Message> $fresh
	 * @return array<string, string|list<string>|null>
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
	 * @param array<string, array<string, Message>> $fresh
	 * @return array<string, array<string, string|list<string>|null>>
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

	/** @param array<string, array<string, string|list<string>|null>> $contexts */
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

			$temp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
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
