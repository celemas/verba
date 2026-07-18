<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Tool;

use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\CatalogFile;

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

	public function testRenderRoundTrips(): void
	{
		$messages = [
			'b' => 'B',
			'a' => null,
			'plural id' => ['form0', 'form1'],
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
			"<?php\nreturn ['contexts' => ['good' => ['A' => 'B'], 'bad' => 'x', 3 => []], "
			. "'obsolete_contexts' => 'bad'];\n",
		);
		$catalog = CatalogFile::load($file);

		$this->assertSame(['good' => ['A' => 'B']], $catalog->contexts);
		$this->assertSame([], $catalog->obsoleteContexts);
	}
}
