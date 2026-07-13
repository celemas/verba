<?php

declare(strict_types=1);

namespace Celemas\Verba\Tests\Tool;

use Celemas\Verba\Tests\TestCase;
use Celemas\Verba\Tool\JavascriptScanner;
use Celemas\Verba\Tool\Message;

class JavascriptScannerTest extends TestCase
{
	/**
	 * @return list<string>
	 */
	private function ids(JavascriptScanner $scanner): array
	{
		return array_map(static fn(Message $m): string => $m->id, $scanner->scan());
	}

	private function scanOne(string $name, string $code): JavascriptScanner
	{
		return new JavascriptScanner([$this->write($name, $code)]);
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

	public function testExtractsNestedCalls(): void
	{
		$scanner = $this->scanOne('a.js', "__('Outer :inner', { inner: __('Inner') });\n");

		$this->assertSame(['Outer :inner', 'Inner'], $this->ids($scanner));
		$this->assertSame([], $scanner->warnings());
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
			__("Caf\u00e9");
			__("Smile \u{1f600}");
			__("Pair \uD83D\uDE00");
			__("Hex \x41");
			__("Controls \b\f\v");
			__("Bad brace \u{}");
			__("Bad open \u{1");
			__("Bad unicode \u0");
			__("Bad hex \xG");
			__("Bad surrogate \uD800");
			JS;

		$ids = $this->ids($this->scanOne('a.js', $code));

		$this->assertContains("Tab\tEnd", $ids);
		$this->assertContains("New\nLine", $ids);
		$this->assertContains("Ret\rX", $ids);
		$this->assertContains("It's", $ids);
		$this->assertContains('Quote"x', $ids);
		$this->assertContains('Back\\slash', $ids);
		$this->assertContains("Caf\u{00e9}", $ids);
		$this->assertContains("Smile \u{1f600}", $ids);
		$this->assertContains("Pair \u{1f600}", $ids);
		$this->assertContains('Hex A', $ids);
		$this->assertContains("Controls \b\f\v", $ids);
		$this->assertContains('Bad brace u{}', $ids);
		$this->assertContains('Bad open u{1', $ids);
		$this->assertContains('Bad unicode u0', $ids);
		$this->assertContains('Bad hex xG', $ids);
		$this->assertContains('Bad surrogate ', $ids);
	}

	public function testSkipsRegexLiterals(): void
	{
		$code = <<<'JS'
			/__("StartRegex")/;
			const r = /__("Regex")/g;
			const escaped = /\/__("Escaped")/;
			const chars = /[__("Class")]/;
			const value = count / __('Real');
			const afterParen = (count) / __('AfterParen');
			JS;

		$this->assertSame(['Real', 'AfterParen'], $this->ids($this->scanOne('a.js', $code)));
	}

	public function testSkipsUnterminatedRegexLiteral(): void
	{
		$this->assertSame([], $this->scanOne('a.js', '/__("Regex")')->scan());
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
			  <a href="https://example.test">{{ __('AfterUrl') }}</a>
			  <button :title="__('AttrCall')">{{ __('Mustache') }}</button>
			</template>
			<script setup>
			const s = "__('scriptString')";
			__('ScriptReal');
			</script>
			VUE;

		$ids = $this->ids($this->scanOne('d.vue', $code));

		$this->assertContains('AfterUrl', $ids);
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

		$ids = $this->ids(new JavascriptScanner([$this->tmpDir() . '/app']));
		sort($ids);

		$this->assertSame(['Jsx', 'Ts', 'Tsx'], $ids);
	}
}
