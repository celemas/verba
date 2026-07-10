import { describe, expect, it } from 'vitest';

import { pluralRule } from '../src/plurals.js';

function pick(key: string, counts: number[]): number[] {
	return counts.map(pluralRule(key));
}

describe('pluralRule', () => {
	it('defaults to two forms', () => {
		expect(pick('de', [1, 2, 0])).toEqual([0, 1, 1]);
	});

	it('treats zero as singular in french', () => {
		expect(pick('fr', [0, 1, 2])).toEqual([0, 0, 1]);
	});

	it('honors the brazilian region subtag', () => {
		expect(pick('pt-BR', [0, 1, 2])).toEqual([0, 0, 1]);
		expect(pick('pt', [0, 1, 2])).toEqual([1, 0, 1]);
	});

	it('uses one form for cjk-style languages', () => {
		expect(pick('ja', [0, 1, 5])).toEqual([0, 0, 0]);
	});

	it('applies the east slavic rule', () => {
		expect(pick('ru', [1, 21, 2, 22, 5, 11, 12, 111])).toEqual([0, 0, 1, 1, 2, 2, 2, 2]);
	});

	it('applies the polish rule', () => {
		expect(pick('pl', [1, 2, 22, 5, 12, 0])).toEqual([0, 1, 1, 2, 2, 2]);
	});

	it('applies the czech and slovak rule', () => {
		expect(pick('cs', [1, 2, 4, 5, 0])).toEqual([0, 1, 1, 2, 2]);
		expect(pick('sk', [1, 3, 7])).toEqual([0, 1, 2]);
	});

	it('applies the arabic rule', () => {
		expect(pick('ar', [0, 1, 2, 3, 10, 11, 99, 100, 102])).toEqual([0, 1, 2, 3, 3, 4, 4, 5, 5]);
	});

	it('normalizes case, separators, and region subtags', () => {
		expect(pick('RU_ru', [2])).toEqual([1]);
		expect(pick('de-AT', [2])).toEqual([1]);
	});
});
