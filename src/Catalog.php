<?php

declare(strict_types=1);

namespace Celema\Verba;

use Closure;

/**
 * The messages of a single domain and locale, plus its plural rule.
 *
 * @api
 */
final class Catalog
{
	/** @var Closure(int): int */
	private readonly Closure $plural;

	/**
	 * @param array<string, string|list<string>|null> $messages
	 * @param string $pluralKey A locale or rule id resolved through {@see Plurals}.
	 * @param array<string, array<string, string|list<string>|null>> $contexts
	 */
	public function __construct(
		private readonly array $messages,
		private readonly string $pluralKey,
		private readonly array $contexts = [],
	) {
		$this->plural = Plurals::rule($pluralKey);
	}

	/**
	 * Loads `<file>`, falling back to an empty catalog when it is missing or
	 * does not return an array.
	 */
	public static function load(string $file, string $locale): self
	{
		if (!is_file($file)) {
			return new self([], $locale);
		}

		/** @var mixed $data */
		$data = require $file;

		if (!is_array($data)) {
			return new self([], $locale);
		}

		return new self(
			self::readMessages($data['messages'] ?? null),
			self::readPluralKey($data['plural'] ?? null, $locale),
			self::readContexts($data['contexts'] ?? null),
		);
	}

	/**
	 * @return array<string, string|list<string>|null>
	 */
	private static function readMessages(mixed $messages): array
	{
		if (!is_array($messages)) {
			return [];
		}

		/** @var array<string, string|list<string>|null> $messages */
		return $messages;
	}

	/**
	 * @return array<string, array<string, string|list<string>|null>>
	 */
	private static function readContexts(mixed $contexts): array
	{
		if (!is_array($contexts)) {
			return [];
		}

		$read = [];

		foreach ($contexts as $context => $messages) {
			if (!is_string($context) || !is_array($messages)) {
				continue;
			}

			$read[$context] = self::readMessages($messages);
		}

		return $read;
	}

	/**
	 * A catalog may borrow another language's rule via a `plural` key holding a
	 * locale/rule id (e.g. `'plural' => 'ru'`); otherwise its own locale rules.
	 */
	private static function readPluralKey(mixed $plural, string $locale): string
	{
		return is_string($plural) ? $plural : $locale;
	}

	/**
	 * The raw entry for an id: a string, a list of plural forms, or null when
	 * absent or explicitly untranslated.
	 *
	 * @return string|list<string>|null
	 */
	public function get(string $id, ?string $context = null): string|array|null
	{
		$messages = $context === null ? $this->messages : $this->contexts[$context] ?? [];

		return $messages[$id] ?? null;
	}

	/**
	 * Zero-based plural form index for a count.
	 */
	public function form(int $n): int
	{
		return ($this->plural)($n);
	}

	/**
	 * The catalog as canonical, JSON-ready data: the plural rule id plus every
	 * translated message. Untranslated ids (null or an empty form list) are
	 * dropped — a consumer of the payload falls back to the id, mirroring the
	 * runtime.
	 *
	 * @return array{
	 *     plural: string,
	 *     messages: array<string, string|list<string>>,
	 *     contexts?: array<string, array<string, string|list<string>>>,
	 * }
	 */
	public function export(): array
	{
		$export = [
			'plural' => $this->pluralKey,
			'messages' => self::translated($this->messages),
		];
		$contexts = [];

		foreach ($this->contexts as $context => $messages) {
			$messages = self::translated($messages);

			if ($messages !== []) {
				$contexts[$context] = $messages;
			}
		}

		if ($contexts !== []) {
			$export['contexts'] = $contexts;
		}

		return $export;
	}

	/**
	 * @param array<string, string|list<string>|null> $messages
	 * @return array<string, string|list<string>>
	 */
	private static function translated(array $messages): array
	{
		$translated = [];

		foreach ($messages as $id => $message) {
			if ($message === null || $message === []) {
				continue;
			}

			$translated[$id] = $message;
		}

		return $translated;
	}
}
