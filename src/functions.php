<?php

declare(strict_types=1);

use Celemas\Verba\Verba;

/**
 * Translate a message through the active domain cascade.
 *
 * @param string|int|float|array<array-key, string|int|float> ...$args
 *
 * @api
 */
function __(string $message, string|int|float|array ...$args): string
{
	return Verba::translate($message, Verba::args($args));
}

/**
 * Translate a pluralized message, choosing the form for $n.
 *
 * @param string|int|float|array<array-key, string|int|float> ...$args
 *
 * @api
 */
function __n(string $one, string $many, int $n, string|int|float|array ...$args): string
{
	return Verba::translatePlural($one, $many, $n, Verba::args($args));
}

/**
 * Translate a message from a specific domain.
 *
 * @param string|int|float|array<array-key, string|int|float> ...$args
 *
 * @api
 */
function __d(string $domain, string $message, string|int|float|array ...$args): string
{
	return Verba::translateDomain($domain, $message, Verba::args($args));
}

/**
 * Translate a pluralized message from a specific domain.
 *
 * @param string|int|float|array<array-key, string|int|float> ...$args
 *
 * @api
 */
function __dn(
	string $domain,
	string $one,
	string $many,
	int $n,
	string|int|float|array ...$args,
): string {
	return Verba::translateDomainPlural($domain, $one, $many, $n, Verba::args($args));
}
