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

	public function testContextDistinguishesSameMessageId(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('Öffnen', $t->translateContext('menu', 'Open'));
		$this->assertSame('Offen', $t->translateContext('state', 'Open'));
		$this->assertSame('Ohne Kontextname', $t->translateContext('', 'Open'));
	}

	public function testContextMissDoesNotUseUncontextualTranslation(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('Save', $t->translateContext('missing', 'Save'));
		$this->assertSame('Hello Bob', $t->translateContext('missing', 'Hello :name', ['name' => 'Bob']));
	}

	public function testContextUsesDomainCascade(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('Öffnen', $t->translateContext('menu', 'Open'));
		$this->assertSame('COSRAY Öffnen', $t->translateDomainContext('cosray', 'menu', 'Open'));
	}

	public function testDomainContextUnknownOrMissingFallsBack(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame('Open', $t->translateDomainContext('missing', 'menu', 'Open'));
		$this->assertSame('Save', $t->translateDomainContext('shop', 'missing', 'Save'));
	}

	public function testContextPluralUsesExactBucket(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame(
			'3 Kontextprodukte',
			$t->translateContextPlural('inventory', 'Found one product', '%d products', 3, [3]),
		);
		$this->assertSame(
			'Ein Kontextprodukt',
			$t->translateDomainContextPlural(
				'shop',
				'inventory',
				'Found one product',
				'%d products',
				1,
			),
		);
	}

	public function testContextPluralMissesFallBackToCallSiteIds(): void
	{
		$t = new Translator('de', $this->cascade());

		$this->assertSame(
			'2 things',
			$t->translateContextPlural('missing', ':count thing', ':count things', 2),
		);
		$this->assertSame(
			'one',
			$t->translateDomainContextPlural('missing', 'inventory', 'one', 'many', 1),
		);
	}

	public function testFallbackResolvesExactContext(): void
	{
		$t = new Translator('es', ['ctx' => $this->i18n()], ['en']);

		$this->assertSame('Abierto', $t->translateContext('state', 'Open'));
		$this->assertSame('Open menu', $t->translateContext('menu', 'Open'));
	}

	public function testContextFallbackPluralUsesFallbackCatalogRule(): void
	{
		$t = new Translator('es', ['rfb' => $this->i18n()], ['ru']);

		$this->assertSame('21 contextual one', $t->translateContextPlural(
			'inventory',
			'thing',
			'things',
			21,
		));
		$this->assertSame('5 contextual many', $t->translateContextPlural(
			'inventory',
			'thing',
			'things',
			5,
		));
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

	public function testExportIncludesTranslatedContexts(): void
	{
		$t = new Translator('de', ['shop' => $this->i18n()]);
		$export = $t->export('shop');

		$this->assertSame('Öffnen', $export['contexts']['menu']['Open'] ?? null);
		$this->assertArrayNotHasKey('Untranslated', $export['contexts']['menu'] ?? []);
	}

	public function testExportManyKeepsContextOnlyPrimaryAndFallbackEntries(): void
	{
		$t = new Translator('es', ['ctx' => $this->i18n()], ['en']);
		$payload = $t->exportMany(['ctx']);

		$this->assertCount(2, $payload['domains']);
		$this->assertSame([], $payload['domains'][0]['messages']);
		$this->assertSame('Abierto', $payload['domains'][0]['contexts']['state']['Open'] ?? null);
		$this->assertSame([], $payload['domains'][1]['messages']);
		$this->assertSame('Open menu', $payload['domains'][1]['contexts']['menu']['Open'] ?? null);
		$this->assertArrayNotHasKey('state', $payload['domains'][1]['contexts'] ?? []);
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
