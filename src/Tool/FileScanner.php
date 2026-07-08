<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Walks the configured roots and feeds each matching file to the concrete
 * scanner. Files are visited in a stable, sorted order.
 *
 * @api
 */
abstract class FileScanner implements Scanner
{
	/**
	 * Call name => [domain arg index or null, id arg index, plural arg index or null].
	 *
	 * @var array<string, array{?int, int, ?int}>
	 */
	protected const array CALLS = [
		'__' => [null, 0, null],
		'__n' => [null, 0, 1],
		'__d' => [0, 1, null],
		'__dn' => [0, 1, 2],
	];

	/** @var list<Message> */
	protected array $messages = [];

	/** @var list<string> */
	protected array $warnings = [];

	/**
	 * @param list<string> $roots Files or directories to scan.
	 */
	public function __construct(
		protected readonly array $roots,
	) {}

	#[\Override]
	public function scan(): array
	{
		$this->messages = [];
		$this->warnings = [];

		foreach ($this->files() as $file) {
			$this->scanCode((string) file_get_contents($file), $file);
		}

		return $this->messages;
	}

	#[\Override]
	public function warnings(): array
	{
		return $this->warnings;
	}

	/**
	 * File extensions (without dot) this scanner reads.
	 *
	 * @return list<string>
	 */
	abstract protected function extensions(): array;

	abstract protected function scanCode(string $code, string $file): void;

	/**
	 * Records a discovered call, or a warning when its id, domain, or plural was
	 * not a literal string.
	 *
	 * @param list<?string> $args
	 */
	protected function emit(string $name, array $args, string $location): void
	{
		[$domainIndex, $idIndex, $pluralIndex] = self::CALLS[$name];

		$id = $args[$idIndex] ?? null;

		if ($id === null) {
			$this->warnings[] = "Non-literal message id in {$name}() at {$location}";

			return;
		}

		$domain = null;

		if ($domainIndex !== null) {
			$domain = $args[$domainIndex] ?? null;

			if ($domain === null) {
				$this->warnings[] = "Non-literal domain in {$name}() at {$location}";

				return;
			}
		}

		$plural = null;

		if ($pluralIndex !== null) {
			$plural = $args[$pluralIndex] ?? null;

			if ($plural === null) {
				$this->warnings[] = "Non-literal plural in {$name}() at {$location}";

				return;
			}
		}

		$this->messages[] = new Message($domain, $id, $plural, [$location]);
	}

	/**
	 * @return list<string>
	 */
	private function files(): array
	{
		$found = [];

		foreach ($this->roots as $root) {
			if (is_file($root)) {
				$found[] = $root;

				continue;
			}

			if (!is_dir($root)) {
				continue;
			}

			$tree = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
			);

			/** @var mixed $entry */
			foreach ($tree as $entry) {
				if (
					!$entry instanceof SplFileInfo
					|| !$entry->isFile()
					|| !in_array(strtolower($entry->getExtension()), $this->extensions(), true)
				) {
					continue;
				}

				$found[] = $entry->getPathname();
			}
		}

		sort($found);

		return $found;
	}
}
