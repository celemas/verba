<?php

declare(strict_types=1);

namespace Celema\Verba\Tool;

/**
 * A translation domain: its catalog directory, the locales it maintains, and
 * the scanners that discover its messages. The default domain also receives
 * bare translation calls (those without an explicit domain).
 *
 * @api
 */
final class Domain
{
	/**
	 * @param list<string> $locales
	 * @param list<Scanner> $scanners
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $dir,
		public readonly array $locales,
		public readonly array $scanners,
		public readonly bool $default = false,
	) {}

	public function file(string $locale): string
	{
		return $this->dir . '/' . $this->name . '.' . $locale . '.php';
	}

	/**
	 * Whether a discovered message targeting $domain belongs here.
	 */
	public function owns(?string $domain): bool
	{
		return $domain === $this->name || $this->default && $domain === null;
	}
}
