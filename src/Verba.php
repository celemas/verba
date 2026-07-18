<?php

declare(strict_types=1);

namespace Celema\Verba;

/**
 * Process-wide holder and facade for the active translator.
 *
 * The eight global translation functions delegate here. With no translator
 * active, lookups return the message id (with interpolation), which
 * keeps translation calls safe in tests, CLI, and early boot.
 *
 * @api
 */
final class Verba
{
	private static ?Translator $translator = null;

	private static ?Translator $fallback = null;

	public static function activate(Translator $translator): void
	{
		self::$translator = $translator;
	}

	public static function deactivate(): void
	{
		self::$translator = null;
	}

	public static function translator(): ?Translator
	{
		return self::$translator;
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public static function translate(string $id, array $args = []): string
	{
		return self::current()->translate($id, $args);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public static function translateContext(string $context, string $id, array $args = []): string
	{
		return self::current()->translateContext($context, $id, $args);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public static function translatePlural(string $one, string $many, int $n, array $args = []): string
	{
		return self::current()->translatePlural($one, $many, $n, $args);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public static function translateContextPlural(
		string $context,
		string $one,
		string $many,
		int $n,
		array $args = [],
	): string {
		return self::current()->translateContextPlural($context, $one, $many, $n, $args);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public static function translateDomain(string $domain, string $id, array $args = []): string
	{
		return self::current()->translateDomain($domain, $id, $args);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public static function translateDomainContext(
		string $domain,
		string $context,
		string $id,
		array $args = [],
	): string {
		return self::current()->translateDomainContext($domain, $context, $id, $args);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public static function translateDomainPlural(
		string $domain,
		string $one,
		string $many,
		int $n,
		array $args = [],
	): string {
		return self::current()->translateDomainPlural($domain, $one, $many, $n, $args);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	// @mago-expect lint:excessive-parameter-list The API mirrors the translator method.
	public static function translateDomainContextPlural(
		string $domain,
		string $context,
		string $one,
		string $many,
		int $n,
		array $args = [],
	): string {
		return self::current()->translateDomainContextPlural($domain, $context, $one, $many, $n, $args);
	}

	/**
	 * Normalizes variadic message arguments into a single args array. A lone
	 * array argument is a named-placeholder map; anything else is a positional
	 * (sprintf) list.
	 *
	 * @param array<array-key, string|int|float|array<array-key, string|int|float>> $args
	 * @return array<array-key, string|int|float>
	 */
	public static function args(array $args): array
	{
		if (count($args) === 1) {
			$first = reset($args);

			if (is_array($first)) {
				return $first;
			}
		}

		/** @var array<array-key, string|int|float> $args */
		return $args;
	}

	private static function current(): Translator
	{
		return self::$translator ?? (self::$fallback ??= new Translator('en', []));
	}
}
