<?php

declare(strict_types=1);

namespace Celemas\Verba;

/**
 * Fills placeholders in a message template.
 *
 * A positional (list) args array is applied with `vsprintf`; a named
 * (associative) args array replaces `:key` tokens. An empty args array
 * leaves the template untouched, so literal `%` in named strings is safe.
 *
 * @api
 */
final class Interpolate
{
	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public static function apply(string $template, array $args): string
	{
		if ($args === []) {
			return $template;
		}

		if (array_is_list($args)) {
			return vsprintf($template, $args);
		}

		$pairs = [];

		/** @var array<array-key, string|int|float> $args */
		foreach ($args as $key => $value) {
			$pairs[':' . $key] = (string) $value;
		}

		return strtr($template, $pairs);
	}
}
