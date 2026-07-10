<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests;

use Celemas\Verba\Catalog;

class CatalogTest extends TestCase
{
	public function testMissingFileYieldsEmptyCatalogWithLocaleRule(): void
	{
		$catalog = Catalog::load($this->i18n() . '/shop.fr.php', 'de');

		$this->assertNull($catalog->get('Add to cart'));
		$this->assertSame(0, $catalog->form(1));
		$this->assertSame(1, $catalog->form(2));
	}

	public function testNonArrayFileYieldsEmptyCatalog(): void
	{
		$catalog = Catalog::load($this->i18n() . '/bad.de.php', 'de');

		$this->assertNull($catalog->get('anything'));
	}

	public function testEmptyArrayFileHasNoMessagesAndLocaleRule(): void
	{
		$catalog = Catalog::load($this->i18n() . '/nomsg.de.php', 'ja');

		$this->assertNull($catalog->get('anything'));
		$this->assertSame(0, $catalog->form(5));
	}

	public function testLoadsStringAndListEntries(): void
	{
		$catalog = Catalog::load($this->i18n() . '/shop.de.php', 'de');

		$this->assertSame('In den Warenkorb', $catalog->get('Add to cart'));
		$this->assertSame(
			['Ein Produkt gefunden', '%d Produkte gefunden'],
			$catalog->get('Found one product'),
		);
	}

	public function testExplicitNullEntryReadsAsMiss(): void
	{
		$catalog = Catalog::load($this->i18n() . '/shop.de.php', 'de');

		$this->assertNull($catalog->get('Untranslated'));
	}

	public function testCatalogPluralOverrideWins(): void
	{
		$catalog = Catalog::load($this->i18n() . '/custom.xx.php', 'ru');

		$this->assertSame(0, $catalog->form(5));
		$this->assertSame(['always-A', 'B', 'C'], $catalog->get('k'));
	}

	public function testExportDropsUntranslatedAndUsesLocaleRule(): void
	{
		$catalog = Catalog::load($this->i18n() . '/shop.de.php', 'de');

		$this->assertSame(
			[
				'plural' => 'de',
				'messages' => [
					'Add to cart' => 'In den Warenkorb',
					'Save' => 'Speichern',
					'Found one product' => ['Ein Produkt gefunden', '%d Produkte gefunden'],
					':count item' => [':count Artikel', ':count Artikel'],
					'single plural' => 'Einzeln',
				],
			],
			$catalog->export(),
		);
	}

	public function testExportReportsPluralOverride(): void
	{
		$catalog = Catalog::load($this->i18n() . '/custom.xx.php', 'ru');

		$this->assertSame(
			[
				'plural' => 'ja',
				'messages' => ['k' => ['always-A', 'B', 'C']],
			],
			$catalog->export(),
		);
	}

	public function testExportDropsEmptyPluralLists(): void
	{
		$catalog = Catalog::load($this->i18n() . '/edge.ru.php', 'ru');

		$this->assertSame(['twoforms' => ['odna', 'dve']], $catalog->export()['messages']);
	}

	public function testExportOfMissingFileIsEmpty(): void
	{
		$catalog = Catalog::load($this->i18n() . '/shop.fr.php', 'fr');

		$this->assertSame(['plural' => 'fr', 'messages' => []], $catalog->export());
	}
}
