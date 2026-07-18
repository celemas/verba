<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Tool;

use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\CatalogFile;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\PhpScanner;
use Celema\Verba\Tool\Sync;
use RuntimeException;

class SyncTest extends TestCase
{
	private function domain(?string $dir = null): Domain
	{
		return new Domain(
			'app',
			$dir ?? $this->tmpDir() . '/i18n',
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

	public function testAddsAndPreservesContextualMessages(): void
	{
		$this->write('src/x.php', "<?php\n__p('menu', 'Open');\n__p('state', 'Open');\n");
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ['messages' => [], 'contexts' => ['menu' => ['Open' => 'Öffnen']]];\n",
		);

		$report = new Sync($this->domain())->run();
		$catalog = $this->catalog();

		$this->assertSame('Öffnen', $catalog->contexts['menu']['Open']);
		$this->assertNull($catalog->contexts['state']['Open']);
		$this->assertSame(1, $report->locales['de']['added']);
		$this->assertSame(2, $report->locales['de']['total']);
	}

	public function testRestoresContextualMessage(): void
	{
		$this->write('src/x.php', "<?php\n__p('menu', 'Back');\n");
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ['messages' => [], "
			. "'obsolete_contexts' => ['menu' => ['Back' => 'Zurück']]];\n",
		);

		new Sync($this->domain())->run();
		$catalog = $this->catalog();

		$this->assertSame('Zurück', $catalog->contexts['menu']['Back']);
		$this->assertSame([], $catalog->obsoleteContexts);
	}

	public function testParksContextualMessage(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n");
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ['messages' => ['A' => 'Ae'], "
			. "'contexts' => ['menu' => ['Gone' => 'Weg']]];\n",
		);

		$report = new Sync($this->domain())->run();
		$catalog = $this->catalog();

		$this->assertSame([], $catalog->contexts);
		$this->assertSame(['menu' => ['Gone' => 'Weg']], $catalog->obsoleteContexts);
		$this->assertSame(1, $report->locales['de']['obsolete']);
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
			"<?php\nreturn ['messages' => ['A' => 'Ae', 'Gone' => 'Weg'], "
			. "'contexts' => ['menu' => ['Old' => 'Alt']], "
			. "'obsolete_contexts' => ['state' => ['Old' => 'Alt']]];\n",
		);

		new Sync($this->domain(), prune: true)->run();
		$catalog = $this->catalog();

		$this->assertSame(['A' => 'Ae'], $catalog->messages);
		$this->assertSame([], $catalog->obsolete);
		$this->assertSame([], $catalog->contexts);
		$this->assertSame([], $catalog->obsoleteContexts);
	}

	public function testThrowsWhenCatalogDirectoryCannotBeCreated(): void
	{
		$blocked = $this->write('blocked', 'not a directory');

		try {
			new Sync($this->domain($blocked))->run();
			self::fail('Expected catalog directory creation to fail');
		} catch (RuntimeException $exception) {
			$this->assertStringContainsString('Cannot create catalog directory', $exception->getMessage());
			$this->assertStringContainsString('mkdir(', $exception->getMessage());
		}
	}

	public function testKeepsCatalogWhenTemporaryWriteFails(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n");
		$contents = "<?php\nreturn ['messages' => ['A' => 'Ae']];\n";
		$file = $this->write('i18n/app.de.php', $contents);
		$dir = dirname($file);
		chmod($dir, 0o555);
		clearstatcache(true, $dir);

		if (is_writable($dir)) {
			chmod($dir, 0o755);
			$this->markTestSkipped('Cannot create a non-writable directory on this platform');
		}

		try {
			new Sync($this->domain())->run();
			self::fail('Expected the temporary catalog write to fail');
		} catch (RuntimeException $exception) {
			$this->assertStringContainsString('Cannot write temporary catalog', $exception->getMessage());
			$this->assertStringContainsString('file_put_contents(', $exception->getMessage());
		} finally {
			chmod($dir, 0o755);
		}

		$this->assertSame($contents, file_get_contents($file));
	}

	public function testCleansTemporaryFileWhenReplacementFails(): void
	{
		$marker = $this->write('i18n/app.de.php/marker', 'keep');
		$file = dirname($marker);

		try {
			new Sync($this->domain())->run();
			self::fail('Expected the catalog replacement to fail');
		} catch (RuntimeException $exception) {
			$this->assertStringContainsString('Cannot replace catalog', $exception->getMessage());
			$this->assertStringContainsString('rename(', $exception->getMessage());
		}

		$this->assertFileExists($marker);
		$this->assertSame([], glob($file . '.*.tmp'));
	}
}
