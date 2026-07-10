<?php

declare(strict_types=1);

namespace Celemas\Verba;

/**
 * Resolves messages for one locale across an ordered cascade of domains.
 *
 * The first domain whose catalog holds a translation wins; a miss falls back
 * to the message id itself. Domains map to the directory that holds their
 * `<domain>.<locale>.php` catalog files.
 *
 * @api
 */
final class Translator
{
	/** @var list<string> */
	private array $order;

	/** @var array<string, Catalog> */
	private array $catalogs = [];

	/**
	 * @param array<string, string> $domains Domain name to i18n directory, in cascade order.
	 */
	public function __construct(
		public readonly string $locale,
		private array $domains,
	) {
		$this->order = array_keys($domains);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public function translate(string $id, array $args = []): string
	{
		foreach ($this->order as $domain) {
			$entry = $this->catalog($domain)->get($id);

			if (is_string($entry)) {
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
		$entry = array_key_exists($domain, $this->domains) ? $this->catalog($domain)->get($id) : null;

		if (is_string($entry)) {
			return Interpolate::apply($entry, $args);
		}

		return Interpolate::apply($id, $args);
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	public function translatePlural(string $one, string $many, int $n, array $args = []): string
	{
		foreach ($this->order as $domain) {
			$form = $this->pluralFrom($this->catalog($domain), $one, $n, $args);

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
		if (array_key_exists($domain, $this->domains)) {
			$form = $this->pluralFrom($this->catalog($domain), $one, $n, $args);

			if ($form !== null) {
				return $form;
			}
		}

		return Interpolate::apply($n === 1 ? $one : $many, self::pluralArgs($args, $n));
	}

	/**
	 * Canonical, JSON-ready catalog data for one domain at this translator's
	 * locale: the plural rule id and translated messages. Empty when the domain
	 * is not part of this translator's cascade. Intended for handing a catalog
	 * to a frontend runtime (e.g. an inline panel payload).
	 *
	 * @return array{plural: string, messages: array<string, string|list<string>>}
	 */
	public function export(string $domain): array
	{
		if (!array_key_exists($domain, $this->domains)) {
			return ['plural' => $this->locale, 'messages' => []];
		}

		return $this->catalog($domain)->export();
	}

	/**
	 * The full payload for handing catalogs to the JavaScript runtime: this
	 * translator's locale plus the export of each named domain, in cascade
	 * order. List only domains meant for the browser — the payload ends up
	 * world-readable in the page source.
	 *
	 * @param list<string> $domains
	 * @return array{locale: string, domains: list<array{domain: string, plural: string, messages: array<string, string|list<string>>}>}
	 */
	public function exportMany(array $domains): array
	{
		$exports = [];

		foreach ($domains as $domain) {
			$export = $this->export($domain);
			$exports[] = [
				'domain' => $domain,
				'plural' => $export['plural'],
				'messages' => $export['messages'],
			];
		}

		return ['locale' => $this->locale, 'domains' => $exports];
	}

	/**
	 * @param array<array-key, string|int|float> $args
	 */
	private function pluralFrom(Catalog $catalog, string $one, int $n, array $args): ?string
	{
		$entry = $catalog->get($one);

		if (is_array($entry)) {
			if ($entry === []) {
				return Interpolate::apply($one, self::pluralArgs($args, $n));
			}

			$idx = $catalog->form($n);
			$form = $entry[$idx] ?? $entry[array_key_last($entry)];

			return Interpolate::apply($form, self::pluralArgs($args, $n));
		}

		if (is_string($entry)) {
			return Interpolate::apply($entry, self::pluralArgs($args, $n));
		}

		return null;
	}

	private function catalog(string $domain): Catalog
	{
		return $this->catalogs[$domain] ??= Catalog::load(
			$this->domains[$domain] . '/' . $domain . '.' . $this->locale . '.php',
			$this->locale,
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
