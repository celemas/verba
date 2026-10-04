<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Tool;

use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\Domain;
use Celema\Verba\Tool\Extractor;
use Celema\Verba\Tool\Message;
use Celema\Verba\Tool\PhpScanner;
use Celema\Verba\Tool\Scanner;

class ExtractorTest extends TestCase
{
	private function source(): string
	{
		$code = <<<'PHP'
			<?php

			__('A');
			__('A');
			__d('app', 'B');
			__d('shop', 'S');
			__d('other', 'C');
			__n('one', 'many', 2);
			__p('menu', 'A');
			__p('menu', 'A');
			__p('state', 'A');
			__dp('shop', 'menu', 'Shop context');
			__($dyn);
			PHP;

		return $this->write('src/x.php', $code);
	}

	public function testMergesFiltersAndCollectsWarnings(): void
	{
		$file = $this->source();
		$domain = new Domain(
			'app',
			$this->tmpDir() . '/i18n',
			['de'],
			[new PhpScanner([$file])],
			default: true,
		);

		$result = new Extractor($domain)->extract();
		$ids = array_keys($result['messages']);
		sort($ids);

		$this->assertSame(['A', 'B', 'one'], $ids);
		$this->assertSame(["{$file}:3", "{$file}:4"], $result['messages']['A']->locations);
		$this->assertSame('many', $result['messages']['one']->plural);
		$this->assertSame(['menu', 'state'], array_keys($result['contexts']));
		$this->assertCount(2, $result['contexts']['menu']['A']->locations);
		$this->assertSame('menu', $result['contexts']['menu']['A']->context);
		$this->assertSame('state', $result['contexts']['state']['A']->context);
		$this->assertNotEmpty($result['warnings']);
	}

	public function testSortsMessagesAndContexts(): void
	{
		$file = $this->write(
			'src/x.php',
			"<?php\n__('b');\n__('a');\n__p('state', 'y');\n__p('menu', 'z');\n__p('menu', 'x');\n",
		);
		$domain = new Domain('app', $this->tmpDir() . '/i18n', ['de'], [new PhpScanner([$file])], default: true);

		$result = new Extractor($domain)->extract();

		$this->assertSame(['a', 'b'], array_keys($result['messages']));
		$this->assertSame(['menu', 'state'], array_keys($result['contexts']));
		$this->assertSame(['x', 'z'], array_keys($result['contexts']['menu']));
	}

	public function testMergesEveryLocationFromEachScannedMessage(): void
	{
		$scanner = new class implements Scanner {
			public function scan(): array
			{
				return [
					new Message(null, 'A', null, ['a.js:1', 'a.js:2']),
					new Message(null, 'A', null, ['b.js:3', 'b.js:4']),
					new Message(null, 'A', null, ['c.js:5']),
				];
			}

			public function warnings(): array
			{
				return [];
			}
		};
		$domain = new Domain('app', $this->tmpDir() . '/i18n', ['de'], [$scanner], default: true);

		$this->assertSame(
			['a.js:1', 'a.js:2', 'b.js:3', 'b.js:4', 'c.js:5'],
			new Extractor($domain)->extract()['messages']['A']->locations,
		);
	}

	public function testNonDefaultDomainTakesOnlyItsCalls(): void
	{
		$file = $this->source();
		$domain = new Domain('shop', $this->tmpDir() . '/i18n', ['de'], [new PhpScanner([$file])]);

		$result = new Extractor($domain)->extract();

		$this->assertSame(['S'], array_keys($result['messages']));
		$this->assertSame(['Shop context'], array_keys($result['contexts']['menu']));
	}

	public function testWarnsOnPluralConflicts(): void
	{
		$file = $this->write('src/x.php', <<<'PHP'
			<?php

			__('same');
			__n('same', 'many', 2);
			__n('other', 'many', 2);
			__n('other', 'others', 2);
			__p('menu', 'label');
			__np('menu', 'label', 'labels', 2);
			__np('button', 'label', 'labels', 2);
			PHP);
		$domain = new Domain(
			'app',
			$this->tmpDir() . '/i18n',
			['de'],
			[new PhpScanner([$file])],
			default: true,
		);

		$result = new Extractor($domain)->extract();

		$this->assertSame(
			[
				"Mixed singular and plural calls for message id 'same' at {$file}:4",
				"Conflicting plural forms for message id 'other' at {$file}:6",
				"Mixed singular and plural calls for message id 'label' in context 'menu' at {$file}:8",
			],
			$result['warnings'],
		);
		$this->assertSame('many', $result['messages']['other']->plural);
		$this->assertSame('button', $result['contexts']['button']['label']->context);
	}
}
