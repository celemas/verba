import { describe, expect, it } from 'vitest';

import { type Messages, type Payload, Translator } from '../src/translator.js';

const shop = {
	domain: 'shop',
	plural: 'de',
	messages: {
		'Add to cart': 'In den Warenkorb',
		'Found one product': ['Ein Produkt gefunden', ':count Produkte gefunden'],
		':count item': [':count Artikel', ':count Artikel'],
		'single plural': 'Einzeln',
	},
};

const cosray = {
	domain: 'cosray',
	plural: 'de',
	messages: {
		'Add to cart': 'COSRAY Warenkorb',
		'node:new': 'Neuer Knoten',
	},
};

const edge = {
	domain: 'edge',
	plural: 'ru',
	messages: {
		twoforms: ['odna', 'dve'],
		emptyforms: [],
	},
};

const cascade: Payload = { locale: 'de', domains: [shop, cosray] };

describe('Translator', () => {
	it('exposes the locale and defaults to en', () => {
		expect(new Translator({ locale: 'de' }).locale).toBe('de');
		expect(new Translator().locale).toBe('en');
	});

	it('prefers the first domain in the cascade', () => {
		expect(new Translator(cascade).translate('Add to cart')).toBe('In den Warenkorb');
	});

	it('falls through to the next domain', () => {
		expect(new Translator(cascade).translate('node:new')).toBe('Neuer Knoten');
	});

	it('returns the message id on a miss', () => {
		expect(new Translator(cascade).translate('missing')).toBe('missing');
	});

	it('interpolates named args on a miss', () => {
		expect(new Translator(cascade).translate('Hi :n', { n: 'Bob' })).toBe('Hi Bob');
	});

	it('skips plural entries on singular lookups', () => {
		expect(new Translator(cascade).translate('Found one product')).toBe('Found one product');
	});

	it('tolerates messages encoded as an empty array', () => {
		const t = new Translator({ domains: [{ domain: 'x', messages: [] as unknown as Messages }] });

		expect(t.translate('Add to cart')).toBe('Add to cart');
	});

	it('pins a domain', () => {
		const t = new Translator(cascade);

		expect(t.translateDomain('cosray', 'Add to cart')).toBe('COSRAY Warenkorb');
	});

	it('returns the id for an unknown domain', () => {
		expect(new Translator(cascade).translateDomain('missing-domain', 'nope')).toBe('nope');
	});

	it('skips plural entries on pinned singular lookups', () => {
		const t = new Translator(cascade);

		expect(t.translateDomain('shop', 'Found one product')).toBe('Found one product');
	});

	it('picks the singular plural form', () => {
		const t = new Translator(cascade);

		expect(t.translatePlural('Found one product', 'Found :count products', 1)).toBe(
			'Ein Produkt gefunden',
		);
	});

	it('picks the plural form and binds count', () => {
		const t = new Translator(cascade);

		expect(t.translatePlural('Found one product', 'Found :count products', 3)).toBe(
			'3 Produkte gefunden',
		);
	});

	it('keeps a caller-supplied count', () => {
		const t = new Translator(cascade);

		expect(t.translatePlural(':count item', ':count items', 5, { count: 'many' })).toBe(
			'many Artikel',
		);
	});

	it('uses a string entry for plural lookups', () => {
		expect(new Translator(cascade).translatePlural('single plural', 'singles', 2)).toBe('Einzeln');
	});

	it('falls back to the singular id', () => {
		expect(new Translator(cascade).translatePlural('nomatch', 'nomatches', 1)).toBe('nomatch');
	});

	it('falls back to the plural id and binds count', () => {
		const t = new Translator(cascade);

		expect(t.translatePlural(':count thing', ':count things', 2)).toBe('2 things');
	});

	it('uses the last form when the rule index is out of range', () => {
		const t = new Translator({ locale: 'ru', domains: [edge] });

		expect(t.translatePlural('twoforms', 'x', 1)).toBe('odna');
		expect(t.translatePlural('twoforms', 'x', 5)).toBe('dve');
	});

	it('treats empty form lists as untranslated', () => {
		const t = new Translator({ locale: 'ru', domains: [edge] });

		expect(t.translatePlural('emptyforms', 'many-x', 1)).toBe('emptyforms');
		expect(t.translatePlural('emptyforms', 'many-x', 5)).toBe('many-x');
	});

	it('skips empty form lists to later entries', () => {
		const t = new Translator({
			locale: 'ru',
			domains: [edge, { domain: 'more', plural: 'en', messages: { emptyforms: 'empty en' } }],
		});

		expect(t.translatePlural('emptyforms', 'x', 5)).toBe('empty en');
	});

	it('defaults the plural rule to the translator locale', () => {
		const t = new Translator({
			locale: 'fr',
			domains: [{ domain: 'app', messages: { ':count item': ['one', 'many'] } }],
		});

		expect(t.translatePlural(':count item', ':count items', 0)).toBe('one');
	});

	it('pins a domain for plural lookups', () => {
		const t = new Translator(cascade);

		expect(t.translateDomainPlural('shop', 'Found one product', 'x', 1)).toBe(
			'Ein Produkt gefunden',
		);
	});

	it('falls back for an unknown plural domain', () => {
		const t = new Translator(cascade);

		expect(t.translateDomainPlural('missing-domain', 'one', ':count many', 5)).toBe('5 many');
	});

	it('falls back on a pinned plural miss', () => {
		const t = new Translator(cascade);

		expect(t.translateDomainPlural('shop', 'nomatch', 'nomatches', 1)).toBe('nomatch');
	});
});

describe('Translator with a locale fallback chain', () => {
	// One entry per locale of the PHP-side fallback chain, es → ru, as
	// Translator::exportMany() ships it. Each entry has its own plural rule.
	const chain: Payload = {
		locale: 'es',
		domains: [
			{ domain: 'app', plural: 'es', messages: { x: ['un', 'unos'] } },
			{
				domain: 'app',
				plural: 'ru',
				messages: {
					x: 'X-en',
					thing: [':count thing one', ':count thing few', ':count thing many'],
				},
			},
		],
	};

	it('walks repeated domain entries for pinned lookups', () => {
		const t = new Translator(chain);

		expect(t.translateDomain('app', 'x')).toBe('X-en');
		expect(t.translateDomainPlural('app', 'x', 'xs', 1)).toBe('un');
	});

	it('reaches a fallback string behind a plural list', () => {
		const t = new Translator(chain);

		expect(t.translate('x')).toBe('X-en');
		expect(t.translatePlural('x', 'xs', 1)).toBe('un');
	});

	it("applies each entry's own plural rule", () => {
		const t = new Translator(chain);

		expect(t.translatePlural('thing', 'things', 21)).toBe('21 thing one');
		expect(t.translatePlural('thing', 'things', 5)).toBe('5 thing many');
	});

	it('keeps the domain cascade ahead of locale fallback', () => {
		const t = new Translator({
			locale: 'es',
			domains: [
				{ domain: 'app', plural: 'es', messages: {} },
				{ domain: 'app', plural: 'en', messages: { shared: 'app-en' } },
				{ domain: 'framework', plural: 'es', messages: { shared: 'framework-es' } },
			],
		});

		expect(t.translate('shared')).toBe('app-en');
	});
});
