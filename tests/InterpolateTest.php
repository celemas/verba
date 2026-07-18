<?php

declare(strict_types=1);

namespace Celema\Verba\Tests;

use Celema\Verba\Interpolate;

class InterpolateTest extends TestCase
{
	public function testEmptyArgsLeaveTemplateUntouched(): void
	{
		$this->assertSame('100% sure', Interpolate::apply('100% sure', []));
	}

	public function testPositionalArgsUseSprintf(): void
	{
		$this->assertSame('a and 3', Interpolate::apply('%s and %d', ['a', 3]));
	}

	public function testNamedArgsReplaceTokens(): void
	{
		$this->assertSame('Hello Bob', Interpolate::apply('Hello :name', ['name' => 'Bob']));
	}

	public function testNamedArgsCastIntAndFloat(): void
	{
		$this->assertSame('5 items 1.5', Interpolate::apply(':n items :p', ['n' => 5, 'p' => 1.5]));
	}

	public function testNamedArgsLeavePercentLiteral(): void
	{
		$this->assertSame('50% y', Interpolate::apply('50% :x', ['x' => 'y']));
	}

	public function testNamedArgsPreferLongerKeys(): void
	{
		$this->assertSame('1 and 9', Interpolate::apply(':count and :countdown', [
			'count' => 1,
			'countdown' => 9,
		]));
	}
}
