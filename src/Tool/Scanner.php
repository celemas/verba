<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * Extracts translatable messages from a body of source files.
 *
 * @api
 */
interface Scanner
{
	/**
	 * @return list<Message>
	 */
	public function scan(): array;

	/**
	 * Messages skipped because an id, domain, context, or plural was not a literal.
	 *
	 * @return list<string>
	 */
	public function warnings(): array;
}
