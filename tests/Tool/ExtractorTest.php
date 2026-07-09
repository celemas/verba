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
		$this->assertNotEmpty($result['warnings']);
	}

	public function testNonDefaultDomainTakesOnlyItsCalls(): void
	{
		$file = $this->source();
		$domain = new Domain('shop', $this->tmpDir() . '/i18n', ['de'], [new PhpScanner([$file])]);

		$result = new Extractor($domain)->extract();

		$this->assertSame(['S'], array_keys($result['messages']));
	}

	public function testWarnsOnPluralConflicts(): void
	{
		$file = $this->write('src/x.php', <<<'PHP'
			<?php

			__('same');
			__n('same', 'many', 2);
			__n('other', 'many', 2);
			__n('other', 'others', 2);
			PHP);
		$domain = new Domain(
			'app',
			$this->tmpDir() . '/i18n',
			['de'],
			[new PhpScanner([$file])],
			default: true,
		);

		$warnings = implode("\n", new Extractor($domain)->extract()['warnings']);

		$this->assertStringContainsString('Mixed singular and plural calls', $warnings);
		$this->assertStringContainsString('Conflicting plural forms', $warnings);
	}
}
