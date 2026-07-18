<?php

declare(strict_types=1);

namespace Celema\Verba;

use Closure;

/**
 * Plural rules keyed by language, using the classic gettext formulas.
 *
 * Only the exceptions are listed; every other language falls through to the
 * two-form `n !== 1` default. Region subtags are honored where they change the
 * rule (e.g. `pt_BR`). A catalog may override its rule via the `plural` key.
 *
 * @api
 */
final class Plurals
{
	/**
	 * Returns the rule mapping a count to its zero-based plural form index.
	 *
	 * @return Closure(int): int
	 */
	public static function rule(string $locale): Closure
	{
		return self::entry($locale)[1];
	}

	/**
	 * Number of plural forms (nplurals) for the given locale.
	 */
	public static function forms(string $locale): int
	{
		return self::entry($locale)[0];
	}

	/**
	 * @return array{int, Closure(int): int}
	 *
	 * @psalm-suppress UnusedClosureParam One-form languages ignore the count.
	 */
	private static function entry(string $locale): array
	{
		$norm = str_replace('-', '_', strtolower($locale));
		$lang = explode('_', $norm)[0];

		return match (true) {
			$norm === 'pt_br', $lang === 'fr' => [2, static fn(int $n): int => $n > 1 ? 1 : 0],
			in_array($lang, ['ja', 'ko', 'zh', 'vi', 'th', 'id', 'fa'], true) => [
				1,
				static fn(int $n): int => 0,
			],
			in_array($lang, ['ru', 'uk', 'be'], true) => [
				3,
				static fn(int $n): int => match (true) {
					($n % 10) === 1 && ($n % 100) !== 11 => 0,
					($n % 10) >= 2 && ($n % 10) <= 4 && !(($n % 100) >= 12 && ($n % 100) <= 14) => 1,
					default => 2,
				},
			],
			$lang === 'pl' => [
				3,
				static fn(int $n): int => match (true) {
					$n === 1 => 0,
					($n % 10) >= 2 && ($n % 10) <= 4 && !(($n % 100) >= 12 && ($n % 100) <= 14) => 1,
					default => 2,
				},
			],
			in_array($lang, ['cs', 'sk'], true) => [
				3,
				static fn(int $n): int => match (true) {
					$n === 1 => 0,
					$n >= 2 && $n <= 4 => 1,
					default => 2,
				},
			],
			$lang === 'ar' => [
				6,
				static fn(int $n): int => match (true) {
					$n === 0 => 0,
					$n === 1 => 1,
					$n === 2 => 2,
					($n % 100) >= 3 && ($n % 100) <= 10 => 3,
					($n % 100) >= 11 => 4,
					default => 5,
				},
			],
			default => [2, static fn(int $n): int => $n === 1 ? 0 : 1],
		};
	}
}
