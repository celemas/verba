<?php

declare(strict_types=1);

namespace Celema\Verba\Tests;

use Celema\Verba\Plurals;
use PHPUnit\Framework\Attributes\DataProvider;

class PluralsTest extends TestCase
{
	#[DataProvider('provideForms')]
	public function testForms(string $locale, int $expected): void
	{
		$this->assertSame($expected, Plurals::forms($locale));
	}

	/**
	 * @return list<array{string, int}>
	 */
	public static function provideForms(): array
	{
		return [
			['en', 2],
			['de', 2],
			['fr', 2],
			['pt_br', 2],
			['ja', 1],
			['ru', 3],
			['pl', 3],
			['cs', 3],
			['ar', 6],
		];
	}

	#[DataProvider('provideRule')]
	#[DataProvider('provideManyFormRule')]
	public function testRule(string $locale, int $n, int $expected): void
	{
		$this->assertSame($expected, Plurals::rule($locale)($n));
	}

	/**
	 * @return list<array{string, int, int}>
	 */
	public static function provideRule(): array
	{
		return [
			// default two-form: n !== 1
			['en', 1, 0],
			['en', 0, 1],
			['en', 2, 1],
			['de', 1, 0],
			['de', 5, 1],
			['de-CH', 1, 0],
			// French / Brazilian Portuguese: n > 1
			['fr', 0, 0],
			['fr', 1, 0],
			['fr', 2, 1],
			['pt_br', 0, 0],
			['pt_br', 1, 0],
			['pt_br', 2, 1],
			['pt-BR', 3, 1],
			['pt-BR', 0, 0],
			['PT_BR', 0, 0],
			['RU', 5, 2],
			// single form
			['ja', 5, 0],
			['zh', 1, 0],
		];
	}

	/**
	 * @return list<array{string, int, int}>
	 */
	public static function provideManyFormRule(): array
	{
		return [
			// Russian family
			['ru', 1, 0],
			['ru', 21, 0],
			['ru', 2, 1],
			['ru', 22, 1],
			['ru', 5, 2],
			['ru', 11, 2],
			['ru', 12, 2],
			['ru', 23, 1],
			['ru', 24, 1],
			['ru', 101, 0],
			['ru', 111, 2],
			['ru', 112, 2],
			['ru', 114, 2],
			['ru', 122, 1],
			['ru', 8712, 2],
			['ru', 8714, 2],
			['ru', 9014, 2],
			['uk', 3, 1],
			['be', 25, 2],
			// Polish
			['pl', 1, 0],
			['pl', 2, 1],
			['pl', 22, 1],
			['pl', 5, 2],
			['pl', 10, 2],
			['pl', 12, 2],
			['pl', 21, 2],
			['pl', 72, 1],
			['pl', 23, 1],
			['pl', 24, 1],
			['pl', 25, 2],
			['pl', 102, 1],
			['pl', 112, 2],
			['pl', 114, 2],
			['pl', 8712, 2],
			['pl', 8714, 2],
			['pl', 9014, 2],
			// Czech / Slovak
			['cs', 1, 0],
			['cs', 2, 1],
			['cs', 5, 2],
			['sk', 4, 1],
			// Arabic
			['ar', 0, 0],
			['ar', 1, 1],
			['ar', 2, 2],
			['ar', 3, 3],
			['ar', 10, 3],
			['ar', 11, 4],
			['ar', 25, 4],
			['ar', 99, 4],
			['ar', 100, 5],
			['ar', 102, 5],
			['ar', 103, 3],
			['ar', 110, 3],
			['ar', 111, 4],
			['ar', 9910, 3],
			['ar', 9911, 4],
		];
	}
}
