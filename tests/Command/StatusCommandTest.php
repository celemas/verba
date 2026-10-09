<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Command;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Celema\Console\Runner;
use Celema\Verba\Command\StatusCommand;
use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\PhpScanner;

class StatusCommandTest extends TestCase
{
	/** @var list<string> */
	private array $argv = [];

	protected function setUp(): void
	{
		$this->argv = $_SERVER['argv'] ?? [];
	}

	protected function tearDown(): void
	{
		$_SERVER['argv'] = $this->argv;

		parent::tearDown();
	}

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

	/**
	 * @return array{int, string, string}
	 */
	private function capture(StatusCommand $command): array
	{
		$buffer = new Buffer();
		$exit = new Runner([$command], new Io($buffer))->run();

		return [$exit, $buffer->output(), $buffer->errorOutput()];
	}

	public function testReportsStatusWithoutStrict(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:status'];
		$this->write('src/x.php', "<?php\n__('A');\n__('B');\n");
		$this->write('i18n/app.de.php', "<?php\nreturn ['messages' => ['A' => 'Ae']];\n");

		[$exit, $output] = $this->capture(new StatusCommand([$this->domain(['de'])]));

		$this->assertSame(0, $exit);
		$this->assertStringContainsString('i18n: app', $output);
		$this->assertStringContainsString('translated', $output);
	}

	public function testStrictFailsWhenGaps(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:status', '--strict'];
		$this->write('src/x.php', "<?php\n__('A');\n");

		[$exit] = $this->capture(new StatusCommand([$this->domain(['de'])]));

		$this->assertSame(1, $exit);
	}

	public function testStrictFailsForEmptyPluralList(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:status', '--strict'];
		$this->write('src/x.php', "<?php\n__n('A', 'As', 2);\n");
		$this->write('i18n/app.de.php', "<?php\nreturn ['messages' => ['A' => []]];\n");

		[$exit, $output] = $this->capture(new StatusCommand([$this->domain(['de'])]));

		$this->assertSame(1, $exit);
		$this->assertStringContainsString('0/1 translated, 0 missing, 1 untranslated', $output);
	}

	public function testStrictPassesWhenClean(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:status', '--strict'];
		$this->write('src/x.php', "<?php\n__('A');\n");
		$this->write('i18n/app.de.php', "<?php\nreturn ['messages' => ['A' => 'Ae']];\n");

		[$exit] = $this->capture(new StatusCommand([$this->domain(['de'])]));

		$this->assertSame(0, $exit);
	}

	public function testWhereListsLocations(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:status', '--where'];
		$this->write('src/x.php', "<?php\n__('A');\n");

		[, $output] = $this->capture(new StatusCommand([$this->domain(['de'])]));

		$this->assertStringContainsString("\n    {$this->tmpDir()}/src/x.php:2\n", $output);
	}

	public function testWhereNamesTheObsoleteIds(): void
	{
		$this->write('src/x.php', "<?php\n__('A');\n");
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ['messages' => ['A' => 'Ae', 'Gone' => 'Weg'], 'obsolete' => ['Old' => 'Alt']];\n",
		);

		$_SERVER['argv'] = ['run', 'i18n:status'];
		[, $counted] = $this->capture(new StatusCommand([$this->domain(['de'])]));

		$this->assertStringContainsString('2 obsolete', $counted);
		$this->assertStringNotContainsString('Gone', $counted);

		$_SERVER['argv'] = ['run', 'i18n:status', '--where'];
		[, $named] = $this->capture(new StatusCommand([$this->domain(['de'])]));

		$this->assertStringContainsString("    vanished: Gone\n", $named);
		$this->assertStringContainsString("    parked: Old\n", $named);
	}

	public function testShowsWarnings(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:status'];
		$this->write('src/x.php', "<?php\n__(\$dyn);\n");

		[, , $error] = $this->capture(new StatusCommand([$this->domain(['de'])]));

		$this->assertMatchesRegularExpression('/^(\e\[[0-9;]*m)?  Non-literal message id/m', $error);
	}
}
