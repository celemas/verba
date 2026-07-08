<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests;

use Celemas\Verba\Translator;
use Celemas\Verba\Verba;

class FunctionsTest extends TestCase
{
	protected function setUp(): void
	{
		Verba::activate(new Translator('de', ['shop' => $this->i18n(), 'cosray' => $this->i18n()]));
	}

	public function testTranslate(): void
	{
		$this->assertSame('In den Warenkorb', __('Add to cart'));
	}

	public function testTranslateNamed(): void
	{
		$this->assertSame('Hello Bob', __('Hello :name', ['name' => 'Bob']));
	}

	public function testTranslatePositional(): void
	{
		$this->assertSame('5 items', __('%s items', '5'));
	}

	public function testTranslatePlainNoArgs(): void
	{
		$this->assertSame('Speichern', __('Save'));
	}

	public function testPluralSingular(): void
	{
		$this->assertSame('Ein Produkt gefunden', __n('Found one product', 'Found %d products', 1));
	}

	public function testPluralWithPositionalCount(): void
	{
		$this->assertSame('3 Produkte gefunden', __n('Found one product', 'Found %d products', 3, 3));
	}

	public function testDomain(): void
	{
		$this->assertSame('COSRAY Warenkorb', __d('cosray', 'Add to cart'));
	}

	public function testDomainPlural(): void
	{
		$this->assertSame('Ein Produkt gefunden', __dn(
			'shop',
			'Found one product',
			'Found %d products',
			1,
		));
	}
}
