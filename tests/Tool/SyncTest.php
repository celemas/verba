<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Tool;

use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\CatalogFile;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\PhpScanner;
use Celema\Verba\Tool\Sync;
use RuntimeException;
use UnexpectedValueException;

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
		$this->assertSame(['Gone'], $report->locales['de']['vanished']);
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
		$this->assertSame(['[menu] Gone'], $report->locales['de']['vanished']);
	}

	public function testSyncsNumericIdsAndContexts(): void
	{
		$this->write('src/x.php', "<?php\n__('404');\n__('0');\n__p('0', 'Open');\n");
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ['messages' => ['404' => 'Nicht gefunden', '7' => 'Sieben'], "
				. "'contexts' => ['0' => ['Open' => 'Offen'], '1' => ['Gone' => 'Weg']]];\n",
		);

		$report = new Sync($this->domain())->run();
		$catalog = $this->catalog();

		$this->assertSame(['0' => null, '404' => 'Nicht gefunden'], $catalog->messages);
		$this->assertSame(['7' => 'Sieben'], $catalog->obsolete);
		$this->assertSame(['0' => ['Open' => 'Offen']], $catalog->contexts);
		$this->assertSame(['1' => ['Gone' => 'Weg']], $catalog->obsoleteContexts);
		$this->assertSame(['7', '[1] Gone'], $report->locales['de']['vanished']);
		$this->assertFalse(new Sync($this->domain())->run()->locales['de']['changed']);
	}

	public function testRejectsMalformedCatalogWithoutRewritingIt(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n");
		$source = "<?php\nreturn ['messages' => ['A' => 123]];\n";
		$file = $this->write('i18n/app.de.php', $source);

		try {
			new Sync($this->domain())->run();
			$this->fail('Sync accepted a malformed catalog');
		} catch (UnexpectedValueException $e) {
			$this->assertStringContainsString("messages['A']", $e->getMessage());
		}

		$this->assertSame($source, file_get_contents($file));
	}

	public function testHandlesSeveralContextsAtOnce(): void
	{
		$this->write('src/x.php', "<?php\n__p('menu', 'Open');\n__p('state', 'Open');\n__p('kept', 'Here');\n");
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ['messages' => ['Gone' => 'Weg', 'Lost' => 'Verloren'], "
				. "'contexts' => ['kept' => ['Here' => 'Hier'], 'a' => ['X' => 'x'], 'b' => ['Y' => 'y']], "
				. "'obsolete_contexts' => ['c' => ['Z' => 'z']]];\n",
		);

		$report = new Sync($this->domain())->run();
		$catalog = $this->catalog();

		$this->assertSame(2, $report->locales['de']['added']);
		$this->assertSame(['Gone', 'Lost', '[a] X', '[b] Y'], $report->locales['de']['vanished']);
		$this->assertSame(['Here' => 'Hier'], $catalog->contexts['kept']);
		$this->assertSame(
			['a' => ['X' => 'x'], 'b' => ['Y' => 'y'], 'c' => ['Z' => 'z']],
			$catalog->obsoleteContexts,
		);
	}

	public function testCreatesCatalogDirectoryWithStandardPermissions(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n");
		$dir = $this->tmpDir() . '/nested/i18n';
		$umask = umask(0o022);

		try {
			new Sync($this->domain($dir))->run();
		} finally {
			umask($umask);
		}

		clearstatcache();
		$this->assertSame(0o755, fileperms($dir) & 0o777);
	}

	public function testSecondRunIsIdempotent(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n");

		new Sync($this->domain())->run();
		$report = new Sync($this->domain())->run();

		$this->assertFalse($report->locales['de']['changed']);
		$this->assertSame(0, $report->locales['de']['added']);
	}

	public function testAllReadsEveryDomainBeforeWritingAny(): void
	{
		$this->write('src/x.php', "<?php\n__d('a', 'New');\n__d('b', 'New');\n");
		$valid = $this->write('i18n/a.en.php', "<?php return [];\n");
		$broken = $this->write('i18n/b.en.php', "<?php return ['extends' => 'missing'];\n");
		$scanners = [new PhpScanner([$this->tmpDir() . '/src'])];
		$domains = [
			new Domain('a', $this->tmpDir() . '/i18n', ['en'], $scanners),
			new Domain('b', $this->tmpDir() . '/i18n', ['en'], $scanners),
		];

		try {
			Sync::all($domains);
			$this->fail('Broken chain accepted');
		} catch (UnexpectedValueException $e) {
			$this->assertSame("Missing parent catalog '{$this->tmpDir()}/i18n/b.missing.php'", $e->getMessage());
		}

		$this->assertSame("<?php return [];\n", file_get_contents($valid));

		file_put_contents($broken, "<?php return ['obsolete' => ['Gone' => 'Weg']];\n");
		$reports = Sync::all($domains);

		$this->assertSame(['a', 'b'], array_map(static fn($report): string => $report->domain, $reports));
		$this->assertSame(['New' => null], CatalogFile::load($valid)->messages);
		$this->assertSame(['Gone' => 'Weg'], CatalogFile::load($broken)->obsolete);

		Sync::all($domains, prune: true);

		$this->assertSame([], CatalogFile::load($broken)->obsolete);
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

		$report = new Sync($this->domain(), prune: true)->run();
		$catalog = $this->catalog();

		$this->assertSame(['A' => 'Ae'], $catalog->messages);
		$this->assertSame([], $catalog->obsolete);
		$this->assertSame([], $catalog->contexts);
		$this->assertSame([], $catalog->obsoleteContexts);
		// Named although dropped: the report is the only trace they leave.
		$this->assertSame(['Gone', '[menu] Old'], $report->locales['de']['vanished']);
	}

	public function testThrowsWhenCatalogDirectoryCannotBeCreated(): void
	{
		$blocked = $this->write('blocked', 'not a directory');

		error_clear_last();

		try {
			new Sync($this->domain($blocked))->run();
			self::fail('Expected catalog directory creation to fail');
		} catch (RuntimeException $exception) {
			$this->assertStringStartsWith(
				"Cannot create catalog directory '{$blocked}': mkdir(",
				$exception->getMessage(),
			);
		}

		$this->assertNull(error_get_last());
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
			$this->assertStringStartsWith(
				"Cannot write temporary catalog for '{$file}': file_put_contents(",
				$exception->getMessage(),
			);
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
			$this->assertStringStartsWith("Cannot replace catalog '{$file}': rename(", $exception->getMessage());
		}

		$this->assertFileExists($marker);
		$this->assertSame([], glob($file . '.*.tmp'));
	}
}
