import type { Args } from './interpolate.js';
import { type Payload, Translator } from './translator.js';

let active: Translator | null = null;

let fallback: Translator | null = null;

/**
 * Wires the global functions to a translator. With no translator active,
 * lookups return the message id (with interpolation), which keeps
 * translation calls safe during SSR, in tests, and before boot.
 */
export function activate(translator: Translator): void {
	active = translator;
}

export function deactivate(): void {
	active = null;
}

export function translator(): Translator | null {
	return active;
}

/**
 * Reads the JSON payload inlined in the given script element and wraps it in
 * a Translator. Returns null without a DOM (SSR), when the element is
 * missing or empty, or when its content is not valid JSON.
 */
export function load(elementId = 'verba-catalog'): Translator | null {
	const el = typeof document === 'undefined' ? null : document.getElementById(elementId);

	if (!el?.textContent) {
		return null;
	}

	try {
		return new Translator(JSON.parse(el.textContent) as Payload);
	} catch {
		return null;
	}
}

export function loadAndActivate(elementId?: string): Translator | null {
	const loaded = load(elementId);

	if (loaded) {
		activate(loaded);
	}

	return loaded;
}

/** Translate a message through the active domain cascade. */
export function __(id: string, args: Args = {}): string {
	return current().translate(id, args);
}

/** Translate a contextual message through the active domain cascade. */
export function __p(context: string, id: string, args: Args = {}): string {
	return current().translateContext(context, id, args);
}

/** Translate a pluralized message, choosing the form for n. */
export function __n(one: string, many: string, n: number, args: Args = {}): string {
	return current().translatePlural(one, many, n, args);
}

/** Translate a contextual pluralized message, choosing the form for n. */
export function __np(
	context: string,
	one: string,
	many: string,
	n: number,
	args: Args = {},
): string {
	return current().translateContextPlural(context, one, many, n, args);
}

/** Translate a message from a specific domain. */
export function __d(domain: string, id: string, args: Args = {}): string {
	return current().translateDomain(domain, id, args);
}

/** Translate a contextual message from a specific domain. */
export function __dp(domain: string, context: string, id: string, args: Args = {}): string {
	return current().translateDomainContext(domain, context, id, args);
}

/** Translate a pluralized message from a specific domain. */
export function __dn(
	domain: string,
	one: string,
	many: string,
	n: number,
	args: Args = {},
): string {
	return current().translateDomainPlural(domain, one, many, n, args);
}

/** Translate a contextual pluralized message from a specific domain. */
export function __dnp(
	domain: string,
	context: string,
	one: string,
	many: string,
	n: number,
	args: Args = {},
): string {
	return current().translateDomainContextPlural(domain, context, one, many, n, args);
}

function current(): Translator {
	return active ?? (fallback ??= new Translator());
}
