<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * Decodes escape sequences from JavaScript string literals.
 *
 * @internal
 */
final class JavascriptEscape
{
	/**
	 * Decodes the escape at $offset and advances it to the final consumed byte.
	 */
	public static function decode(string $raw, int &$offset): string
	{
		$offset++;
		$char = $raw[$offset] ?? '';

		return match ($char) {
			'b' => "\b",
			'f' => "\f",
			'n' => "\n",
			'r' => "\r",
			't' => "\t",
			'v' => "\v",
			'u' => self::unicode($raw, $offset),
			'x' => self::hex($raw, $offset),
			default => $char,
		};
	}

	private static function unicode(string $raw, int &$offset): string
	{
		if (($raw[$offset + 1] ?? '') === '{') {
			$end = strpos($raw, '}', $offset + 2);

			if ($end === false) {
				return 'u';
			}

			$hex = substr($raw, $offset + 2, $end - $offset - 2);

			if ($hex === '' || !ctype_xdigit($hex)) {
				return 'u';
			}

			$offset = $end;

			return self::codepoint($hex);
		}

		$hex = substr($raw, $offset + 1, 4);

		if (strlen($hex) !== 4 || !ctype_xdigit($hex)) {
			return 'u';
		}

		$escape = '\\u' . $hex;
		$offset += 4;

		if (self::highSurrogate($hex)) {
			$low = substr($raw, $offset + 3, 4);

			if (
				($raw[$offset + 1] ?? '') === '\\'
				&& ($raw[$offset + 2] ?? '') === 'u'
				&& strlen($low) === 4
				&& ctype_xdigit($low)
				&& self::lowSurrogate($low)
			) {
				$escape .= '\\u' . $low;
				$offset += 6;
			}
		}

		/** @var string|null $decoded */
		$decoded = json_decode('"' . $escape . '"');

		return $decoded ?? '';
	}

	private static function codepoint(string $hex): string
	{
		// Braced escapes already hold a full codepoint; fixed-width escapes use JSON for surrogates.
		$entity = '&#x' . $hex . ';';
		$decoded = html_entity_decode($entity, ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return $decoded === $entity ? '' : $decoded;
	}

	private static function highSurrogate(string $hex): bool
	{
		$code = hexdec($hex);

		return $code >= 0xD800 && $code <= 0xDBFF;
	}

	private static function lowSurrogate(string $hex): bool
	{
		$code = hexdec($hex);

		return $code >= 0xDC00 && $code <= 0xDFFF;
	}

	private static function hex(string $raw, int &$offset): string
	{
		$hex = substr($raw, $offset + 1, 2);

		if (strlen($hex) !== 2 || !ctype_xdigit($hex)) {
			return 'x';
		}

		$offset += 2;

		return chr(hexdec($hex));
	}
}
