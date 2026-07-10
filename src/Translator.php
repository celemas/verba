<?php

declare(strict_types=1);

namespace Celemas\Verba;

use InvalidArgumentException;

/**
 * Resolves messages for one locale across an ordered cascade of domains.
 *
 * The first domain whose catalog holds a translation wins; a miss falls back
 * to the message id itself. Domains map to the directory that holds their
 * `<domain>.<locale>.php` catalog files.
 *
 * A locale may name fallback locales, tried in order whenever its own catalog
 * lacks a string. Resolution is per id and stays within a domain before the
 * cascade moves on, so the domain cascade outranks the locale fallback.
 *
 * @api
 */
final class Translator
{
	/** @var list<string> */
	private array $order;

	/** @var list<string> Locale resolution order: the primary locale, then its fallbacks. */
	private array $locales;

	/** @var array<string, Catalog> */
	private array $catalogs = [];

	/**
	 * Locale ids may contain only ASCII letters, digits, hyphens, and underscores.
	 *
	 * @param array<string, string> $domains Domain name to i18n directory, in cascade order.
	 * @param list<string> $fallback Locales tried, in order, when the primary locale lacks a string.
	 */
	public function __construct(
		public readonly string $locale,
		private array $domains,
		array $fallback = [],
	) {
		$this->order = array_keys($domains);
		$this->locales = array_values(array_unique([$locale, ...$fallback]));

		foreach ($this->locales as $localeId) {
			// Locale ids become catalog filename segments; reject path characters.
			if (preg_match('/^[A-Za-z0-9_-]+$/', $localeId) !== 1) {
				throw new InvalidArgumentException("Invalid locale id '{$localeId}'");
			}
		}
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public function translate(string $id, array $args = []): string
	{
		foreach ($this->order as $domain) {
			$entry = $this->entry($domain, $id);

			if ($entry !== null) {
				return Interpolate::apply($entry, $args);
			}
		}

		return Interpolate::apply($id, $args);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public function translateDomain(string $domain, string $id, array $args = []): string
	{
		$entry = array_key_exists($domain, $this->domains) ? $this->entry($domain, $id) : null;

		return Interpolate::apply($entry ?? $id, $args);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public function translatePlural(string $one, string $many, int $n, array $args = []): string
	{
		foreach ($this->order as $domain) {
			$form = $this->pluralEntry($domain, $one, $n, $args);

			if ($form !== null) {
				return $form;
			}
		}

		return Interpolate::apply($n === 1 ? $one : $many, self::pluralArgs($args, $n));
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public function translateDomainPlural(
		string $domain,
		string $one,
		string $many,
		int $n,
		array $args = [],
	): string {
		$form = array_key_exists($domain, $this->domains)
			? $this->pluralEntry($domain, $one, $n, $args)
			: null;

		return $form ?? Interpolate::apply($n === 1 ? $one : $many, self::pluralArgs($args, $n));
	}

	/**
	 * Canonical, JSON-ready catalog data for one domain at the primary locale:
	 * the plural rule id and translated messages. Fallback locales are not
	 * consulted here — {@see self::exportMany()} ships the whole resolution
	 * chain. Empty when the domain is not part of this translator's cascade.
	 *
	 * @return array{plural: string, messages: array<string, string|list<string>>}
	 */
	public function export(string $domain): array
	{
		if (!array_key_exists($domain, $this->domains)) {
			return ['plural' => $this->locale, 'messages' => []];
		}

		return $this->catalog($domain, $this->locale)->export();
	}

	/**
	 * The full payload for handing catalogs to the JavaScript runtime: this
	 * translator's locale plus ordered, locale-specific entries for each named
	 * domain and its fallback chain. Each entry carries its own plural rule, so
	 * the JavaScript runtime resolves the same chain the PHP runtime does.
	 * Entries with no reachable messages are omitted. List only domains meant
	 * for the browser — the payload ends up world-readable in the page source.
	 *
	 * @param list<string> $domains
	 * @return array{locale: string, domains: list<array{domain: string, plural: string, messages: array<string, string|list<string>>}>}
	 */
	public function exportMany(array $domains): array
	{
		$exports = [];

		foreach ($domains as $domain) {
			if (array_key_exists($domain, $this->domains)) {
				array_push($exports, ...$this->domainExports($domain));

				continue;
			}

			$exports[] = ['domain' => $domain, 'plural' => $this->locale, 'messages' => []];
		}

		return ['locale' => $this->locale, 'domains' => $exports];
	}

	/**
	 * Locale-specific export entries for a domain, dropping messages a lookup
	 * can never reach: an id resolved to a string is final, and behind a plural
	 * list only a string can still win (for singular lookups). Fallback entries
	 * with nothing left to add are dropped entirely.
	 *
	 * @return list<array{domain: string, plural: string, messages: array<string, string|list<string>>}>
	 */
	private function domainExports(string $domain): array
	{
		$exports = [];

		/** @var array<string, bool> $placed True when a placed id is a string, false for a form list. */
		$placed = [];

		foreach ($this->locales as $i => $locale) {
			$export = $this->catalog($domain, $locale)->export();
			$messages = [];

			foreach ($export['messages'] as $id => $message) {
				$stringPlaced = $placed[$id] ?? null;

				// A placed string is final; behind a form list only a string adds value.
				if ($stringPlaced === true || $stringPlaced === false && !is_string($message)) {
					continue;
				}

				$messages[$id] = $message;
				$placed[$id] = is_string($message);
			}

			if ($i > 0 && $messages === []) {
				continue;
			}

			$exports[] = ['domain' => $domain, 'plural' => $export['plural'], 'messages' => $messages];
		}

		return $exports;
	}

	/**
	 * The first string translation for $id across the locale chain, or null.
	 */
	private function entry(string $domain, string $id): ?string
	{
		foreach ($this->locales as $locale) {
			$entry = $this->catalog($domain, $locale)->get($id);

			if (is_string($entry)) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * The interpolated plural form from the first locale in the chain that
	 * translates $one, or null when none does.
	 *
	 * @param array<array-key, string|int|float> $args
	 */
	private function pluralEntry(string $domain, string $one, int $n, array $args): ?string
	{
		foreach ($this->locales as $locale) {
			$form = $this->pluralFrom($this->catalog($domain, $locale), $one, $n, $args);

			if ($form !== null) {
				return $form;
			}
		}

		return null;
	}

	/**
	 * An empty form list counts as untranslated, like null, so the fallback
	 * chain continues.
	 *
	 * @param array<array-key, string|int|float> $args
	 */
	private function pluralFrom(Catalog $catalog, string $one, int $n, array $args): ?string
	{
		$entry = $catalog->get($one);

		if (is_array($entry) && $entry !== []) {
			$idx = $catalog->form($n);
			$form = $entry[$idx] ?? $entry[array_key_last($entry)];

			return Interpolate::apply($form, self::pluralArgs($args, $n));
		}

		if (is_string($entry)) {
			return Interpolate::apply($entry, self::pluralArgs($args, $n));
		}

		return null;
	}

	private function catalog(string $domain, string $locale): Catalog
	{
		return $this->catalogs[$domain . "\0" . $locale] ??= Catalog::load(
			$this->domains[$domain] . '/' . $domain . '.' . $locale . '.php',
			$locale,
		);
	}

	/**
	 * Positional args are passed through untouched; named (or empty) args gain a
	 * `:count` placeholder bound to $n unless the caller already set `count`.
	 *
	 * @param array<array-key, string|int|float> $args
	 * @return array<array-key, string|int|float>
	 */
	private static function pluralArgs(array $args, int $n): array
	{
		if ($args !== [] && array_is_list($args)) {
			return $args;
		}

		/** @var array<array-key, string|int|float> $args */
		if (!array_key_exists('count', $args)) {
			$args['count'] = $n;
		}

		return $args;
	}
}
