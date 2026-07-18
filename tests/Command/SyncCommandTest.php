<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Command;

use Celema\Console\Args;
use Celema\Console\Output;
use Celema\Verba\Command\SyncCommand;
use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\CatalogFile;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\PhpScanner;

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

	/** @return array{string, string} */
	private function capture(SyncCommand $command): array
	{
		$out = $this->tmpDir() . '/out.txt';
		$err = $this->tmpDir() . '/err.txt';
		file_put_contents($err, '');
		$args = new Args(array_slice($_SERVER['argv'] ?? [], offset: 2));
		$command->output(new Output($out, $err))->run($args);

		return [(string) file_get_contents($out), (string) file_get_contents($err)];
	}

	public function testReportsPerLocale(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:sync'];
		$this->write('src/x.php', "<?php\n__('A');\n");

		[$output] = $this->capture(new SyncCommand([$this->domain()]));

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

		[, $error] = $this->capture(new SyncCommand([$this->domain()]));

		$this->assertStringContainsString('Non-literal message id', $error);
	}

	public function testReturnsZero(): void
	{
		$_SERVER['argv'] = ['run', 'i18n:sync'];
		$this->write('src/x.php', "<?php\n__('A');\n");

		$args = new Args(array_slice($_SERVER['argv'] ?? [], offset: 2));
		$exit = new SyncCommand([$this->domain()])
			->output(new Output($this->tmpDir() . '/o.txt'))
			->run($args);

		$this->assertSame(0, $exit);
	}
}
