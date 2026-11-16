<?php

declare(strict_types=1);

namespace Celema\Verba\Tests;

use Celema\Verba\Catalog;
use Celema\Verba\Tool\CatalogFile;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\Status;
use Celema\Verba\Tool\Sync;
use Celema\Verba\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use UnexpectedValueException;

class InheritanceTest extends TestCase
{
	public function testResolvesOverridesAndAncestors(): void
	{
		$t = new Translator('en_US', ['variant' => $this->i18n()]);

		$this->assertSame('Choose a color', $t->translate('Choose a colour'));
		$this->assertSame('Save changes', $t->translate('Save'));
		$this->assertSame('Save changes', $t->translateDomain('variant', 'Save'));
		$this->assertSame('Save menu', $t->translateContext('menu', 'Save'));
		$this->assertSame('Save menu', $t->translateDomainContext('variant', 'menu', 'Save'));
		$this->assertSame('Not found', $t->translateContext('0', '404'));
		$this->assertSame('Save', $t->translateContext('other', 'Save'));
		$this->assertSame('Old', $t->translate('Old'));
		$this->assertSame('missing', $t->translate('missing'));
	}

	public function testKeepsPluralRulesAndWholeMessageOverrides(): void
	{
		$t = new Translator('en_US', ['variant' => $this->i18n()]);

		$this->assertSame('21 base-one', $t->translatePlural('one item', 'many items', 21));
		$this->assertSame('2 base-few', $t->translateDomainPlural('variant', 'one item', 'many items', 2));
		$this->assertSame('5 context-many', $t->translateContextPlural('inventory', 'one item', 'many items', 5));
		$this->assertSame('21 context-one', $t->translateDomainContextPlural(
			'variant',
			'inventory',
			'one item',
			'many items',
			21,
		));
		$this->assertSame('local', $t->translatePlural('Local forms', 'Local forms', 5));
		$this->assertSame('Base singular', $t->translate('Mixed'));
		$this->assertSame('Local many', $t->translatePlural('Mixed', 'Mixed', 5));
	}

	public function testExportManyMatchesBrowserFixture(): void
	{
		$t = new Translator('en_US', ['variant' => $this->i18n()], ['en_GB', 'en']);
		$expected = json_decode(
			file_get_contents(__DIR__ . '/../js/tests/fixtures/inheritance.json'),
			true,
			flags: JSON_THROW_ON_ERROR,
		);

		$this->assertEquals($expected, $t->exportMany(['variant']));
		$this->assertSame(
			Catalog::load($this->i18n() . '/variant.en_US.php', 'en_US')->export(),
			$t->export('variant'),
		);
	}

	public function testParentsPrecedeFallbacksAndLaterDomains(): void
	{
		$this->write('app.en_US.php', "<?php return ['extends' => 'en_GB'];");
		$this->write('app.en_GB.php', "<?php return ['messages' => ['A' => 'parent']];");
		$this->write('app.fr.php', "<?php return ['extends' => 'fr_CA', 'messages' => ['A' => 'fallback']];");
		$this->write('app.fr_CA.php', "<?php return ['messages' => ['B' => 'fallback parent']];");
		$this->write(
			'other.en_US.php',
			"<?php return ['messages' => ['A' => 'other', 'B' => 'other', 'C' => 'other']];",
		);
		$t = new Translator('en_US', ['app' => $this->tmpDir(), 'other' => $this->tmpDir()], ['fr']);

		$this->assertSame('parent', $t->translate('A'));
		$this->assertSame('fallback parent', $t->translate('B'));
		$this->assertSame('other', $t->translate('C'));
		$this->assertSame('B', $t->translateDomain('absent', 'B'));
		$this->assertSame(
			[
				['domain' => 'app', 'plural' => 'en_US', 'messages' => []],
				['domain' => 'app', 'plural' => 'en_GB', 'messages' => ['A' => 'parent']],
				['domain' => 'app', 'plural' => 'fr_CA', 'messages' => ['B' => 'fallback parent']],
			],
			$t->exportMany(['app'])['domains'],
		);
	}

	public function testOverlappingChainsKeepLookupOrder(): void
	{
		$this->write('app.en_US.php', "<?php return ['extends' => 'en'];");
		$this->write('app.en.php', "<?php return ['messages' => ['A' => 'parent']];");
		$this->write(
			'app.en_GB.php',
			"<?php return ['extends' => 'en', 'messages' => ['A' => 'fallback', 'B' => 'fallback']];",
		);
		$t = new Translator('en_US', ['app' => $this->tmpDir()], ['en', 'en_GB']);

		$this->assertSame('parent', $t->translate('A'));
		$this->assertSame('fallback', $t->translate('B'));
		$this->assertSame('C', $t->translate('C'));
		$this->assertSame('parent', $t->translate('A'));
		$this->assertSame(
			[
				['domain' => 'app', 'plural' => 'en_US', 'messages' => []],
				['domain' => 'app', 'plural' => 'en', 'messages' => ['A' => 'parent']],
				['domain' => 'app', 'plural' => 'en_GB', 'messages' => ['B' => 'fallback']],
			],
			$t->exportMany(['app'])['domains'],
		);
	}

	public function testFallbackChainsLoadOnlyWhenReached(): void
	{
		$this->write('app.de.php', "<?php return ['messages' => ['A' => 'primary']];");
		$fallback = $this->write(
			'app.en.php',
			"<?php file_put_contents(__FILE__ . '.reads', '.', FILE_APPEND); return ['extends' => 'en_GB'];",
		);
		$this->write('app.en_GB.php', "<?php return ['messages' => ['B' => 'fallback parent']];");
		$t = new Translator('de', ['app' => $this->tmpDir()], ['en']);

		$this->assertSame('primary', $t->translate('A'));
		$this->assertFileDoesNotExist($fallback . '.reads');
		$this->assertSame('fallback parent', $t->translate('B'));
		$this->assertSame('C', $t->translate('C'));
		$this->assertSame('.', file_get_contents($fallback . '.reads'));
	}

	public function testBrokenFallbackChainFailsOnlyLookupsThatReachIt(): void
	{
		$this->write('app.de.php', "<?php return ['messages' => ['A' => 'primary', 'one' => ['eins', 'viele']]];");
		$this->write('app.en.php', "<?php return ['extends' => 'missing'];");
		$t = new Translator('de', ['app' => $this->tmpDir()], ['en']);

		$this->assertSame('primary', $t->translate('A'));
		$this->assertSame('viele', $t->translatePlural('one', 'many', 2));

		foreach (['B', 'C'] as $missing) {
			try {
				$t->translate($missing);
				$this->fail('Broken fallback chain accepted');
			} catch (UnexpectedValueException $e) {
				$this->assertSame("Missing parent catalog '{$this->tmpDir()}/app.missing.php'", $e->getMessage());
			}
		}

		$this->assertSame('primary', $t->translate('A'));
	}

	public function testFormalGermanUsesGermanPlurals(): void
	{
		$this->write(
			'app.de.php',
			"<?php return ['messages' => ['Enter your name' => 'Gib deinen Namen ein', 'Save' => 'Speichern']];",
		);
		$this->write(
			'app.de-formal.php',
			"<?php return ['extends' => 'de', 'messages' => ['Enter your name' => 'Geben Sie Ihren Namen ein', 'one item' => ['Ihr Artikel', 'Ihre Artikel']]];",
		);
		$t = new Translator('de-formal', ['app' => $this->tmpDir()]);

		$this->assertSame('Geben Sie Ihren Namen ein', $t->translate('Enter your name'));
		$this->assertSame('Speichern', $t->translate('Save'));
		$this->assertSame('Ihr Artikel', $t->translatePlural('one item', 'items', 1));
		$this->assertSame('Ihre Artikel', $t->translatePlural('one item', 'items', 0));
	}

	/** @return iterable<string, array{array<string, array<string, string>>, string, int, string, string}> */
	public static function pluralRules(): iterable
	{
		yield 'parent declares' => [
			['sr-formal' => ['extends' => 'sr'], 'sr' => ['plural' => 'ru']],
			'sr-formal',
			21,
			'form 0',
			'ru',
		];
		yield 'ancestor declares' => [
			['a' => ['extends' => 'b'], 'b' => ['extends' => 'c'], 'c' => ['plural' => 'ru']],
			'a',
			21,
			'form 0',
			'ru',
		];
		yield 'nearest declaration wins' => [
			['a' => ['extends' => 'b'], 'b' => ['extends' => 'c', 'plural' => 'pl'], 'c' => ['plural' => 'ru']],
			'a',
			21,
			'form 2',
			'pl',
		];
		yield 'own declaration wins' => [
			['a' => ['extends' => 'b', 'plural' => 'en'], 'b' => ['plural' => 'ru']],
			'a',
			21,
			'form 1',
			'en',
		];
		yield 'no declaration' => [
			['pt_BR' => ['extends' => 'pt'], 'pt' => []],
			'pt_BR',
			0,
			'form 0',
			'pt_BR',
		];
	}

	/** @param array<string, array<string, string>> $catalogs */
	#[DataProvider('pluralRules')]
	public function testVariantUsesNearestDeclaredPluralRule(
		array $catalogs,
		string $locale,
		int $n,
		string $form,
		string $rule,
	): void {
		foreach ($catalogs as $id => $catalog) {
			$catalog['messages'] = ['item' => ['form 0', 'form 1', 'form 2']];
			$this->write("app.{$id}.php", '<?php return ' . var_export($catalog, true) . ';');
		}

		$t = new Translator($locale, ['app' => $this->tmpDir()]);

		$this->assertSame($form, $t->translatePlural('item', 'items', $n));
		$this->assertSame($rule, $t->export('app')['plural']);
		$this->assertSame($rule, $t->exportMany(['app'])['domains'][0]['plural']);
	}

	/** @return iterable<string, array{string}> */
	public static function invalidParents(): iterable
	{
		yield 'empty' => ["''"];
		yield 'path' => ["'../en'"];
		yield 'newline' => ['"en\n"'];
		yield 'multiple parents' => ["['en', 'de']"];
		yield 'number' => ['42'];
	}

	#[DataProvider('invalidParents')]
	public function testRejectsInvalidParentDeclarations(string $value): void
	{
		$file = $this->write('app.en_US.php', "<?php return ['extends' => {$value}];");

		foreach ([Catalog::load(...), CatalogFile::load(...)] as $load) {
			try {
				$load($file, 'en_US');
				$this->fail('Invalid parent accepted');
			} catch (UnexpectedValueException $e) {
				$this->assertSame("Invalid extends in '{$file}': expected a locale id", $e->getMessage());
			}
		}
	}

	/** @return iterable<string, array{string, string, string}> */
	public static function invalidChains(): iterable
	{
		foreach (['translate', 'export', 'sync', 'status'] as $operation) {
			yield $operation . ' missing' => [$operation, 'missing', "Missing parent catalog '%s/app.en.php'"];
			yield $operation . ' self-cycle' => [$operation, 'self', "Catalog inheritance cycle in '%s/app.en_US.php'"];
			yield $operation . ' indirect cycle' => [
				$operation,
				'cycle',
				"Catalog inheritance cycle in '%s/app.en_US.php'",
			];
		}
	}

	#[DataProvider('invalidChains')]
	public function testRejectsInvalidChains(string $operation, string $kind, string $error): void
	{
		$this->write('app.en_US.php', "<?php return ['extends' => '" . ($kind === 'self' ? 'en_US' : 'en') . "'];");

		if ($kind === 'cycle') {
			$this->write('app.en.php', "<?php return ['extends' => 'en_US'];");
		}

		$t = new Translator('en_US', ['app' => $this->tmpDir()]);
		$domain = new Domain('app', $this->tmpDir(), ['en_US'], []);
		$this->expectException(UnexpectedValueException::class);
		$this->expectExceptionMessage(sprintf($error, $this->tmpDir()));

		match ($operation) {
			'translate' => $t->translate('missing'),
			'export' => $t->exportMany(['app']),
			'sync' => new Sync($domain)->run(),
			'status' => new Status($domain)->run(),
		};
	}
}
