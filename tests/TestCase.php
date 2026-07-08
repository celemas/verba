<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests;

use Celemas\Verba\Verba;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * @internal
 */
class TestCase extends BaseTestCase
{
	protected function tearDown(): void
	{
		Verba::deactivate();
	}

	protected function i18n(): string
	{
		return __DIR__ . '/Fixtures/i18n';
	}
}
