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

		if (!is_string($parent) || preg_match('/^[A-Za-z0-9_-]+$/D', $parent) !== 1) {
			throw new UnexpectedValueException("Invalid extends in '{$file}': expected a locale id");
		}

		return $parent;
	}

	/**
	 * @template T of Catalog|CatalogFile
	 * @param Closure(string, string): T $load
	 * @param list<string> $optional Parent locales that may lack a catalog file.
	 * @return non-empty-list<T>
	 */
	public static function chain(
		string $domain,
		string $dir,
		string $locale,
		Closure $load,
		array $optional = [],
	): array {
		$chain = [];
		$seen = [];

		do {
			$file = $dir . '/' . $domain . '.' . $locale . '.php';

			if (array_key_exists($locale, $seen)) {
				throw new UnexpectedValueException("Catalog inheritance cycle in '{$file}'");
			}

			if ($chain !== [] && !is_file($file) && !in_array($locale, $optional, true)) {
				throw new UnexpectedValueException("Missing parent catalog '{$file}'");
			}

			$seen[$locale] = true;
			$catalog = $load($file, $locale);
			$chain[] = $catalog;
			$locale = $catalog->extends;
		} while ($locale !== null);

		return $chain;
	}
}
