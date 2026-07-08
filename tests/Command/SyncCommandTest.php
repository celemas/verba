<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests\Command;

use Celemas\Cli\Output;
use Celemas\Verba\Command\SyncCommand;
use Celemas\Verba\Tests\TestCase;
use Celemas\Verba\Tool\CatalogFile;
use Celemas\Verba\Tool\Domain;
use Celemas\Verba\Tool\PhpScanner;

class SyncCommandTest extends TestCase
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

	private function domain(): Domain
	{
		return new Domain(
			'app',
			$this->tmpDir() . '/i18n',
			['de'],
			[new PhpScanner([$this->tmpDir() . '/src'])],
			default: true,
		);
	}

	private function capture(SyncCommand $command): string
	{
		$out = $this->tmpDir() . '/out.txt';
		$command->output(new Output($out))->run();

		return (string) file_get_contents($out);
	}

	public function testReportsPerLocale(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:sync'];
		$this->write('src/x.php', "<?php\n__('A');\n");

		$output = $this->capture(new SyncCommand([$this->domain()]));

		$this->assertStringContainsString('i18n: app', $output);
		$this->assertStringContainsString('1 added', $output);
	}

	public function testPruneFlagDropsObsolete(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:sync', '--prune'];
		$this->write('src/x.php', "<?php\n__('A');\n");
		$this->write(
			'i18n/app.de.php',
			"<?php\nreturn ['messages' => ['A' => 'Ae', 'Gone' => 'Weg']];\n",
		);

		$this->capture(new SyncCommand([$this->domain()]));

		$this->assertSame([], CatalogFile::load($this->tmpDir() . '/i18n/app.de.php')->obsolete);
	}

	public function testWarningsAreShown(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:sync'];
		$this->write('src/x.php', "<?php\n__(\$dynamic);\n");

		$this->assertStringContainsString(
			'Non-literal message id',
			$this->capture(new SyncCommand([$this->domain()])),
		);
	}

	public function testReturnsZero(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:sync'];
		$this->write('src/x.php', "<?php\n__('A');\n");

		$exit = new SyncCommand([$this->domain()])->output(new Output($this->tmpDir() . '/o.txt'))->run();

		$this->assertSame(0, $exit);
	}
}
