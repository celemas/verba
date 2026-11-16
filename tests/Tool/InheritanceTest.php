<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Tool;

use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\CatalogFile;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\PhpScanner;
use Celema\Verba\Tool\Status;
use Celema\Verba\Tool\Sync;
use UnexpectedValueException;

class InheritanceTest extends TestCase
{
	/** @param list<string> $locales */
	private function domain(array $locales = ['en_US']): Domain
	{
		return new Domain(
			'app',
			$this->tmpDir() . '/i18n',
			$locales,
			[new PhpScanner([$this->tmpDir() . '/src'])],
			default: true,
		);
	}

	private function catalog(string $locale, CatalogFile $catalog): string
	{
		return $this->write('i18n/app.' . $locale . '.php', $catalog->render());
	}

	public function testSyncKeepsVariantSparseAndPreservesOverrides(): void
	{
		$this->write('src/x.php', "<?php
__('Inherited');
__('Placeholder');
__n('Empty', 'Empties', 2);
__('Same');
__('Restored');
__('Missing');
__('EmptyParent');
__('LocalEmpty');
__('Plain');
__p('menu', 'Open');
__p('state', 'Open');
__p('0', '404');
");
		$base = $this->catalog('en_GB', new CatalogFile(
			[
				'Inherited' => 'base',
				'Placeholder' => 'base',
				'Empty' => ['one', 'many'],
				'Same' => 'same',
				'Restored' => 'base',
				'EmptyParent' => null,
				'Plain' => ['plural only'],
			],
			[],
			null,
			['menu' => ['Open' => 'Open menu'], '0' => ['404' => 'Not found']],
		));
		$baseContents = file_get_contents($base);
		$file = $this->catalog(
			'en_US',
			new CatalogFile(
				[
					'Placeholder' => null,
					'Empty' => [],
					'Same' => 'same',
					'LocalEmpty' => [],
					'Gone' => 'gone',
				],
				['Restored' => 'local'],
				'en',
				['menu' => ['Open' => null]],
				extends: 'en_GB',
			),
		);

		$report = new Sync($this->domain())->run();
		$catalog = CatalogFile::load($file);

		$this->assertSame('en_GB', $catalog->extends);
		$this->assertSame('en', $catalog->plural);
		$this->assertSame(
			[
				'EmptyParent' => null,
				'LocalEmpty' => [],
				'Missing' => null,
				'Plain' => null,
				'Restored' => 'local',
				'Same' => 'same',
			],
			$catalog->messages,
		);
		$this->assertSame(['state' => ['Open' => null]], $catalog->contexts);
		$this->assertSame(['Gone' => 'gone'], $catalog->obsolete);
		$this->assertSame(4, $report->locales['en_US']['added']);
		$this->assertSame(7, $report->locales['en_US']['total']);
		$this->assertSame($baseContents, file_get_contents($base));
		$this->assertFalse(new Sync($this->domain())->run()->locales['en_US']['changed']);
		new Sync($this->domain(), prune: true)->run();
		$this->assertSame([], CatalogFile::load($file)->obsolete);
		$this->assertSame('en_GB', CatalogFile::load($file)->extends);
	}

	public function testSyncRestoresParentsBeforeChildrenRegardlessOfLocaleOrder(): void
	{
		$this->write('src/x.php', "<?php __('Back'); __p('menu', 'Back');");
		$this->catalog('en', new CatalogFile([], ['Back' => 'Restored'], null, obsoleteContexts: ['menu' => [
			'Back' => 'Menu',
		]]));
		$this->catalog('en_GB', new CatalogFile([], [], null, extends: 'en'));
		$file = $this->catalog('en_US', new CatalogFile(['Back' => null], [], null, extends: 'en_GB'));
		$domain = $this->domain(['en_US', 'en_GB', 'en']);

		$report = new Sync($domain)->run();

		$this->assertSame(['en_US', 'en_GB', 'en'], array_keys($report->locales));
		$this->assertSame([], CatalogFile::load($file)->messages);
		$this->assertSame([], CatalogFile::load($file)->contexts);
		$this->assertSame(0, $report->locales['en_US']['added']);
		$this->assertSame(2, new Status($domain)->run()->locales['en_US']['inherited']);
		$this->assertTrue(new Status($domain)->run()->clean());

		foreach (new Sync($domain)->run()->locales as $locale) {
			$this->assertFalse($locale['changed']);
		}
	}

	public function testSyncReadsEachCatalogOnce(): void
	{
		$this->write('src/x.php', "<?php __('Save');");
		$base = $this->write(
			'i18n/app.en.php',
			"<?php file_put_contents(__FILE__ . '.reads', '.', FILE_APPEND); return ['messages' => ['Save' => 'Saved']];",
		);
		$this->catalog('en_GB', new CatalogFile(['Save' => null], [], null, extends: 'en'));
		$file = $this->catalog('en_US', new CatalogFile(['Save' => null], [], null, extends: 'en_GB'));

		new Sync($this->domain(['en_US', 'en_GB']))->run();

		$this->assertSame('.', file_get_contents($base . '.reads'));
		$this->assertSame([], CatalogFile::load($file)->messages);
	}

	public function testSyncValidatesAllChainsBeforeWriting(): void
	{
		$this->write('src/x.php', "<?php __('New');");
		$base = $this->write('i18n/app.en.php', "<?php return [];\n");
		$variant = $this->write('i18n/app.en_US.php', "<?php return ['extends' => 'missing'];\n");

		try {
			new Sync($this->domain(['en', 'en_US']))->run();
			$this->fail('Missing parent accepted');
		} catch (UnexpectedValueException $e) {
			$this->assertSame("Missing parent catalog '{$this->tmpDir()}/i18n/app.missing.php'", $e->getMessage());
		}

		$this->assertSame("<?php return [];\n", file_get_contents($base));
		$this->assertSame("<?php return ['extends' => 'missing'];\n", file_get_contents($variant));
	}

	public function testStatusSeparatesInheritedLocalAndUntranslatedMessages(): void
	{
		$source = $this->write('src/x.php', "<?php
__('Local');
__('Inherited');
__('Placeholder');
__n('Empty', 'Empties', 2);
__p('menu', 'Open');
__p('state', 'Open');
__('Missing');
__('Untranslated');
__('WrongKind');
__n('PluralString', 'PluralStrings', 2);
__n('LocalPlural', 'LocalPlurals', 2);
");
		$this->catalog('en', new CatalogFile(
			[
				'Inherited' => 'Base',
				'Placeholder' => 'Base',
				'Empty' => ['one', 'many'],
				'WrongKind' => ['plural only'],
				'PluralString' => 'Any count',
			],
			['Missing' => 'Obsolete'],
			null,
			['menu' => ['Open' => 'Menu']],
		));
		$this->catalog('en_GB', new CatalogFile(['Inherited' => null, 'Empty' => []], [], null, extends: 'en'));
		$this->catalog(
			'en_US',
			new CatalogFile(
				[
					'Local' => '',
					'Placeholder' => null,
					'Empty' => [],
					'Untranslated' => null,
					'WrongKind' => ['plural only'],
					'LocalPlural' => ['local one', 'local many'],
				],
				[],
				null,
				extends: 'en_GB',
			),
		);

		$report = new Status($this->domain())->run();

		$this->assertSame(
			[
				'missing' => 2,
				'untranslated' => 2,
				'translated' => 2,
				'inherited' => 5,
				'obsolete' => 0,
				'total' => 11,
				'locations' => ["{$source}:8", "{$source}:9", "{$source}:10", "{$source}:7"],
				'parked' => [],
				'vanished' => [],
			],
			$report->locales['en_US'],
		);
		$this->assertFalse($report->clean());
	}

	public function testStatusReadsEachCatalogOnce(): void
	{
		$this->write('src/x.php', "<?php __('Save');");
		$base = $this->write(
			'i18n/app.en.php',
			"<?php file_put_contents(__FILE__ . '.reads', '.', FILE_APPEND); return ['messages' => ['Save' => 'Saved']];",
		);
		$this->catalog('en_GB', new CatalogFile([], [], null, extends: 'en'));
		$this->catalog('en_US', new CatalogFile([], [], null, extends: 'en_GB'));

		$report = new Status($this->domain(['en', 'en_GB', 'en_US']))->run();

		$this->assertSame('.', file_get_contents($base . '.reads'));
		$this->assertSame(1, $report->locales['en_US']['inherited']);
	}

	public function testNumericLocaleInheritance(): void
	{
		$this->write('src/x.php', "<?php __('Save');");
		$this->catalog('1', new CatalogFile(['Save' => 'Saved'], [], null));
		$file = $this->catalog('0', new CatalogFile(['Save' => null], [], null, extends: '1'));
		$domain = $this->domain(['0', '1']);

		new Sync($domain)->run();

		$this->assertSame([], CatalogFile::load($file)->messages);
		$this->assertSame('1', CatalogFile::load($file)->extends);
		$this->assertSame(1, new Status($domain)->run()->locales['0']['inherited']);
		$this->assertTrue(new Status($domain)->run()->clean());
	}

	public function testToolingValidatesParentEntries(): void
	{
		$this->catalog('en_US', new CatalogFile([], [], null, extends: 'en'));
		$file = $this->write('i18n/app.en.php', "<?php return ['messages' => ['Bad' => 42]];");
		$this->expectException(UnexpectedValueException::class);
		$this->expectExceptionMessage(
			"Invalid catalog entry messages['Bad'] in '{$file}': expected a string, a list of strings, or null",
		);

		new Status($this->domain())->run();
	}
}
