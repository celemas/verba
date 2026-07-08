<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests\Tool;

use Celemas\Verba\Tests\TestCase;
use Celemas\Verba\Tool\FrontendScanner;
use Celemas\Verba\Tool\Message;

class FrontendScannerTest extends TestCase
{
	/**
	 * @return list<string>
	 */
	private function ids(FrontendScanner $scanner): array
	{
		return array_map(static fn(Message $m): string => $m->id, $scanner->scan());
	}

	private function scanOne(string $name, string $code): FrontendScanner
	{
		return new FrontendScanner([$this->write($name, $code)]);
	}

	public function testExtractsAllCallForms(): void
	{
		$code = <<<'JS'
			__ ('Spaced');
			__n('one', 'many', count);
			__d('shop', 'B');
			__dn('shop', 'o', 'm', count);
			JS;

		$scanner = $this->scanOne('a.js', $code);
		$byId = [];

		foreach ($scanner->scan() as $message) {
			$byId[$message->id] = $message;
		}

		$this->assertArrayHasKey('Spaced', $byId);
		$this->assertNull($byId['one']->domain);
		$this->assertSame('many', $byId['one']->plural);
		$this->assertSame('shop', $byId['B']->domain);
		$this->assertSame('shop', $byId['o']->domain);
		$this->assertSame('m', $byId['o']->plural);
	}

	public function testSkipsStringsAndComments(): void
	{
		$code = <<<'JS'
			const s = "__('inString')";
			// __('lineComment')
			/* __('blockComment') */
			__('Real');
			JS;

		$this->assertSame(['Real'], $this->ids($this->scanOne('a.js', $code)));
	}

	public function testSkipsTemplateLiteralsWithBraces(): void
	{
		$code = <<<'JS'
			const t = `__('inTemplate') ${ {a: 1} } more ${ "}" }`;
			__('Real');
			JS;

		$this->assertSame(['Real'], $this->ids($this->scanOne('a.js', $code)));
	}

	public function testMemberAndDigitPrefixedAreNotCalls(): void
	{
		$code = <<<'JS'
			obj.__('member');
			9__('digit');
			__('Real');
			JS;

		$this->assertSame(['Real'], $this->ids($this->scanOne('a.js', $code)));
	}

	public function testParsesNestedArgumentStructures(): void
	{
		$this->assertSame(
			['Nested'],
			$this->ids($this->scanOne('a.js', "__('Nested', foo(1, 2), [3, 4]);\n")),
		);
	}

	public function testDecodesEscapes(): void
	{
		$code = <<<'JS'
			__("Tab\tEnd");
			__("New\nLine");
			__("Ret\rX");
			__('It\'s');
			__("Quote\"x");
			__('Back\\slash');
			JS;

		$ids = $this->ids($this->scanOne('a.js', $code));

		$this->assertContains("Tab\tEnd", $ids);
		$this->assertContains("New\nLine", $ids);
		$this->assertContains("Ret\rX", $ids);
		$this->assertContains("It's", $ids);
		$this->assertContains('Quote"x', $ids);
		$this->assertContains('Back\\slash', $ids);
	}

	public function testTemplateLiteralArguments(): void
	{
		$code = <<<'JS'
			__(`plain`);
			__(`hi ${x}`);
			JS;

		$scanner = $this->scanOne('a.js', $code);

		$this->assertSame(['plain'], $this->ids($scanner));
		$this->assertStringContainsString('Non-literal message id', implode("\n", $scanner->warnings()));
	}

	public function testWarnsOnNonLiteralArguments(): void
	{
		$scanner = $this->scanOne('a.js', "__(\$dyn);\n__('a' + b);\n__();\n");

		$this->assertSame([], $scanner->scan());
		$this->assertCount(3, $scanner->warnings());
	}

	public function testHandlesUnterminatedRegions(): void
	{
		$this->assertSame([], $this->scanOne('str.js', 'const x = "no end')->scan());
		$this->assertSame([], $this->scanOne('tmpl.js', 'const t = `${')->scan());
		$this->assertSame(['A'], $this->ids($this->scanOne('line.js', "__('A'); // no newline")));
		$this->assertSame(['A'], $this->ids($this->scanOne('block.js', "__('A'); /* no end")));
		$this->assertSame(['A'], $this->ids($this->scanOne('html.js', "__('A'); <!-- no end")));
		$this->assertSame([], $this->scanOne('call.js', "__('A'")->scan());
	}

	public function testIgnoresCallNameNotFollowedByParen(): void
	{
		$code = <<<'JS'
			const __ = 5;
			let __n = 1;
			__('Real');
			JS;

		$this->assertSame(['Real'], $this->ids($this->scanOne('a.js', $code)));
	}

	public function testSvelteExtractsScriptAndMarkupButSkipsLiteralsAndComments(): void
	{
		$code = <<<'SVELTE'
			<script>
			  __('Script');
			</script>
			<button title="Delete">{__('Brace')}</button>
			<!-- __('CommentSkip') -->
			SVELTE;

		$ids = $this->ids($this->scanOne('c.svelte', $code));

		$this->assertContains('Script', $ids);
		$this->assertContains('Brace', $ids);
		$this->assertNotContains('Delete', $ids);
		$this->assertNotContains('CommentSkip', $ids);
	}

	public function testVueTreatsAttributesAsExpressionsButScriptStringsAsStrings(): void
	{
		$code = <<<'VUE'
			<template>
			  <button :title="__('AttrCall')">{{ __('Mustache') }}</button>
			</template>
			<script setup>
			const s = "__('scriptString')";
			__('ScriptReal');
			</script>
			VUE;

		$ids = $this->ids($this->scanOne('d.vue', $code));

		$this->assertContains('AttrCall', $ids);
		$this->assertContains('Mustache', $ids);
		$this->assertContains('ScriptReal', $ids);
		$this->assertNotContains('scriptString', $ids);
	}

	public function testScansDirectoryAcrossDialectsAndSkipsForeignFiles(): void
	{
		$this->write('app/a.jsx', "__('Jsx');\n");
		$this->write('app/b.tsx', "__('Tsx');\n");
		$this->write('app/c.ts', "__('Ts');\n");
		$this->write('app/styles.css', "__('Css');\n");

		$ids = $this->ids(new FrontendScanner([$this->tmpDir() . '/app']));
		sort($ids);

		$this->assertSame(['Jsx', 'Ts', 'Tsx'], $ids);
	}
}
