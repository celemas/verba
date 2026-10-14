<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Tool;

use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\CatalogFile;
use PHPUnit\Framework\Attributes\DataProvider;
use UnexpectedValueException;

class CatalogFileTest extends TestCase
{
	public function testLoadMissingFile(): void
	{
		$catalog = CatalogFile::load($this->tmpDir() . '/nope.php');

		$this->assertSame([], $catalog->messages);
		$this->assertSame([], $catalog->obsolete);
		$this->assertSame([], $catalog->contexts);
		$this->assertSame([], $catalog->obsoleteContexts);
		$this->assertNull($catalog->plural);
	}

	public function testLoadNonArrayFile(): void
	{
		$catalog = CatalogFile::load($this->write('bad.php', "<?php\nreturn 'x';\n"));

		$this->assertSame([], $catalog->messages);
	}

	public function testLoadPopulatedFile(): void
	{
		$file = $this->write(
			'app.de.php',
			"<?php\nreturn ["
				. "'plural' => 'ru', 'messages' => ['A' => 'Ae'], 'obsolete' => ['B' => 'Be'], "
				. "'contexts' => ['menu' => ['Open' => 'Öffnen']], "
				. "'obsolete_contexts' => ['menu' => ['Old' => 'Alt']]];\n",
		);
		$catalog = CatalogFile::load($file);

		$this->assertSame(['A' => 'Ae'], $catalog->messages);
		$this->assertSame(['B' => 'Be'], $catalog->obsolete);
		$this->assertSame(['menu' => ['Open' => 'Öffnen']], $catalog->contexts);
		$this->assertSame(['menu' => ['Old' => 'Alt']], $catalog->obsoleteContexts);
		$this->assertSame('ru', $catalog->plural);
	}

	public function testLoadIgnoresNonStringPlural(): void
	{
		$file = $this->write('app.de.php', "<?php\nreturn ['plural' => 123, 'messages' => []];\n");

		$this->assertNull(CatalogFile::load($file)->plural);
	}

	#[DataProvider('provideMalformedEntries')]
	public function testLoadRejectsMalformedEntries(string $catalog, string $entry): void
	{
		$file = $this->write('app.de.php', "<?php\nreturn {$catalog};\n");

		$this->expectException(UnexpectedValueException::class);
		$this->expectExceptionMessage("Invalid catalog entry {$entry} in '{$file}'");

		CatalogFile::load($file);
	}

	/**
	 * @return list<array{string, string}>
	 */
	public static function provideMalformedEntries(): array
	{
		return [
			["['messages' => ['A' => 123]]", "messages['A']"],
			["['obsolete' => ['A' => true]]", "obsolete['A']"],
			["['contexts' => ['menu' => ['A' => ['eins', false]]]]", "contexts['menu']['A']"],
			["['obsolete_contexts' => ['0' => ['A' => ['one' => 'eins']]]]", "obsolete_contexts['0']['A']"],
		];
	}

	public function testRenderRoundTrips(): void
	{
		$messages = [
			'b' => 'B',
			'a' => null,
			'plural id' => ['form0', 'form1'],
			'untranslated plural' => [],
			"quote's" => 'back\\slash',
		];
		$obsolete = ['gone' => 'weg'];
		$contexts = [
			'state' => ['Open' => 'Offen'],
			'menu' => ['Open' => 'Öffnen', 'unused' => null],
		];
		$obsoleteContexts = ['menu' => ['Old' => 'Alt']];
		$rendered = new CatalogFile(
			$messages,
			$obsolete,
			'ru',
			$contexts,
			$obsoleteContexts,
		)->render();

		$reloaded = CatalogFile::load($this->write('out.php', $rendered));
		ksort($messages, SORT_STRING);

		$this->assertSame($messages, $reloaded->messages);
		$this->assertSame($obsolete, $reloaded->obsolete);
		$this->assertSame(
			['menu' => $contexts['menu'], 'state' => $contexts['state']],
			$reloaded->contexts,
		);
		$this->assertSame($obsoleteContexts, $reloaded->obsoleteContexts);
		$this->assertSame('ru', $reloaded->plural);
	}

	public function testRenderRoundTripsNumericIdsAndContexts(): void
	{
		$messages = ['0' => 'Null', '404' => 'Nicht gefunden', 'a' => 'A'];
		$obsolete = ['7' => 'Sieben'];
		$contexts = ['0' => ['1' => 'Eins'], 'menu' => ['2' => 'Zwei']];
		$obsoleteContexts = ['3' => ['Old' => 'Alt']];
		$rendered = new CatalogFile(
			$messages,
			$obsolete,
			null,
			$contexts,
			$obsoleteContexts,
		)->render();

		$reloaded = CatalogFile::load($this->write('out.php', $rendered));

		$this->assertSame($messages, $reloaded->messages);
		$this->assertSame($obsolete, $reloaded->obsolete);
		$this->assertSame($contexts, $reloaded->contexts);
		$this->assertSame($obsoleteContexts, $reloaded->obsoleteContexts);
	}

	public function testRenderLayout(): void
	{
		$rendered = new CatalogFile(
			['b' => 'B', 'a' => null, 'plural id' => ['form0', 'form1']],
			['gone' => 'weg'],
			'ru',
			['state' => ['Open' => 'Offen'], 'menu' => ['Open' => 'Öffnen']],
			['menu' => ['Old' => 'Alt']],
		)->render();

		$this->assertSame(
			<<<'PHP'
				<?php

				declare(strict_types=1);

				return [
					'plural' => 'ru',
					'messages' => [
						'a' => null,
						'b' => 'B',
						'plural id' => ['form0', 'form1'],
					],
					'contexts' => [
						'menu' => [
							'Open' => 'Öffnen',
						],
						'state' => [
							'Open' => 'Offen',
						],
					],
					'obsolete' => [
						'gone' => 'weg',
					],
					'obsolete_contexts' => [
						'menu' => [
							'Old' => 'Alt',
						],
					],
				];

				PHP,
			$rendered,
		);
	}

	public function testRenderWithoutPluralOrObsolete(): void
	{
		$rendered = new CatalogFile(['a' => 'A'], [], null, ['empty' => []], ['empty' => []])->render();

		$this->assertStringNotContainsString('plural', $rendered);
		$this->assertStringNotContainsString('obsolete', $rendered);
		$this->assertStringNotContainsString('contexts', $rendered);
		$this->assertSame(['a' => 'A'], CatalogFile::load($this->write('out.php', $rendered))->messages);
	}

	public function testLoadIgnoresMalformedContextSections(): void
	{
		$file = $this->write(
			'app.de.php',
			"<?php\nreturn ['contexts' => ['bad' => 'x', 'good' => ['A' => 'B']], "
				. "'obsolete_contexts' => 'bad'];\n",
		);
		$catalog = CatalogFile::load($file);

		$this->assertSame(['good' => ['A' => 'B']], $catalog->contexts);
		$this->assertSame([], $catalog->obsoleteContexts);
	}
}
