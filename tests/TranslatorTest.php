<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests;

use Celemas\Verba\Translator;

class TranslatorTest extends TestCase
{
	/**
	 * @return array<string, string>
	 */
	private function cascade(): array
	{
		return ['shop' => $this->i18n(), 'cosray' => $this->i18n()];
	}

	public function testExposesLocale(): void
	{
		$this->assertSame('de', new Translator('de', [])->locale);
	}

	public function testCascadePrefersFirstDomain(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('In den Warenkorb', $t->translate('Add to cart'));
	}

	public function testCascadeFallsThroughToNextDomain(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('Neuer Knoten', $t->translate('node:new'));
	}

	public function testMissReturnsMessageId(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('missing', $t->translate('missing'));
	}

	public function testMissInterpolatesPositional(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('missing x', $t->translate('missing %s', ['x']));
	}

	public function testMissInterpolatesNamed(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('Hi Bob', $t->translate('Hi :n', ['n' => 'Bob']));
	}

	public function testSingularLookupSkipsPluralEntries(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('Found one product', $t->translate('Found one product'));
	}

	public function testMissingCatalogFileFallsBackToId(): void
	{
		$t = new Translator('fr', ['shop' => $this->i18n()]);

		$this->assertSame('Add to cart', $t->translate('Add to cart'));
	}

	public function testTranslateDomainPinsDomain(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('COSRAY Warenkorb', $t->translateDomain('cosray', 'Add to cart'));
	}

	public function testTranslateDomainUnknownDomainReturnsId(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('nope', $t->translateDomain('missing-domain', 'nope'));
	}

	public function testTranslateDomainSkipsPluralEntry(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('Found one product', $t->translateDomain('shop', 'Found one product'));
	}

	public function testPluralPicksSingularForm(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame(
			'Ein Produkt gefunden',
			$t->translatePlural('Found one product', 'Found %d products', 1),
		);
	}

	public function testPluralPicksPluralFormWithPositionalArg(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame(
			'3 Produkte gefunden',
			$t->translatePlural('Found one product', 'Found %d products', 3, [3]),
		);
	}

	public function testPluralInjectsCountForNamedForms(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('5 Artikel', $t->translatePlural(':count item', ':count items', 5));
	}

	public function testPluralKeepsCallerCount(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame(
			'many Artikel',
			$t->translatePlural(':count item', ':count items', 5, ['count' => 'many']),
		);
	}

	public function testPluralUsesStringEntry(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('Einzeln', $t->translatePlural('single plural', 'singles', 2));
	}

	public function testPluralFallbackSingular(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('nomatch', $t->translatePlural('nomatch', 'nomatches', 1));
	}

	public function testPluralFallbackPluralNamed(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('2 things', $t->translatePlural(':count thing', ':count things', 2));
	}

	public function testPluralFallbackPluralPositional(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('5 nomatches', $t->translatePlural('nomatch', '%d nomatches', 5, [5]));
	}

	public function testPluralIndexOutOfRangeUsesLastForm(): void
	{
		$t = new Translator('ru', ['edge' => $this->i18n()]);

		$this->assertSame('dve', $t->translatePlural('twoforms', 'x', 5));
	}

	public function testPluralIndexInRange(): void
	{
		$t = new Translator('ru', ['edge' => $this->i18n()]);

		$this->assertSame('odna', $t->translatePlural('twoforms', 'x', 1));
	}

	public function testPluralEmptyListFallsBackToSingular(): void
	{
		$t = new Translator('ru', ['edge' => $this->i18n()]);

		$this->assertSame('emptyforms', $t->translatePlural('emptyforms', 'x', 5));
	}

	public function testTranslateDomainPluralHit(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame(
			'Ein Produkt gefunden',
			$t->translateDomainPlural('shop', 'Found one product', 'Found %d products', 1),
		);
	}

	public function testTranslateDomainPluralUnknownDomainFallsBack(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame(
			'5 many',
			$t->translateDomainPlural('missing-domain', 'one', '%d many', 5, [5]),
		);
	}

	public function testTranslateDomainPluralMissFallsBack(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame(
			'nomatch',
			$t->translateDomainPlural('shop', 'nomatch', 'nomatches', 1),
		);
	}
}
