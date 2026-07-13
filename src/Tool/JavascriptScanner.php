<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * Extracts `__`, `__n`, `__d`, and `__dn` calls from JavaScript source across a
 * range of dialects — `.js`, `.ts`, `.jsx`, `.tsx`, `.svelte`, and `.vue` — with
 * one shared character lexer that skips strings, template literals, and
 * comments. Only literal string arguments are captured.
 *
 * The JS dialects and `.svelte` are lexed whole (quoted strings are skipped, so
 * a literal attribute like `title="Delete"` is not mistaken for a call). In
 * `.vue`, `<script>` blocks are lexed the same way while the template treats
 * quoted attribute values as expression context, so `:title="__('Save')"` is
 * found. HTML comments are always skipped. Template-literal interpolations
 * are scanned as code while their raw text is skipped.
 *
 * @api
 */
final class JavascriptScanner extends FileScanner
{
	#[\Override]
	protected function extensions(): array
	{
		return ['js', 'ts', 'jsx', 'tsx', 'svelte', 'vue'];
	}

	#[\Override]
	protected function scanCode(string $code, string $file): void
	{
		if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'vue') {
			$this->scanVue($code, $file);

			return;
		}

		$this->collect($code, $file, 0, false);
	}

	private function scanVue(string $code, string $file): void
	{
		$offset = 0;
		preg_match_all(
			'#<script\b[^>]*>(.*?)</script>#is',
			$code,
			$matches,
			PREG_OFFSET_CAPTURE | PREG_SET_ORDER,
		);

		foreach ($matches as $match) {
			$whole = $match[0][0];
			$start = $match[0][1];
			$inner = $match[1][0];
			$innerStart = $match[1][1];

			$this->collect(
				substr($code, $offset, $start - $offset),
				$file,
				$this->lineAt($code, $offset),
				true,
			);
			$this->collect($inner, $file, $this->lineAt($code, $innerStart), false);
			$offset = $start + strlen($whole);
		}

		$this->collect(substr($code, $offset), $file, $this->lineAt($code, $offset), true);
	}

	private function lineAt(string $code, int $offset): int
	{
		return substr_count($code, "\n", 0, $offset);
	}

	/**
	 * Walks $code finding calls. String literals are skipped unless $transparent
	 * (a markup region where quotes delimit attributes, not JS strings).
	 */
	private function collect(string $code, string $file, int $lineBase, bool $transparent): void
	{
		$length = strlen($code);
		$i = 0;

		while ($i < $length) {
			$char = $code[$i];

			if (substr($code, $i, 4) === '<!--') {
				$i = $this->skipTo($code, $i + 4, '-->');

				continue;
			}

			if (!$transparent && substr($code, $i, 2) === '//') {
				$i = $this->skipTo($code, $i + 2, "\n");

				continue;
			}

			if (!$transparent && substr($code, $i, 2) === '/*') {
				$i = $this->skipTo($code, $i + 2, '*/');

				continue;
			}

			if (!$transparent && $char === '/' && $this->startsRegex($code, $i)) {
				$i = $this->skipRegex($code, $i);

				continue;
			}

			if ($char === '`') {
				$i = $this->scanTemplate($code, $i, $file, $lineBase);

				continue;
			}

			if (!$transparent && ($char === '"' || $char === "'")) {
				$i = $this->skipString($code, $i, $char);

				continue;
			}

			if (!$this->isNameStart($char)) {
				$i++;

				continue;
			}

			$i = $this->identifier($code, $i, $file, $lineBase);
		}
	}

	/**
	 * Reads the identifier at $start and, when it is one of the call names used
	 * as a function, records it. Returns the index to continue from.
	 */
	private function identifier(string $code, int $start, string $file, int $lineBase): int
	{
		$length = strlen($code);
		$end = $start;

		while ($end < $length && $this->isNamePart($code[$end])) {
			$end++;
		}

		$name = substr($code, $start, $end - $start);
		$prev = $start > 0 ? $code[$start - 1] : '';

		if (!array_key_exists($name, self::CALLS) || $prev === '.' || $this->isNamePart($prev)) {
			return $end;
		}

		if ($this->isFunctionDeclaration($code, $start)) {
			return $end;
		}

		$open = $this->skipTrivia($code, $end);

		if ($open >= $length || $code[$open] !== '(') {
			return $end;
		}

		$args = $this->arguments($code, $open);
		$this->emit($name, $args, $file . ':' . ($lineBase + substr_count($code, "\n", 0, $start) + 1));

		return $end;
	}

	private function isFunctionDeclaration(string $code, int $start): bool
	{
		return preg_match('/\bfunction\s*\*?\s*$/', substr($code, 0, $start)) === 1;
	}

	/**
	 * @return list<?string>
	 */
	private function arguments(string $code, int $open): array
	{
		$length = strlen($code);
		$i = $open + 1;
		$start = $i;
		$depth = 0;
		$args = [];

		while ($i < $length) {
			$char = $code[$i];

			$after = $this->skipRegion($code, $i);

			if ($after !== null) {
				$i = $after;

				continue;
			}

			if ($char === '(' || $char === '[' || $char === '{') {
				$depth++;
				$i++;

				continue;
			}

			if ($char === ')' && $depth === 0) {
				$args[] = $this->literal(substr($code, $start, $i - $start));

				return $args;
			}

			if ($char === ')' || $char === ']' || $char === '}') {
				$depth--;
				$i++;

				continue;
			}

			if ($char === ',' && $depth === 0) {
				$args[] = $this->literal(substr($code, $start, $i - $start));
				$i++;
				$start = $i;

				continue;
			}

			$i++;
		}

		return $args;
	}

	private function literal(string $raw): ?string
	{
		$length = strlen($raw);
		$i = $this->skipTrivia($raw, 0);

		if ($i >= $length) {
			return null;
		}

		$quote = $raw[$i];

		if ($quote !== '"' && $quote !== "'" && $quote !== '`') {
			return null;
		}

		$value = '';

		for ($i++; $i < $length; $i++) {
			$char = $raw[$i];

			if ($char === '\\') {
				$value .= $this->unescape($raw, $i);

				continue;
			}

			if ($quote === '`' && $char === '$' && ($raw[$i + 1] ?? '') === '{') {
				return null;
			}

			if ($char === $quote) {
				return $this->skipTrivia($raw, $i + 1) === $length ? $value : null;
			}

			$value .= $char;
		}

		// The argument parser only passes balanced segments, so a quoted arg
		// always closes above.
		// @codeCoverageIgnoreStart
		return null;

		// @codeCoverageIgnoreEnd
	}

	private function unescape(string $raw, int &$i): string
	{
		$i++;
		$char = $raw[$i] ?? '';

		return match ($char) {
			'b' => "\b",
			'f' => "\f",
			'n' => "\n",
			'r' => "\r",
			't' => "\t",
			'v' => "\v",
			'u' => $this->unicodeEscape($raw, $i),
			'x' => $this->hexEscape($raw, $i),
			default => $char,
		};
	}

	private function unicodeEscape(string $raw, int &$i): string
	{
		if (($raw[$i + 1] ?? '') === '{') {
			$end = strpos($raw, '}', $i + 2);

			if ($end === false) {
				return 'u';
			}

			$hex = substr($raw, $i + 2, $end - $i - 2);

			if ($hex === '' || !ctype_xdigit($hex)) {
				return 'u';
			}

			$i = $end;

			return $this->htmlCodepoint($hex);
		}

		$hex = substr($raw, $i + 1, 4);

		if (strlen($hex) !== 4 || !ctype_xdigit($hex)) {
			return 'u';
		}

		$escape = '\\u' . $hex;
		$i += 4;

		if ($this->highSurrogate($hex)) {
			$low = substr($raw, $i + 3, 4);

			if (
				($raw[$i + 1] ?? '') === '\\'
				&& ($raw[$i + 2] ?? '') === 'u'
				&& strlen($low) === 4
				&& ctype_xdigit($low)
				&& $this->lowSurrogate($low)
			) {
				$escape .= '\\u' . $low;
				$i += 6;
			}
		}

		/** @var string|null $decoded */
		$decoded = json_decode('"' . $escape . '"');

		return $decoded ?? '';
	}

	private function htmlCodepoint(string $hex): string
	{
		// Braced escapes already give one full codepoint. Fixed-width \uXXXX
		// escapes use json_decode above so PHP handles surrogate pairs for us.
		$entity = '&#x' . $hex . ';';
		$decoded = html_entity_decode($entity, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return $decoded === $entity ? '' : $decoded;
	}

	private function highSurrogate(string $hex): bool
	{
		$code = hexdec($hex);

		return $code >= 0xD800 && $code <= 0xDBFF;
	}

	private function lowSurrogate(string $hex): bool
	{
		$code = hexdec($hex);

		return $code >= 0xDC00 && $code <= 0xDFFF;
	}

	private function hexEscape(string $raw, int &$i): string
	{
		$hex = substr($raw, $i + 1, 2);

		if (strlen($hex) !== 2 || !ctype_xdigit($hex)) {
			return 'x';
		}

		$i += 2;

		return chr(hexdec($hex));
	}

	private function scanTemplate(string $code, int $i, string $file, int $lineBase): int
	{
		$length = strlen($code);

		for ($i++; $i < $length; $i++) {
			$char = $code[$i];

			if ($char === '\\') {
				$i++;

				continue;
			}

			if ($char === '$' && ($code[$i + 1] ?? '') === '{') {
				$start = $i + 2;
				$after = $this->skipBraces($code, $i + 1);
				$end = ($code[$after - 1] ?? '') === '}' ? $after - 1 : $after;

				$this->collect(
					substr($code, $start, $end - $start),
					$file,
					$lineBase + substr_count($code, "\n", 0, $start),
					false,
				);
				$i = $after - 1;

				continue;
			}

			if ($char === '`') {
				return $i + 1;
			}
		}

		return $length;
	}

	private function skipString(string $code, int $i, string $quote): int
	{
		$length = strlen($code);

		for ($i++; $i < $length; $i++) {
			$char = $code[$i];

			if ($char === '\\') {
				$i++;

				continue;
			}

			if ($quote === '`' && $char === '$' && ($code[$i + 1] ?? '') === '{') {
				$i = $this->skipBraces($code, $i + 1) - 1;

				continue;
			}

			if ($char === $quote) {
				return $i + 1;
			}
		}

		return $length;
	}

	private function skipRegion(string $code, int $i): ?int
	{
		$pair = substr($code, $i, 2);

		if ($pair === '//') {
			return $this->skipTo($code, $i + 2, "\n");
		}

		if ($pair === '/*') {
			return $this->skipTo($code, $i + 2, '*/');
		}

		$char = $code[$i];

		if ($char === '/' && $this->startsRegex($code, $i)) {
			return $this->skipRegex($code, $i);
		}

		return $char === '"' || $char === "'" || $char === '`'
			? $this->skipString($code, $i, $char)
			: null;
	}

	private function skipBraces(string $code, int $i): int
	{
		$length = strlen($code);
		$depth = 0;

		while ($i < $length) {
			$char = $code[$i];

			$after = $this->skipRegion($code, $i);

			if ($after !== null) {
				$i = $after;

				continue;
			}

			if ($char === '{') {
				$depth++;
				$i++;

				continue;
			}

			if ($char === '}') {
				$depth--;
				$i++;

				if ($depth === 0) {
					return $i;
				}

				continue;
			}

			$i++;
		}

		return $length;
	}

	private function startsRegex(string $code, int $i): bool
	{
		for ($j = $i - 1; $j >= 0; $j--) {
			$prev = $code[$j];

			if ($prev === ' ' || $prev === "\t" || $prev === "\n" || $prev === "\r") {
				continue;
			}

			if (in_array(
				$prev,
				['(', '[', '{', '=', ',', ':', ';', '!', '&', '|', '?', '+', '-', '*', '~', '%', '^', '<', '>'],
				true,
			)) {
				return true;
			}

			if ($this->isNamePart($prev)) {
				$end = $j + 1;

				while ($j >= 0 && $this->isNamePart($code[$j])) {
					$j--;
				}

				return in_array(
					substr($code, $j + 1, $end - $j - 1),
					[
						'case',
						'delete',
						'instanceof',
						'of',
						'return',
						'throw',
						'typeof',
						'void',
						'yield',
					],
					true,
				);
			}

			return false;
		}

		return true;
	}

	private function skipRegex(string $code, int $i): int
	{
		$length = strlen($code);
		$inClass = false;

		for ($i++; $i < $length; $i++) {
			$char = $code[$i];

			if ($char === '\\') {
				$i++;

				continue;
			}

			if ($char === '[') {
				$inClass = true;

				continue;
			}

			if ($char === ']') {
				$inClass = false;

				continue;
			}

			if (($char === "\n" || $char === "\r") && !$inClass) {
				return $i;
			}

			if ($char === '/' && !$inClass) {
				$i++;

				while ($i < $length && ctype_alpha($code[$i])) {
					$i++;
				}

				return $i;
			}
		}

		return $length;
	}

	private function skipTo(string $code, int $from, string $needle): int
	{
		$at = strpos($code, $needle, $from);

		return $at === false ? strlen($code) : $at + strlen($needle);
	}

	private function skipTrivia(string $code, int $i): int
	{
		$length = strlen($code);

		while ($i < $length) {
			$i = $this->skipSpace($code, $i);

			if (substr($code, $i, 2) === '//') {
				$i = $this->skipTo($code, $i + 2, "\n");

				continue;
			}

			if (substr($code, $i, 2) === '/*') {
				$i = $this->skipTo($code, $i + 2, '*/');

				continue;
			}

			return $i;
		}

		return $i;
	}

	private function skipSpace(string $code, int $i): int
	{
		$length = strlen($code);

		while (
			$i < $length
			&& ($code[$i] === ' ' || $code[$i] === "\t" || $code[$i] === "\n" || $code[$i] === "\r")
		) {
			$i++;
		}

		return $i;
	}

	private function isNameStart(string $char): bool
	{
		return $char === '_' || $char === '$' || ctype_alpha($char);
	}

	private function isNamePart(string $char): bool
	{
		return $this->isNameStart($char) || ctype_digit($char);
	}
}
