<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Tool;

use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\Domain;

class DomainTest extends TestCase
{
	public function testFileBuildsCatalogPath(): void
	{
		$domain = new Domain('shop', '/i18n', ['de'], []);

		$this->assertSame('/i18n/shop.de.php', $domain->file('de'));
	}

	public function testOwnsExplicitDomainOnly(): void
	{
		$domain = new Domain('shop', '/i18n', [], []);

		$this->assertTrue($domain->owns('shop'));
		$this->assertFalse($domain->owns('other'));
		$this->assertFalse($domain->owns(null));
	}

	public function testDefaultDomainOwnsBareCalls(): void
	{
		$domain = new Domain('app', '/i18n', [], [], default: true);

		$this->assertTrue($domain->owns(null));
		$this->assertTrue($domain->owns('app'));
		$this->assertFalse($domain->owns('other'));
	}
}
