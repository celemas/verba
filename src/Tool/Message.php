<?php

declare(strict_types=1);

namespace Celema\Verba\Tool;

/**
 * A translatable message found in source: its id, optional plural source,
 * optional context, target domain, and locations.
 *
 * @api
 */
final class Message
{
	/**
	 * @param list<string> $locations
	 */
	public function __construct(
		public readonly ?string $domain,
		public readonly string $id,
		public readonly ?string $plural,
		public readonly array $locations,
		public readonly ?string $context = null,
	) {}

	/**
	 * Whether a catalog entry translates this message. A string always does;
	 * a plural list only translates a plural message, because singular
	 * lookups skip lists.
	 *
	 * @param string|list<string>|null $entry
	 */
	public function coveredBy(string|array|null $entry): bool
	{
		return is_string($entry) || $this->plural !== null && is_array($entry) && $entry !== [];
	}
}
