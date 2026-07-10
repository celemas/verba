import { afterEach, describe, expect, it } from 'vitest';

import { Translator } from '../src/translator.js';
import { __, __d, __dn, __n, activate, deactivate, load, translator } from '../src/verba.js';

const payload = {
	locale: 'de',
	domains: [
		{
			domain: 'app',
			plural: 'de',
			messages: {
				Save: 'Speichern',
				':count file': [':count Datei', ':count Dateien'],
			},
		},
	],
};

type WithDocument = { document?: unknown };

function stubDocument(text: string | null): void {
	(globalThis as WithDocument).document = {
		getElementById: (id: string) => (id === 'verba-catalog' ? { textContent: text } : null),
	};
}

afterEach(() => {
	deactivate();
	delete (globalThis as WithDocument).document;
});

describe('the global functions', () => {
	it('return the interpolated id with no translator active', () => {
		expect(__('Save')).toBe('Save');
		expect(__('Hello :name', { name: 'Ada' })).toBe('Hello Ada');
		expect(__n(':count file', ':count files', 1)).toBe('1 file');
		expect(__n(':count file', ':count files', 2)).toBe('2 files');
		expect(__d('app', 'Save')).toBe('Save');
		expect(__dn('app', ':count file', ':count files', 2)).toBe('2 files');
	});

	it('route through the active translator', () => {
		const t = new Translator(payload);
		activate(t);

		expect(translator()).toBe(t);
		expect(__('Save')).toBe('Speichern');
		expect(__n(':count file', ':count files', 2)).toBe('2 Dateien');
		expect(__d('app', 'Save')).toBe('Speichern');
		expect(__dn('app', ':count file', ':count files', 1)).toBe('1 Datei');
	});

	it('fall back again after deactivation', () => {
		activate(new Translator(payload));
		deactivate();

		expect(translator()).toBeNull();
		expect(__('Save')).toBe('Save');
	});
});

describe('load', () => {
	it('returns null without a DOM', () => {
		expect(load()).toBeNull();
	});

	it('returns null when the element is missing or empty', () => {
		stubDocument(null);

		expect(load()).toBeNull();
		expect(load('other-id')).toBeNull();
	});

	it('returns null for invalid JSON', () => {
		stubDocument('{nope');

		expect(load()).toBeNull();
	});

	it('builds a translator from the inlined payload', () => {
		stubDocument(JSON.stringify(payload));
		const t = load();

		expect(t?.locale).toBe('de');
		expect(t?.translate('Save')).toBe('Speichern');
	});
});
