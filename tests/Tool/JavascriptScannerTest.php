<?php

declare(strict_types=1);

namespace Celema\Verba\Tests\Tool;

use Celema\Verba\Tests\TestCase;
use Celema\Verba\Tool\JavascriptScanner;
use Celema\Verba\Tool\Message;

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
			__p('menu', 'Open');
			__np('inventory', 'result', 'results', count);
			__dp('shop', 'button', 'Buy');
			__dnp('shop', 'orders', 'sale', 'sales', count);
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
		$this->assertSame('menu', $byId['Open']->context);
		$this->assertSame('inventory', $byId['result']->context);
		$this->assertSame('results', $byId['result']->plural);
		$this->assertSame('button', $byId['Buy']->context);
		$this->assertSame('shop', $byId['Buy']->domain);
		$this->assertSame('orders', $byId['sale']->context);
		$this->assertSame('sales', $byId['sale']->plural);
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

	public function testAllowsCommentsAroundCallsAndLiteralArguments(): void
	{
		$code = <<<'JS'
			__ /* call */ (
			  /* before */ 'Before' /* ) , after */
			);
			__n('one' /* ) */, /* before */ 'many', count);
			__(
			  // translator note
			  'Line'
			);
			JS;

		$scanner = $this->scanOne('a.js', $code);

		$this->assertSame(['Before', 'one', 'Line'], $this->ids($scanner));
		$this->assertSame([], $scanner->warnings());
	}

	public function testSkipsTemplateLiteralsWithBraces(): void
	{
		$code = <<<'JS'
			const t = `__('inTemplate') ${ {a: 1} } more ${ "}" }`;
			const escaped = `\${__('escapedExpression')} \` __('rawText')`;
			__('Real');
			JS;

		$this->assertSame(['Real'], $this->ids($this->scanOne('a.js', $code)));
	}

	public function testExtractsCallsFromTemplateInterpolations(): void
	{
		$code = <<<'JS'
			const t = `${/}/.test('}') ? /* } */ __('Inside') : ''} ${
			  // }
			  __('LineInside')
			} ${`nested ${__('Deep')}`}`;
			__('After');
			JS;

		$this->assertSame(
			['Inside', 'LineInside', 'Deep', 'After'],
			$this->ids($this->scanOne('a.js', $code)),
		);
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

	public function testSkipsFunctionDeclarations(): void
	{
		$code = <<<'TS'
			function __(id) { return id; }
			export function __n(one, many, n) { return one; }
			function* __d(domain, id) { yield id; }
			declare function __dn(domain, one, many, n): string;
			function __p(context, id) { return id; }
			function __np(context, one, many, n) { return one; }
			function __dp(domain, context, id) { return id; }
			function __dnp(domain, context, one, many, n) { return one; }
			__('Real');
			TS;

		$scanner = $this->scanOne('a.ts', $code);

		$this->assertSame(['Real'], $this->ids($scanner));
		$this->assertSame([], $scanner->warnings());
	}

	public function testParsesNestedArgumentStructures(): void
	{
		$this->assertSame(
			['Nested'],
			$this->ids($this->scanOne('a.js', "__('Nested', foo(1, 2), [3, 4], /[),]/);\n")),
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
		$scanner = $this->scanOne(
			'a.js',
			"__(\$dyn);\n__('a' + b);\n__p(context, 'x');\n__();\n",
		);

		$this->assertSame([], $scanner->scan());
		$this->assertCount(4, $scanner->warnings());
		$this->assertStringContainsString(
			'Non-literal context',
			implode("\n", $scanner->warnings()),
		);
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
