<?php

declare(strict_types=1);

namespace Celema\Verba\Tool;

/**
 * Runs a domain's scanners, keeps the messages that belong to it, and merges
 * duplicates by context and id (unioning locations, preferring a plural form
 * when any call site supplied one).
 *
 * @api
 */
final class Extractor
{
	public function __construct(
		private readonly Domain $domain,
	) {}

	/**
	 * @return array{
	 *     messages: array<string, Message>,
	 *     contexts: array<string, array<string, Message>>,
	 *     warnings: list<string>,
	 * }
	 */
	public function extract(): array
	{
		$merged = [];
		$contexts = [];
		$warnings = [];

		foreach ($this->domain->scanners as $scanner) {
			foreach ($scanner->scan() as $message) {
				if (!$this->domain->owns($message->domain)) {
					continue;
				}

				if ($message->context === null) {
					$existing = $merged[$message->id] ?? null;
					$merged[$message->id] = $this->merge($existing, $message, $warnings);
				} else {
					$existing = $contexts[$message->context][$message->id] ?? null;
					$contexts[$message->context][$message->id] = $this->merge(
						$existing,
						$message,
						$warnings,
					);
				}
			}

			foreach ($scanner->warnings() as $warning) {
				$warnings[] = $warning;
			}
		}

		ksort($merged, SORT_STRING);
		ksort($contexts, SORT_STRING);

		foreach ($contexts as &$messages) {
			ksort($messages, SORT_STRING);
		}

		unset($messages);

		return ['messages' => $merged, 'contexts' => $contexts, 'warnings' => $warnings];
	}

	/**
	 * @param list<string> $warnings
	 */
	private function merge(?Message $existing, Message $next, array &$warnings): Message
	{
		$warning = $this->conflict($existing, $next);

		if ($warning !== null) {
			$warnings[] = $warning;
		}

		return $this->fold($existing, $next);
	}

	private function conflict(?Message $existing, Message $next): ?string
	{
		if ($existing === null || $existing->plural === $next->plural) {
			return null;
		}

		$location = $next->locations[0] ?? 'unknown location';
		$id = "message id '{$next->id}'";

		if ($next->context !== null) {
			$id .= " in context '{$next->context}'";
		}

		if ($existing->plural === null || $next->plural === null) {
			return "Mixed singular and plural calls for {$id} at {$location}";
		}

		return "Conflicting plural forms for {$id} at {$location}";
	}

	private function fold(?Message $existing, Message $next): Message
	{
		if ($existing === null) {
			return new Message(null, $next->id, $next->plural, $next->locations, $next->context);
		}

		return new Message(
			null,
			$next->id,
			$existing->plural ?? $next->plural,
			[...$existing->locations, ...$next->locations],
			$next->context,
		);
	}
}
