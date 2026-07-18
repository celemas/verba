<?php

declare(strict_types=1);

use Celema\Verba\Verba;

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
 * Translate a message in a specific context through the active domain cascade.
 *
 * @param string|int|float|array<array-key, string|int|float> ...$args
 *
 * @api
 */
function __p(string $context, string $message, string|int|float|array ...$args): string
{
	return Verba::translateContext($context, $message, Verba::args($args));
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
 * Translate a pluralized message in a specific context.
 *
 * @param string|int|float|array<array-key, string|int|float> ...$args
 *
 * @api
 */
function __np(
	string $context,
	string $one,
	string $many,
	int $n,
	string|int|float|array ...$args,
): string {
	return Verba::translateContextPlural($context, $one, $many, $n, Verba::args($args));
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
 * Translate a contextual message from a specific domain.
 *
 * @param string|int|float|array<array-key, string|int|float> ...$args
 *
 * @api
 */
function __dp(
	string $domain,
	string $context,
	string $message,
	string|int|float|array ...$args,
): string {
	return Verba::translateDomainContext($domain, $context, $message, Verba::args($args));
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

/**
 * Translate a contextual pluralized message from a specific domain.
 *
 * @param string|int|float|array<array-key, string|int|float> ...$args
 *
 * @api
 * @mago-expect lint:excessive-parameter-list The API mirrors dnpgettext plus interpolation args.
 */
function __dnp(
	string $domain,
	string $context,
	string $one,
	string $many,
	int $n,
	string|int|float|array ...$args,
): string {
	return Verba::translateDomainContextPlural(
		$domain,
		$context,
		$one,
		$many,
		$n,
		Verba::args($args),
	);
}
