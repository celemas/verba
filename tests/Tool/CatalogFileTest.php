<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests\Tool;

use Celemas\Verba\Tests\TestCase;
use Celemas\Verba\Tool\CatalogFile;

class CatalogFileTest extends TestCase
{
	public function testLoadMissingFile(): void
	{
		$catalog = CatalogFile::load($this->tmpDir() . '/nope.php');

		$this->assertSame([], $catalog->messages);
		$this->assertSame([], $catalog->obsolete);
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
			"<?php\nreturn ['plural' => 'ru', 'messages' => ['A' => 'Ae'], 'obsolete' => ['B' => 'Be']];\n",
		);
		$catalog = CatalogFile::load($file);

		$this->assertSame(['A' => 'Ae'], $catalog->messages);
		$this->assertSame(['B' => 'Be'], $catalog->obsolete);
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
		$rendered = new CatalogFile($messages, $obsolete, 'ru')->render();

		$reloaded = CatalogFile::load($this->write('out.php', $rendered));
		ksort($messages, SORT_STRING);

		$this->assertSame($messages, $reloaded->messages);
		$this->assertSame($obsolete, $reloaded->obsolete);
		$this->assertSame('ru', $reloaded->plural);
	}

	public function testRenderWithoutPluralOrObsolete(): void
	{
		$rendered = new CatalogFile(['a' => 'A'], [], null)->render();

		$this->assertStringNotContainsString('plural', $rendered);
		$this->assertStringNotContainsString('obsolete', $rendered);
		$this->assertSame(['a' => 'A'], CatalogFile::load($this->write('out.php', $rendered))->messages);
	}
}
