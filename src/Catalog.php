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
	/**
	 * @param array<string, string|list<string>|null> $messages
	 * @param Closure(int): int $plural
	 */
	public function __construct(
		private array $messages,
		private Closure $plural,
	) {}

	/**
	 * Loads `<file>`, falling back to an empty catalog when it is missing or
	 * does not return an array.
	 */
	public static function load(string $file, string $locale): self
	{
		if (!is_file($file)) {
			return new self([], Plurals::rule($locale));
		}

		/** @var mixed $data */
		$data = require $file;

		if (!is_array($data)) {
			return new self([], Plurals::rule($locale));
		}

		return new self(
			self::readMessages($data['messages'] ?? null),
			self::readPlural($data['plural'] ?? null, $locale),
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
	 *
	 * @return Closure(int): int
	 */
	private static function readPlural(mixed $plural, string $locale): Closure
	{
		return Plurals::rule(is_string($plural) ? $plural : $locale);
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
}
