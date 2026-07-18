<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Tool;

use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\PhpScanner;
use Celema\Verba\Tool\Status;

class StatusTest extends TestCase
{
	/**
	 * @param list<string> $locales
	 */
	private function domain(array $locales): Domain
	{
		return new Domain(
			'app',
			$this->tmpDir() . '/i18n',
			$locales,
			[new PhpScanner([$this->tmpDir() . '/src'])],
			default: true,
		);
	}

	public function testReportsGaps(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n__('B');\n");
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ['messages' => ['A' => 'Ae', 'B' => null, 'Extra' => 'X'], 'obsolete' => ['Old' => 'Alt']];\n",
		);

		$report = new Status($this->domain(['de']))->run();
		$de = $report->locales['de'];

		$this->assertSame(1, $de['translated']);
		$this->assertSame(1, $de['untranslated']);
		$this->assertSame(0, $de['missing']);
		$this->assertSame(2, $de['obsolete']);
		$this->assertSame(2, $de['total']);
		$this->assertFalse($report->clean());
	}

	public function testMissingWhenNoCatalog(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n__('B');\n");

		$report = new Status($this->domain(['fr']))->run();
		$fr = $report->locales['fr'];

		$this->assertSame(2, $fr['missing']);
		$this->assertCount(2, $fr['locations']);
	}

	public function testReportsContextualGapsAndObsoleteEntries(): void
	{
		$this->write(
			'src/x.php',
			"<?php\n__p('menu', 'Open');\n__p('state', 'Open');\n__p('menu', 'Save');\n",
		);
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ['messages' => [], "
			. "'contexts' => ['menu' => ['Open' => 'Öffnen', 'Save' => null, 'Extra' => 'X'], "
			. "'wrong' => ['Open' => 'Falsch']], "
			. "'obsolete_contexts' => ['menu' => ['Old' => 'Alt']]];\n",
		);

		$de = new Status($this->domain(['de']))->run()->locales['de'];

		$this->assertSame(1, $de['translated']);
		$this->assertSame(1, $de['untranslated']);
		$this->assertSame(1, $de['missing']);
		$this->assertSame(3, $de['obsolete']);
		$this->assertSame(3, $de['total']);
		$this->assertCount(2, $de['locations']);
	}

	public function testCleanWhenFullyTranslated(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n");
		$this->write('i18n/app.de.php', "<?php\nreturn ['messages' => ['A' => 'Ae']];\n");

		$this->assertTrue(new Status($this->domain(['de']))->run()->clean());
	}
}
