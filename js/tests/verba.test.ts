import { afterEach, describe, expect, it } from 'vitest';

import { Translator } from '../src/translator.js';
import {
	__,
	__d,
	__dn,
	__dnp,
	__dp,
	__n,
	__np,
	__p,
	activate,
	deactivate,
	load,
	loadAndActivate,
	translator,
} from '../src/verba.js';

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
			contexts: {
				inventory: { ':count file': [':count Kontextdatei', ':count Kontextdateien'] },
				menu: { Open: 'Öffnen' },
			},
		},
	],
};

type WithDocument = { document?: unknown };

function stubDocument(text: string | null, elementId = 'verba-catalog'): void {
	(globalThis as WithDocument).document = {
		getElementById: (id: string) => (id === elementId ? { textContent: text } : null),
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
		expect(__p('menu', 'Open')).toBe('Open');
		expect(__np('inventory', ':count file', ':count files', 2)).toBe('2 files');
		expect(__dp('app', 'menu', 'Open')).toBe('Open');
		expect(__dnp('app', 'inventory', ':count file', ':count files', 2)).toBe('2 files');
	});

	it('route through the active translator', () => {
		const t = new Translator(payload);
		activate(t);

		expect(translator()).toBe(t);
		expect(__('Save')).toBe('Speichern');
		expect(__n(':count file', ':count files', 2)).toBe('2 Dateien');
		expect(__d('app', 'Save')).toBe('Speichern');
		expect(__dn('app', ':count file', ':count files', 1)).toBe('1 Datei');
		expect(__p('menu', 'Open')).toBe('Öffnen');
		expect(__np('inventory', ':count file', ':count files', 2)).toBe('2 Kontextdateien');
		expect(__dp('app', 'menu', 'Open')).toBe('Öffnen');
		expect(__dnp('app', 'inventory', ':count file', ':count files', 1)).toBe('1 Kontextdatei');
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

describe('loadAndActivate', () => {
	it('loads and activates the inlined payload', () => {
		stubDocument(JSON.stringify(payload), 'catalog');
		const loaded = loadAndActivate('catalog');

		expect(translator()).toBe(loaded);
		expect(__('Save')).toBe('Speichern');
	});

	it('leaves the active translator unchanged when loading fails', () => {
		const active = new Translator(payload);
		activate(active);

		expect(loadAndActivate()).toBeNull();
		expect(translator()).toBe(active);
	});
});
