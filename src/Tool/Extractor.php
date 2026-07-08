<?php

declare(strict_types=1);

namespace Celemas\Verba\Tool;

/**
 * Runs a domain's scanners, keeps the messages that belong to it, and merges
 * duplicates by id (unioning locations, preferring a plural form when any call
 * site supplied one).
 *
 * @api
 */
final class Extractor
{
	public function __construct(
		private readonly Domain $domain,
	) {}

	/**
	 * @return array{messages: array<string, Message>, warnings: list<string>}
	 */
	public function extract(): array
	{
		$merged = [];
		$warnings = [];

		foreach ($this->domain->scanners as $scanner) {
			foreach ($scanner->scan() as $message) {
				if (!$this->domain->owns($message->domain)) {
					continue;
				}

				$merged[$message->id] = $this->fold($merged[$message->id] ?? null, $message);
			}

			foreach ($scanner->warnings() as $warning) {
				$warnings[] = $warning;
			}
		}

		ksort($merged, SORT_STRING);

		return ['messages' => $merged, 'warnings' => $warnings];
	}

	private function fold(?Message $existing, Message $next): Message
	{
		if ($existing === null) {
			return new Message(null, $next->id, $next->plural, $next->locations);
		}

		return new Message(
			null,
			$next->id,
			$existing->plural ?? $next->plural,
			[...$existing->locations, ...$next->locations],
		);
	}
}
