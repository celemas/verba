<?php

declare(strict_types=1);

namespace Celema\Verba\Tool;

use Celema\Verba\Inheritance;

/**
 * The catalogs of a domain during one tooling run. Each file is read at most
 * once, so a chain never sees a catalog as it was before the run rewrote it.
 *
 * @internal
 */
final class CatalogFiles
{
	/** @var array<array-key, CatalogFile> */
	private array $catalogs = [];

	public function __construct(
		private readonly Domain $domain,
	) {}

	/** @return non-empty-array<array-key, CatalogFile> */
	public function chain(string $locale): array
	{
		return Inheritance::chain(
			$this->domain->name,
			$this->domain->dir,
			$locale,
			fn(string $id): CatalogFile => $this->catalogs[$id] ??= CatalogFile::load($this->domain->file($id)),
			$this->domain->locales,
		);
	}

	public function replace(string $locale, CatalogFile $catalog): void
	{
		$this->catalogs[$locale] = $catalog;
	}
}
