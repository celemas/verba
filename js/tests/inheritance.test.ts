import { describe, expect, it } from 'vitest';

import { Translator } from '../src/translator.js';
import payload from './fixtures/inheritance.json';

describe('inherited catalogs exported by PHP', () => {
	const t = new Translator(JSON.parse(JSON.stringify(payload)));

	it('resolves overrides and inherited ordinary and contextual messages', () => {
		expect(t.translate('Choose a colour')).toBe('Choose a color');
		expect(t.translate('Save')).toBe('Save changes');
		expect(t.translateDomain('variant', 'Save')).toBe('Save changes');
		expect(t.translateContext('menu', 'Save')).toBe('Save menu');
		expect(t.translateDomainContext('variant', 'menu', 'Save')).toBe('Save menu');
		expect(t.translateContext('0', '404')).toBe('Not found');
		expect(t.translateContext('other', 'Save')).toBe('Save');
		expect(t.translate('Old')).toBe('Old');
	});

	it('preserves each supplying catalog’s plural rule', () => {
		expect(t.translatePlural('one item', 'many items', 21)).toBe('21 base-one');
		expect(t.translateDomainPlural('variant', 'one item', 'many items', 2)).toBe('2 base-few');
		expect(t.translateContextPlural('inventory', 'one item', 'many items', 5)).toBe(
			'5 context-many',
		);
		expect(
			t.translateDomainContextPlural('variant', 'inventory', 'one item', 'many items', 21),
		).toBe('21 context-one');
	});

	it('keeps plural overrides whole without hiding inherited singular translations', () => {
		expect(t.translatePlural('Local forms', 'Local forms', 5)).toBe('local');
		expect(t.translate('Mixed')).toBe('Base singular');
		expect(t.translatePlural('Mixed', 'Mixed', 5)).toBe('Local many');
	});
});
