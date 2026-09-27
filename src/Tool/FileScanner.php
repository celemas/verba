<?php

declare(strict_types=1);

namespace Celema\Verba\Tool;

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
	 * Argument indexes for each recognized call.
	 *
	 * @var array<string, array{domain: ?int, context: ?int, id: int, plural: ?int}>
	 */
	protected const array CALLS = [
		'__' => ['domain' => null, 'context' => null, 'id' => 0, 'plural' => null],
		'__n' => ['domain' => null, 'context' => null, 'id' => 0, 'plural' => 1],
		'__p' => ['domain' => null, 'context' => 0, 'id' => 1, 'plural' => null],
		'__np' => ['domain' => null, 'context' => 0, 'id' => 1, 'plural' => 2],
		'__d' => ['domain' => 0, 'context' => null, 'id' => 1, 'plural' => null],
		'__dn' => ['domain' => 0, 'context' => null, 'id' => 1, 'plural' => 2],
		'__dp' => ['domain' => 0, 'context' => 1, 'id' => 2, 'plural' => null],
		'__dnp' => ['domain' => 0, 'context' => 1, 'id' => 2, 'plural' => 3],
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
	 * Records a discovered call, or a warning when its id, domain, context, or
	 * plural was not a literal string.
	 *
	 * @param list<?string> $args
	 */
	protected function emit(string $name, array $args, string $location): void
	{
		$call = self::CALLS[$name];
		$id = $args[$call['id']] ?? null;

		if ($id === null) {
			$this->warnings[] = "Non-literal message id in {$name}() at {$location}";

			return;
		}

		$domain = null;

		if ($call['domain'] !== null) {
			$domain = $args[$call['domain']] ?? null;

			if ($domain === null) {
				$this->warnings[] = "Non-literal domain in {$name}() at {$location}";

				return;
			}
		}

		$context = null;

		if ($call['context'] !== null) {
			$context = $args[$call['context']] ?? null;

			if ($context === null) {
				$this->warnings[] = "Non-literal context in {$name}() at {$location}";

				return;
			}
		}

		$plural = null;

		if ($call['plural'] !== null) {
			$plural = $args[$call['plural']] ?? null;

			if ($plural === null) {
				$this->warnings[] = "Non-literal plural in {$name}() at {$location}";

				return;
			}
		}

		$this->messages[] = new Message($domain, $id, $plural, [$location], $context);
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
