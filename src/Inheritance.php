<?php

declare(strict_types=1);

namespace Celema\Verba;

use Celema\Verba\Tool\CatalogFile;
use Closure;
use UnexpectedValueException;

/** @internal */
final class Inheritance
{
	public static function parent(mixed $parent, string $file): ?string
	{
		if ($parent === null) {
			return null;
		}

		if (!is_string($parent) || !Locale::valid($parent)) {
			throw new UnexpectedValueException("Invalid extends in '{$file}': expected a locale id");
		}

		return $parent;
	}

	/**
	 * @template T of Catalog|CatalogFile
	 * @param Closure(string): T $load Loads the catalog of a locale id.
	 * @param list<string> $optional Parent locales that may lack a catalog file.
	 * @return non-empty-array<array-key, T> Catalogs by locale id, starting with the given locale.
	 */
	public static function chain(
		string $domain,
		string $dir,
		string $locale,
		Closure $load,
		array $optional = [],
	): array {
		$chain = [];

		do {
			$file = Locale::file($dir, $domain, $locale);

			if (array_key_exists($locale, $chain)) {
				throw new UnexpectedValueException("Catalog inheritance cycle in '{$file}'");
			}

			if ($chain !== [] && !is_file($file) && !in_array($locale, $optional, true)) {
				throw new UnexpectedValueException("Missing parent catalog '{$file}'");
			}

			$catalog = $load($locale);
			$chain[$locale] = $catalog;
			$locale = $catalog->extends;
		} while ($locale !== null);

		return $chain;
	}
}
