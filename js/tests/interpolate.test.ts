import { describe, expect, it } from 'vitest';

import { interpolate } from '../src/interpolate.js';

describe('interpolate', () => {
	it('leaves the template untouched without args', () => {
		expect(interpolate('Hello :name')).toBe('Hello :name');
	});

	it('fills named placeholders', () => {
		expect(interpolate('Hello :name', { name: 'Ada' })).toBe('Hello Ada');
	});

	it('casts numbers to strings', () => {
		expect(interpolate(':count files', { count: 3 })).toBe('3 files');
	});

	it('replaces every occurrence', () => {
		expect(interpolate(':x + :x', { x: 1 })).toBe('1 + 1');
	});

	it('replaces longer keys first', () => {
		expect(interpolate(':names :name', { name: 'a', names: 'b' })).toBe('b a');
	});

	it('never re-scans replaced text', () => {
		expect(interpolate(':a', { a: ':b', b: 'x' })).toBe(':b');
	});

	it('keeps literal percent signs', () => {
		expect(interpolate('100% :done', { done: 'ok' })).toBe('100% ok');
	});

	it('escapes regex metacharacters in keys', () => {
		expect(interpolate('a :k.x b', { 'k.x': 'v' })).toBe('a v b');
	});
});
