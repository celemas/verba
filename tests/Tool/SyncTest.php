<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests\Tool;

use Celemas\Verba\Tests\TestCase;
use Celemas\Verba\Tool\CatalogFile;
use Celemas\Verba\Tool\Domain;
use Celemas\Verba\Tool\PhpScanner;
use Celemas\Verba\Tool\Sync;

class SyncTest extends TestCase
{
	private function domain(): Domain
	{
		return new Domain(
			'app',
			$this->tmpDir() . '/i18n',
			['de', 'en'],
			[new PhpScanner([$this->tmpDir() . '/src'])],
			default: true,
		);
	}

	private function catalog(): CatalogFile
	{
		return CatalogFile::load($this->tmpDir() . '/i18n/app.de.php');
	}

	public function testFirstSyncCreatesUntranslatedCatalogs(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n__('B');\n");

		$report = new Sync($this->domain())->run();

		$this->assertSame(['A' => null, 'B' => null], $this->catalog()->messages);
		$this->assertSame(2, $report->locales['de']['added']);
		$this->assertTrue($report->locales['de']['changed']);
		$this->assertSame([], $report->warnings);
	}

	public function testKeepsRestoresAndParks(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n__('B');\n__('Back');\n");
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ["
			. "'messages' => ['A' => 'Ae', 'Gone' => 'Weg'], "
			. "'obsolete' => ['Back' => 'Zurück', 'Ancient' => 'Alt']];\n",
		);

		$report = new Sync($this->domain())->run();
		$catalog = $this->catalog();

		$this->assertSame('Ae', $catalog->messages['A']);
		$this->assertNull($catalog->messages['B']);
		$this->assertSame('Zurück', $catalog->messages['Back']);
		$this->assertSame(['Ancient' => 'Alt', 'Gone' => 'Weg'], $catalog->obsolete);
		$this->assertSame(1, $report->locales['de']['added']);
		$this->assertSame(2, $report->locales['de']['obsolete']);
	}

	public function testSecondRunIsIdempotent(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n");

		new Sync($this->domain())->run();
		$report = new Sync($this->domain())->run();

		$this->assertFalse($report->locales['de']['changed']);
		$this->assertSame(0, $report->locales['de']['added']);
	}

	public function testPruneDropsObsolete(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n");
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ['messages' => ['A' => 'Ae', 'Gone' => 'Weg']];\n",
		);

		new Sync($this->domain(), prune: true)->run();

		$this->assertSame(['A' => 'Ae'], $this->catalog()->messages);
		$this->assertSame([], $this->catalog()->obsolete);
	}
}
