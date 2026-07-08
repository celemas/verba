<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests\Tool;

use Celemas\Verba\Tests\TestCase;
use Celemas\Verba\Tool\Domain;
use Celemas\Verba\Tool\PhpScanner;
use Celemas\Verba\Tool\Status;

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
			"<?php\nreturn ['messages' => ['A' => 'Ae', 'B' => null, 'Extra' => 'X']];\n",
		);

		$report = new Status($this->domain(['de']))->run();
		$de = $report->locales['de'];

		$this->assertSame(1, $de['translated']);
		$this->assertSame(1, $de['untranslated']);
		$this->assertSame(0, $de['missing']);
		$this->assertSame(1, $de['obsolete']);
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

	public function testCleanWhenFullyTranslated(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n");
		$this->write('i18n/app.de.php', "<?php\nreturn ['messages' => ['A' => 'Ae']];\n");

		$this->assertTrue(new Status($this->domain(['de']))->run()->clean());
	}
}
