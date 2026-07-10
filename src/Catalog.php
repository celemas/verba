<?php

declare(strict_types=1);

namespace Celemas\Verba;

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
	 */
	public function __construct(
		private readonly array $messages,
		private readonly string $pluralKey,
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
	public function get(string $id): string|array|null
	{
		return $this->messages[$id] ?? null;
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
	 * @return array{plural: string, messages: array<string, string|list<string>>}
	 */
	public function export(): array
	{
		$messages = [];

		foreach ($this->messages as $id => $message) {
			if ($message === null || $message === []) {
				continue;
			}

			$messages[$id] = $message;
		}

		return ['plural' => $this->pluralKey, 'messages' => $messages];
	}
}
