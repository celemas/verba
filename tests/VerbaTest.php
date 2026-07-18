<?php

declare(strict_types=1);

namespace Celema\Verba\Tests;

use Celema\Verba\Translator;
use Celema\Verba\Verba;

class VerbaTest extends TestCase
{
	private function translator(): Translator
	{
		return new Translator('de', ['shop' => $this->i18n(), 'cosray' => $this->i18n()]);
	}

	public function testNoActiveTranslatorFallsBackToId(): void
	{
		$this->assertNull(Verba::translator());
		$this->assertSame('Add to cart', Verba::translate('Add to cart'));
	}

	public function testFallbackStillInterpolates(): void
	{
		$this->assertSame('Hello Bob', Verba::translate('Hello :n', ['n' => 'Bob']));
	}

	public function testFallbackPluralUsesEnglishRule(): void
	{
		$this->assertSame('2 things', Verba::translatePlural(':count thing', ':count things', 2));
	}

	public function testFallbackContextReturnsId(): void
	{
		$this->assertSame('Hello Bob', Verba::translateContext('menu', 'Hello :name', ['name' => 'Bob']));
		$this->assertSame(
			'2 things',
			Verba::translateContextPlural('menu', ':count thing', ':count things', 2),
		);
	}

	public function testFallbackDomainContextReturnsId(): void
	{
		$this->assertSame('Open', Verba::translateDomainContext('app', 'menu', 'Open'));
		$this->assertSame(
			'2 things',
			Verba::translateDomainContextPlural(
				'app',
				'menu',
				':count thing',
				':count things',
				2,
			),
		);
	}

	public function testActivateExposesTranslator(): void
	{
		$t = $this->translator();
		Verba::activate($t);

		$this->assertSame($t, Verba::translator());
	}

	public function testActivatedTranslate(): void
	{
		Verba::activate($this->translator());

		$this->assertSame('In den Warenkorb', Verba::translate('Add to cart'));
	}

	public function testActivatedPlural(): void
	{
		Verba::activate($this->translator());

		$this->assertSame(
			'3 Produkte gefunden',
			Verba::translatePlural('Found one product', 'Found %d products', 3, [3]),
		);
	}

	public function testActivatedDomain(): void
	{
		Verba::activate($this->translator());

		$this->assertSame('COSRAY Warenkorb', Verba::translateDomain('cosray', 'Add to cart'));
	}

	public function testActivatedDomainPlural(): void
	{
		Verba::activate($this->translator());

		$this->assertSame(
			'Ein Produkt gefunden',
			Verba::translateDomainPlural('shop', 'Found one product', 'x', 1),
		);
	}

	public function testActivatedContextMethods(): void
	{
		Verba::activate($this->translator());

		$this->assertSame('Öffnen', Verba::translateContext('menu', 'Open'));
		$this->assertSame(
			'3 Kontextprodukte',
			Verba::translateContextPlural('inventory', 'Found one product', 'x', 3, [3]),
		);
		$this->assertSame(
			'COSRAY Öffnen',
			Verba::translateDomainContext('cosray', 'menu', 'Open'),
		);
		$this->assertSame(
			'Ein Kontextprodukt',
			Verba::translateDomainContextPlural(
				'shop',
				'inventory',
				'Found one product',
				'x',
				1,
			),
		);
	}

	public function testDeactivateRestoresFallback(): void
	{
		Verba::activate($this->translator());
		Verba::deactivate();

		$this->assertNull(Verba::translator());
		$this->assertSame('Add to cart', Verba::translate('Add to cart'));
	}

	public function testArgsWithSingleArrayIsNamed(): void
	{
		$this->assertSame(['name' => 'Bob'], Verba::args([['name' => 'Bob']]));
	}

	public function testArgsWithSingleScalarIsPositional(): void
	{
		$this->assertSame([5], Verba::args([5]));
	}

	public function testArgsWithMultipleScalarsIsPositional(): void
	{
		$this->assertSame(['a', 'b'], Verba::args(['a', 'b']));
	}

	public function testArgsWithNoneIsEmpty(): void
	{
		$this->assertSame([], Verba::args([]));
	}
}
