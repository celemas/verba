<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests\Tool;

use Celemas\Verba\Tests\TestCase;
use Celemas\Verba\Tool\Domain;
use Celemas\Verba\Tool\Extractor;
use Celemas\Verba\Tool\PhpScanner;

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
		$this->assertCount(2, $result['messages']['A']->locations);
		$this->assertSame('many', $result['messages']['one']->plural);
		$this->assertSame(['menu', 'state'], array_keys($result['contexts']));
		$this->assertCount(2, $result['contexts']['menu']['A']->locations);
		$this->assertSame('menu', $result['contexts']['menu']['A']->context);
		$this->assertSame('state', $result['contexts']['state']['A']->context);
		$this->assertNotEmpty($result['warnings']);
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
		$warnings = implode("\n", $result['warnings']);

		$this->assertStringContainsString('Mixed singular and plural calls', $warnings);
		$this->assertStringContainsString('Conflicting plural forms', $warnings);
		$this->assertStringContainsString("context 'menu'", $warnings);
		$this->assertSame('button', $result['contexts']['button']['label']->context);
	}
}
