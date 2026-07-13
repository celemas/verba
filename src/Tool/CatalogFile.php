<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * Reads and renders a `<domain>.<locale>.php` catalog for the sync tooling,
 * exposing the raw message, context, and obsolete sections plus the optional
 * plural key. Rendering is deterministic: sections are key-sorted and values
 * are emitted as plain single-quoted literals.
 *
 * @api
 */
final class CatalogFile
{
	/**
	 * @param array<string, string|list<string>|null> $messages
	 * @param array<string, string|list<string>|null> $obsolete
	 * @param array<string, array<string, string|list<string>|null>> $contexts
	 * @param array<string, array<string, string|list<string>|null>> $obsoleteContexts
	 */
	public function __construct(
		public array $messages,
		public array $obsolete,
		public ?string $plural,
		public array $contexts = [],
		public array $obsoleteContexts = [],
	) {}

	public static function load(string $file): self
	{
		if (!is_file($file)) {
			return new self([], [], null);
		}

		/** @var mixed $data */
		$data = require $file;

		if (!is_array($data)) {
			return new self([], [], null);
		}

		return new self(
			self::section($data['messages'] ?? null),
			self::section($data['obsolete'] ?? null),
			self::pluralKey($data['plural'] ?? null),
			self::contexts($data['contexts'] ?? null),
			self::contexts($data['obsolete_contexts'] ?? null),
		);
	}

	private static function pluralKey(mixed $plural): ?string
	{
		return is_string($plural) ? $plural : null;
	}

	public function render(): string
	{
		$lines = ['<?php', '', 'declare(strict_types=1);', '', 'return ['];

		if ($this->plural !== null) {
			$lines[] = "\t'plural' => " . $this->quote($this->plural) . ',';
		}

		$lines[] = "\t'messages' => [";

		foreach ($this->rows($this->messages) as $row) {
			$lines[] = $row;
		}

		$lines[] = "\t],";
		$this->appendContexts($lines, 'contexts', $this->contexts);

		if ($this->obsolete !== []) {
			$lines[] = "\t'obsolete' => [";

			foreach ($this->rows($this->obsolete) as $row) {
				$lines[] = $row;
			}

			$lines[] = "\t],";
		}

		$this->appendContexts($lines, 'obsolete_contexts', $this->obsoleteContexts);
		$lines[] = '];';
		$lines[] = '';

		return implode("\n", $lines);
	}

	/**
	 * @param array<string, string|list<string>|null> $section
	 * @return list<string>
	 */
	private function rows(array $section, int $depth = 2): array
	{
		ksort($section, SORT_STRING);
		$indent = str_repeat("\t", $depth);
		$rows = [];

		foreach ($section as $id => $value) {
			$rows[] = $indent . $this->quote($id) . ' => ' . $this->value($value) . ',';
		}

		return $rows;
	}

	/**
	 * @param list<string> $lines
	 * @param array<string, array<string, string|list<string>|null>> $contexts
	 */
	private function appendContexts(array &$lines, string $name, array $contexts): void
	{
		$contexts = array_filter($contexts, static fn(array $messages): bool => $messages !== []);

		if ($contexts === []) {
			return;
		}

		ksort($contexts, SORT_STRING);
		$lines[] = "\t'{$name}' => [";

		foreach ($contexts as $context => $messages) {
			$lines[] = "\t\t" . $this->quote($context) . ' => [';

			foreach ($this->rows($messages, 3) as $row) {
				$lines[] = $row;
			}

			$lines[] = "\t\t],";
		}

		$lines[] = "\t],";
	}

	/**
	 * @param string|list<string>|null $value
	 */
	private function value(string|array|null $value): string
	{
		if ($value === null) {
			return 'null';
		}

		if (is_string($value)) {
			return $this->quote($value);
		}

		return '[' . implode(', ', array_map($this->quote(...), $value)) . ']';
	}

	private function quote(string $value): string
	{
		return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
	}

	/**
	 * @return array<string, string|list<string>|null>
	 */
	private static function section(mixed $value): array
	{
		if (!is_array($value)) {
			return [];
		}

		/** @var array<string, string|list<string>|null> $value */
		return $value;
	}

	/**
	 * @return array<string, array<string, string|list<string>|null>>
	 */
	private static function contexts(mixed $value): array
	{
		if (!is_array($value)) {
			return [];
		}

		$contexts = [];

		foreach ($value as $context => $messages) {
			if (!is_string($context) || !is_array($messages)) {
				continue;
			}

			$contexts[$context] = self::section($messages);
		}

		return $contexts;
	}
}
