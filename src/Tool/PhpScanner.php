<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

use PhpToken;

/**
 * Extracts `__`, `__n`, `__d`, and `__dn` calls from PHP source (including
 * Boiler templates) by walking the token stream — no parser, no regex. Only
 * literal string arguments are captured; a dynamic id, domain, or plural is
 * reported as a warning and skipped.
 *
 * @api
 */
final class PhpScanner extends FileScanner
{
	#[\Override]
	protected function extensions(): array
	{
		return ['php'];
	}

	#[\Override]
	protected function scanCode(string $code, string $file): void
	{
		$tokens = PhpToken::tokenize($code);
		$count = count($tokens);

		for ($i = 0; $i < $count; $i++) {
			$token = $tokens[$i];
			$name = $this->callName($token);

			if ($name === null) {
				continue;
			}

			$before = $this->step($tokens, $i, -1);

			if (
				$before !== null
				&& $tokens[$before]->is([
					T_OBJECT_OPERATOR,
					T_NULLSAFE_OBJECT_OPERATOR,
					T_DOUBLE_COLON,
					T_FUNCTION,
				])
			) {
				continue;
			}

			$open = $this->step($tokens, $i, 1);

			if ($open === null || $tokens[$open]->text !== '(') {
				continue;
			}

			$args = $this->arguments($tokens, $open);
			$this->emit($name, $args, $file . ':' . $token->line);
		}
	}

	private function callName(PhpToken $symbol): ?string
	{
		if ($symbol->is(T_STRING) && array_key_exists($symbol->text, self::CALLS)) {
			return $symbol->text;
		}

		if ($symbol->is(T_NAME_FULLY_QUALIFIED)) {
			$name = ltrim($symbol->text, '\\');

			if (array_key_exists($name, self::CALLS)) {
				return $name;
			}
		}

		return null;
	}

	/**
	 * Index of the next significant token from $from in direction $dir.
	 *
	 * @param list<PhpToken> $tokens
	 */
	private function step(array $tokens, int $from, int $dir): ?int
	{
		for ($i = $from + $dir; array_key_exists($i, $tokens); $i += $dir) {
			if (!$tokens[$i]->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Parses the argument list starting at the opening paren. Each argument is
	 * its literal string value, or null when it is not a single string literal.
	 *
	 * @param list<PhpToken> $tokens
	 * @return list<?string>
	 */
	private function arguments(array $tokens, int $open): array
	{
		$args = [];
		$current = [];
		$depth = 0;
		$count = count($tokens);

		for ($i = $open; $i < $count; $i++) {
			$token = $tokens[$i];
			$text = $token->text;

			if ($token->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) {
				continue;
			}

			if ($text === '(' || $text === '[' || $text === '{') {
				if ($depth > 0) {
					$current[] = $token;
				}

				$depth++;

				continue;
			}

			if ($text === ')' || $text === ']' || $text === '}') {
				$depth--;

				if ($depth === 0) {
					if ($current !== [] || $args !== []) {
						$args[] = $this->literal($current);
					}

					return $args;
				}

				$current[] = $token;

				continue;
			}

			if ($text === ',' && $depth === 1) {
				$args[] = $this->literal($current);
				$current = [];

				continue;
			}

			$current[] = $token;
		}

		return $args;
	}

	/**
	 * @param list<PhpToken> $tokens
	 */
	private function literal(array $tokens): ?string
	{
		if (count($tokens) !== 1 || !$tokens[0]->is(T_CONSTANT_ENCAPSED_STRING)) {
			return null;
		}

		$text = $tokens[0]->text;
		$inner = substr($text, 1, -1);

		if ($text[0] === "'") {
			return str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);
		}

		return stripcslashes($inner);
	}
}
