<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests\Tool;

use Celemas\Verba\Tests\TestCase;
use Celemas\Verba\Tool\Message;
use Celemas\Verba\Tool\PhpScanner;

class PhpScannerTest extends TestCase
{
	/**
	 * @param list<Message> $messages
	 * @return list<string>
	 */
	private function ids(array $messages): array
	{
		return array_map(static fn(Message $m): string => $m->id, $messages);
	}

	public function testExtractsLiteralIdsAndDecodesEscapes(): void
	{
		$code = <<<'PHP'
			<?php

			__('Simple');
			__("Double");
			__('It\'s here');
			__("Tab\there");
			__('Back\\slash');
			\__('Fully qualified');
			PHP;

		$scanner = new PhpScanner([$this->write('a.php', $code)]);
		$ids = $this->ids($scanner->scan());

		$this->assertContains('Simple', $ids);
		$this->assertContains('Double', $ids);
		$this->assertContains("It's here", $ids);
		$this->assertContains("Tab\there", $ids);
		$this->assertContains('Back\\slash', $ids);
		$this->assertContains('Fully qualified', $ids);
		$this->assertSame([], $scanner->warnings());
	}

	public function testExtractsDomainAndPluralArguments(): void
	{
		$code = <<<'PHP'
			<?php

			$count = 2;
			__n('one item', '%d items', $count);
			__d('shop', 'Shop label');
			__dn('shop', 'one order', '%d orders', $count);
			__('Nested', ['k' => strlen('x')]);
			PHP;

		$scanner = new PhpScanner([$this->write('a.php', $code)]);
		$messages = $scanner->scan();
		$byId = [];

		foreach ($messages as $message) {
			$byId[$message->id] = $message;
		}

		$this->assertNull($byId['one item']->domain);
		$this->assertSame('%d items', $byId['one item']->plural);
		$this->assertSame('shop', $byId['Shop label']->domain);
		$this->assertSame('shop', $byId['one order']->domain);
		$this->assertSame('%d orders', $byId['one order']->plural);
		$this->assertSame('Nested', $byId['Nested']->id);
		$this->assertSame([], $scanner->warnings());
	}

	public function testExtractsNestedCalls(): void
	{
		$code = <<<'PHP'
			<?php

			__('Outer :inner', ['inner' => __('Inner')]);
			PHP;

		$scanner = new PhpScanner([$this->write('a.php', $code)]);

		$this->assertSame(['Outer :inner', 'Inner'], $this->ids($scanner->scan()));
		$this->assertSame([], $scanner->warnings());
	}

	public function testSkipsMethodStaticAndDeclaration(): void
	{
		$code = <<<'PHP'
			<?php

			$obj->__('method');
			Dummy::__('static');
			function __($x) { return $x; }
			PHP;

		$scanner = new PhpScanner([$this->write('a.php', $code)]);

		$this->assertSame([], $scanner->scan());
		$this->assertSame([], $scanner->warnings());
	}

	public function testWarnsOnNonLiteralArguments(): void
	{
		$code = <<<'PHP'
			<?php

			__($dynamic);
			__d($domain, 'x');
			__n('one', $plural, 2);
			__();
			PHP;

		$scanner = new PhpScanner([$this->write('a.php', $code)]);
		$messages = $scanner->scan();
		$warnings = implode("\n", $scanner->warnings());

		$this->assertSame([], $messages);
		$this->assertStringContainsString('Non-literal message id', $warnings);
		$this->assertStringContainsString('Non-literal domain', $warnings);
		$this->assertStringContainsString('Non-literal plural', $warnings);
	}

	public function testIgnoresBareNameWithoutCall(): void
	{
		$scanner = new PhpScanner([$this->write('a.php', "<?php\n\$x = __ . 'tail';\n")]);

		$this->assertSame([], $scanner->scan());
		$this->assertSame([], $scanner->warnings());
	}

	public function testMergesLocationsAcrossFilesAndSkipsForeignFiles(): void
	{
		$this->write('src/b.php', "<?php\n__('Shared');\n");
		$this->write('src/a.php', "<?php\n__('Shared');\n__('Only A');\n");
		$this->write('src/notes.txt', "__('ignored, not php');\n");

		$scanner = new PhpScanner([$this->tmpDir() . '/src']);
		$messages = $scanner->scan();

		$this->assertCount(3, $messages);
		$this->assertContains('Shared', $this->ids($messages));
	}

	public function testWarnsOnUnterminatedCall(): void
	{
		$scanner = new PhpScanner([$this->write('a.php', "<?php\n__('A'")]);

		$this->assertSame([], $scanner->scan());
		$this->assertStringContainsString('Non-literal message id', implode("\n", $scanner->warnings()));
	}

	public function testIgnoresTrailingNameAtEndOfFile(): void
	{
		$scanner = new PhpScanner([$this->write('a.php', '<?php __ ')]);

		$this->assertSame([], $scanner->scan());
		$this->assertSame([], $scanner->warnings());
	}

	public function testSkipsUnreadableRoots(): void
	{
		$scanner = new PhpScanner([$this->tmpDir() . '/does-not-exist']);

		$this->assertSame([], $scanner->scan());
	}
}
