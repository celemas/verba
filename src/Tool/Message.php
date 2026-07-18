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
}
