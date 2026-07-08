<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * A translatable message found in source: its id, optional plural source, the
 * domain it targets (null for a bare `__`/`__n` call), and where it occurs.
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
	) {}
}
