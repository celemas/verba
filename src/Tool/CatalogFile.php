<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * Reads and renders a `<domain>.<locale>.php` catalog for the sync tooling,
 * exposing the raw message and obsolete sections and the optional plural key.
 * Rendering is deterministic: sections are key-sorted and values are emitted as
 * plain single-quoted literals.
 *
 * @api
 */
final class CatalogFile
{
	/**
	 * @param array<string, string|list<string>|null> $messages
	 * @param array<string, string|list<string>|null> $obsolete
	 */
	public function __construct(
		public array $messages,
		public array $obsolete,
		public ?string $plural,
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

		if ($this->obsolete !== []) {
			$lines[] = "\t'obsolete' => [";

			foreach ($this->rows($this->obsolete) as $row) {
				$lines[] = $row;
			}

			$lines[] = "\t],";
		}

		$lines[] = '];';
		$lines[] = '';

		return implode("\n", $lines);
	}

	/**
	 * @param array<string, string|list<string>|null> $section
	 * @return list<string>
	 */
	private function rows(array $section): array
	{
		ksort($section, SORT_STRING);

		$rows = [];

		foreach ($section as $id => $value) {
			$rows[] = "\t\t" . $this->quote($id) . ' => ' . $this->value($value) . ',';
		}

		return $rows;
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
}
