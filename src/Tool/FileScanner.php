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
