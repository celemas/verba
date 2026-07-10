<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests;

use Celemas\Verba\Translator;
use InvalidArgumentException;

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

	public function testPluralEmptyListIsUntranslated(): void
	{
		$t = new Translator('ru', ['edge' => $this->i18n()]);

		$this->assertSame('emptyforms', $t->translatePlural('emptyforms', 'many-x', 1));
		$this->assertSame('many-x', $t->translatePlural('emptyforms', 'many-x', 5));
	}

	public function testPluralEmptyListFallsThroughToFallbackLocale(): void
	{
		$t = new Translator('ru', ['edge' => $this->i18n()], ['en']);

		$this->assertSame('empty en', $t->translatePlural('emptyforms', 'x', 5));
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

	public function testFallbackPrefersPrimaryLocale(): void
	{
		$t = new Translator('es', ['fb' => $this->i18n()], ['en', 'de']);

		$this->assertSame('A-es', $t->translate('a'));
	}

	public function testFallbackFillsFromNextLocale(): void
	{
		$t = new Translator('es', ['fb' => $this->i18n()], ['en', 'de']);

		$this->assertSame('B-en', $t->translate('b'));
	}

	public function testFallbackReachesThirdLocale(): void
	{
		$t = new Translator('es', ['fb' => $this->i18n()], ['en', 'de']);

		$this->assertSame('C-de', $t->translate('c'));
	}

	public function testFallbackTreatsExplicitNullAsUntranslated(): void
	{
		$t = new Translator('es', ['fb' => $this->i18n()], ['en', 'de']);

		$this->assertSame('from en', $t->translate('null-key'));
	}

	public function testFallbackExhaustedReturnsId(): void
	{
		$t = new Translator('es', ['fb' => $this->i18n()], ['en', 'de']);

		$this->assertSame('ghost', $t->translate('ghost'));
	}

	public function testDomainCascadeOutranksLocaleFallback(): void
	{
		// `shared` lives in fb.en (fallback locale) and ov.es (primary locale).
		// The domain cascade is the stronger axis, so fb's English wins.
		$t = new Translator('es', ['fb' => $this->i18n(), 'ov' => $this->i18n()], ['en']);

		$this->assertSame('fb-en', $t->translate('shared'));
	}

	public function testFallbackAppliesToPinnedDomain(): void
	{
		$t = new Translator('es', ['fb' => $this->i18n()], ['en']);

		$this->assertSame('B-en', $t->translateDomain('fb', 'b'));
	}

	public function testFallbackResolvesPluralFromNextLocale(): void
	{
		$t = new Translator('es', ['fb' => $this->i18n()], ['en']);

		$this->assertSame('3 things en', $t->translatePlural('thing', 'things', 3, [3]));
	}

	public function testFallbackResolvesPluralInPinnedDomain(): void
	{
		$t = new Translator('es', ['fb' => $this->i18n()], ['en']);

		$this->assertSame('3 things en', $t->translateDomainPlural('fb', 'thing', 'things', 3, [3]));
	}

	public function testFallbackPluralUsesFallbackCatalogRule(): void
	{
		// rfb exists only in ru, whose three-form rule differs from the
		// two-form es default: 21 picks form 0 and 5 picks form 2, while the
		// es rule would pick form 1 for both.
		$t = new Translator('es', ['rfb' => $this->i18n()], ['ru']);

		$this->assertSame('21 thing one', $t->translatePlural('thing', 'things', 21));
		$this->assertSame('5 thing many', $t->translatePlural('thing', 'things', 5));
	}

	public function testFallbackStringBehindPrimaryPluralList(): void
	{
		// 'x' is a plural list in es and fr but a string in en: singular
		// lookups skip the lists and reach the string, plural lookups use the
		// primary list.
		$t = new Translator('es', ['mix' => $this->i18n()], ['fr', 'en']);

		$this->assertSame('X-en', $t->translate('x'));
		$this->assertSame('un', $t->translatePlural('x', 'xs', 1));
	}

	public function testAcceptsFilenameSafeLocaleIds(): void
	{
		$t = new Translator('pt_BR', [], ['zh-Hant']);

		$this->assertSame('pt_BR', $t->locale);
	}

	public function testRejectsUnsafeLocaleId(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Translator('de', [], ['../evil']);
	}

	public function testExportIgnoresFallbackLocales(): void
	{
		$t = new Translator('es', ['fb' => $this->i18n()], ['en', 'de']);

		$this->assertSame(['plural' => 'es', 'messages' => ['a' => 'A-es']], $t->export('fb'));
	}

	public function testExportManyShipsFallbackChain(): void
	{
		$t = new Translator('es', ['fb' => $this->i18n()], ['en', 'de']);
		$payload = $t->exportMany(['fb']);

		$this->assertSame('es', $payload['locale']);
		$this->assertSame(['fb', 'fb', 'fb'], array_column($payload['domains'], 'domain'));
		$this->assertSame(['es', 'en', 'de'], array_column($payload['domains'], 'plural'));
		$this->assertSame(['a' => 'A-es'], $payload['domains'][0]['messages']);
		$this->assertSame(
			[
				'b' => 'B-en',
				'null-key' => 'from en',
				'shared' => 'fb-en',
				'thing' => ['one thing en', '%d things en'],
			],
			$payload['domains'][1]['messages'],
		);
		$this->assertSame(['c' => 'C-de'], $payload['domains'][2]['messages']);
	}

	public function testExportManyKeepsStringBehindPluralList(): void
	{
		// The fr list for 'x' is unreachable behind the es list and is
		// dropped with its whole entry; the en string is still reachable.
		$t = new Translator('es', ['mix' => $this->i18n()], ['fr', 'en']);
		$payload = $t->exportMany(['mix']);

		$this->assertSame(['es', 'en'], array_column($payload['domains'], 'plural'));
		$this->assertSame(['x' => ['un', 'unos']], $payload['domains'][0]['messages']);
		$this->assertSame(['x' => 'X-en'], $payload['domains'][1]['messages']);
	}

	public function testExportManyDropsExhaustedFallbackEntries(): void
	{
		// The en string for 'x' is final, so the es fallback entry is empty
		// and dropped.
		$t = new Translator('en', ['mix' => $this->i18n()], ['es']);
		$payload = $t->exportMany(['mix']);

		$this->assertCount(1, $payload['domains']);
		$this->assertSame(['x' => 'X-en'], $payload['domains'][0]['messages']);
	}

	public function testExportReturnsDomainCatalog(): void
	{
		$t = new Translator('de', ['shop' => $this->i18n()]);
		$export = $t->export('shop');

		$this->assertSame('de', $export['plural']);
		$this->assertSame('In den Warenkorb', $export['messages']['Add to cart']);
		$this->assertArrayNotHasKey('Untranslated', $export['messages']);
	}

	public function testExportUnknownDomainIsEmpty(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame(['plural' => 'de', 'messages' => []], $t->export('missing-domain'));
	}

	public function testExportManyBuildsPayloadInOrder(): void
	{
		$t = new Translator('de', $this->cascade());
		$payload = $t->exportMany(['shop', 'missing-domain']);

		$this->assertSame('de', $payload['locale']);
		$this->assertSame('shop', $payload['domains'][0]['domain']);
		$this->assertSame('In den Warenkorb', $payload['domains'][0]['messages']['Add to cart']);
		$this->assertSame(
			['domain' => 'missing-domain', 'plural' => 'de', 'messages' => []],
			$payload['domains'][1],
		);
	}
}
