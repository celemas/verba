<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Tool;

use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\Message;
use Celema\Verba\Tool\PhpScanner;

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
			\__p('qualified', 'Qualified context');
			PHP;

		$scanner = new PhpScanner([$this->write('a.php', $code)]);
		$ids = $this->ids($scanner->scan());

		$this->assertContains('Simple', $ids);
		$this->assertContains('Double', $ids);
		$this->assertContains("It's here", $ids);
		$this->assertContains("Tab\there", $ids);
		$this->assertContains('Back\\slash', $ids);
		$this->assertContains('Fully qualified', $ids);
		$this->assertContains('Qualified context', $ids);
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
			__p('menu', 'Open');
			__np('inventory', 'one result', '%d results', $count);
			__dp('shop', 'button', 'Buy');
			__dnp('shop', 'orders', 'one sale', '%d sales', $count);
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
		$this->assertSame('menu', $byId['Open']->context);
		$this->assertNull($byId['Open']->domain);
		$this->assertSame('inventory', $byId['one result']->context);
		$this->assertSame('%d results', $byId['one result']->plural);
		$this->assertSame('shop', $byId['Buy']->domain);
		$this->assertSame('button', $byId['Buy']->context);
		$this->assertSame('shop', $byId['one sale']->domain);
		$this->assertSame('orders', $byId['one sale']->context);
		$this->assertSame('%d sales', $byId['one sale']->plural);
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
			function __p($context, $x) { return $x; }
			__('real');
			PHP;

		$scanner = new PhpScanner([$this->write('a.php', $code)]);

		$this->assertSame(['real'], $this->ids($scanner->scan()));
		$this->assertSame([], $scanner->warnings());
	}

	public function testWarnsOnNonLiteralArguments(): void
	{
		$code = <<<'PHP'
			<?php

			__($dynamic);
			__d($domain, 'x');
			__n('one', $plural, 2);
			__p($context, 'contextual');
			__();
			PHP;

		$scanner = new PhpScanner([$this->write('a.php', $code)]);
		$messages = $scanner->scan();
		$warnings = implode("\n", $scanner->warnings());

		$this->assertSame([], $messages);
		$this->assertStringContainsString('Non-literal message id', $warnings);
		$this->assertStringContainsString('Non-literal domain', $warnings);
		$this->assertStringContainsString('Non-literal context', $warnings);
		$this->assertStringContainsString('Non-literal plural', $warnings);
	}

	public function testIgnoresBareNameWithoutCall(): void
	{
		$scanner = new PhpScanner([$this->write('a.php', "<?php\n\$x = __ . 'tail';\n__('real');\n")]);

		$this->assertSame(['real'], $this->ids($scanner->scan()));
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

	public function testRecordsFileAndLineOfEachCall(): void
	{
		$file = $this->write('a.php', "<?php\n\n__('A');\n");

		$this->assertSame(["{$file}:3"], new PhpScanner([$file])->scan()[0]->locations);
	}

	public function testKeepsBackslashSequencesInSingleQuotedLiterals(): void
	{
		$scanner = new PhpScanner([$this->write('a.php', "<?php\n__('Line\\nBreak');\n")]);

		$this->assertSame(['Line\\nBreak'], $this->ids($scanner->scan()));
	}

	public function testWarnsAboutTheArgumentThatIsNotLiteral(): void
	{
		$scanner = new PhpScanner([$this->write('a.php', "<?php\n__p(\$contexts['menu'], 'Open');\n")]);

		$this->assertSame([], $scanner->scan());
		$this->assertCount(1, $scanner->warnings());
		$this->assertStringStartsWith('Non-literal context', $scanner->warnings()[0]);
	}

	public function testScansFileRootsInSortedOrderAndSkipsMissingRoots(): void
	{
		$z = $this->write('z.php', "<?php\n__('Z');\n");
		$a = $this->write('a.php', "<?php\n__('A');\n");
		$scanner = new PhpScanner([$this->tmpDir() . '/missing', $z, $a]);

		$this->assertSame(['A', 'Z'], $this->ids($scanner->scan()));
	}

	public function testMatchesExtensionsCaseInsensitivelyAndSkipsBrokenLinks(): void
	{
		$this->write('src/UPPER.PHP', "<?php\n__('Upper');\n");
		symlink($this->tmpDir() . '/nowhere.php', $this->tmpDir() . '/src/dead.php');
		$scanner = new PhpScanner([$this->tmpDir() . '/src']);

		$this->assertSame(['Upper'], $this->ids($scanner->scan()));
		$this->assertSame([], $scanner->warnings());
	}

	public function testSkipsUnreadableRoots(): void
	{
		$scanner = new PhpScanner([$this->tmpDir() . '/does-not-exist']);

		$this->assertSame([], $scanner->scan());
	}
}
