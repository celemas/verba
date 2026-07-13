import { type Args, interpolate } from './interpolate.js';
import { type PluralRule, pluralRule } from './plurals.js';

export type Messages = Record<string, string | string[]>;

export type Contexts = Record<string, Messages>;

export type DomainPayload = {
	domain: string;
	plural?: string;
	messages?: Messages;
	contexts?: Contexts;
};

export type Payload = {
	locale?: string;
	domains?: DomainPayload[];
};

type Domain = {
	name: string;
	messages: Messages;
	contexts: Contexts;
	rule: PluralRule;
};

/**
 * Resolves messages for one locale across an ordered cascade of domains —
 * the JavaScript mirror of the PHP Translator, fed by the payload that
 * `Translator::exportMany()` produces. The first entry whose catalog holds
 * a translation wins; a miss falls back to the message id itself. A domain
 * may appear once per locale of the PHP-side fallback chain, each entry
 * carrying its own plural rule, so walking entries in payload order
 * resolves the same chain the PHP runtime does.
 */
export class Translator {
	readonly locale: string;

	private readonly domains: Domain[];

	constructor(payload: Payload = {}) {
		this.locale = payload.locale ?? 'en';
		this.domains = (payload.domains ?? []).map((entry) => ({
			name: entry.domain,
			messages: readMessages(entry.messages),
			contexts: readContexts(entry.contexts),
			rule: pluralRule(entry.plural ?? this.locale),
		}));
	}

	translate(id: string, args: Args = {}): string {
		return this.translateFrom(null, id, args);
	}

	translateContext(context: string, id: string, args: Args = {}): string {
		return this.translateFrom(context, id, args);
	}

	translateDomain(name: string, id: string, args: Args = {}): string {
		return this.translateDomainFrom(name, null, id, args);
	}

	translateDomainContext(name: string, context: string, id: string, args: Args = {}): string {
		return this.translateDomainFrom(name, context, id, args);
	}

	translatePlural(one: string, many: string, n: number, args: Args = {}): string {
		return this.translatePluralFrom(null, one, many, n, args);
	}

	translateContextPlural(
		context: string,
		one: string,
		many: string,
		n: number,
		args: Args = {},
	): string {
		return this.translatePluralFrom(context, one, many, n, args);
	}

	translateDomainPlural(
		name: string,
		one: string,
		many: string,
		n: number,
		args: Args = {},
	): string {
		return this.translateDomainPluralFrom(name, null, one, many, n, args);
	}

	translateDomainContextPlural(
		name: string,
		context: string,
		one: string,
		many: string,
		n: number,
		args: Args = {},
	): string {
		return this.translateDomainPluralFrom(name, context, one, many, n, args);
	}

	private translateFrom(context: string | null, id: string, args: Args): string {
		for (const domain of this.domains) {
			const entry = messageFrom(domain, context, id);

			if (typeof entry === 'string') {
				return interpolate(entry, args);
			}
		}

		return interpolate(id, args);
	}

	private translateDomainFrom(
		name: string,
		context: string | null,
		id: string,
		args: Args,
	): string {
		for (const domain of this.domains) {
			if (domain.name !== name) {
				continue;
			}

			const entry = messageFrom(domain, context, id);

			if (typeof entry === 'string') {
				return interpolate(entry, args);
			}
		}

		return interpolate(id, args);
	}

	private translatePluralFrom(
		context: string | null,
		one: string,
		many: string,
		n: number,
		args: Args,
	): string {
		for (const domain of this.domains) {
			const form = pluralFrom(domain, context, one, n, args);

			if (form !== null) {
				return form;
			}
		}

		return interpolate(n === 1 ? one : many, pluralArgs(args, n));
	}

	private translateDomainPluralFrom(
		name: string,
		context: string | null,
		one: string,
		many: string,
		n: number,
		args: Args,
	): string {
		for (const domain of this.domains) {
			if (domain.name !== name) {
				continue;
			}

			const form = pluralFrom(domain, context, one, n, args);

			if (form !== null) {
				return form;
			}
		}

		return interpolate(n === 1 ? one : many, pluralArgs(args, n));
	}
}

/** PHP encodes an empty message map as a JSON array, so lists read as empty. */
function readMessages(messages: Messages | undefined): Messages {
	return messages === undefined || Array.isArray(messages) ? {} : messages;
}

function readContexts(contexts: Contexts | undefined): Contexts {
	if (contexts === undefined || Array.isArray(contexts)) {
		return {};
	}

	return Object.fromEntries(
		Object.entries(contexts).map(([context, messages]) => [context, readMessages(messages)]),
	);
}

function messageFrom(
	domain: Domain,
	context: string | null,
	id: string,
): string | string[] | undefined {
	return context === null ? domain.messages[id] : domain.contexts[context]?.[id];
}

/** An empty form list counts as untranslated, like a missing id — mirrors PHP. */
function pluralFrom(
	domain: Domain,
	context: string | null,
	one: string,
	n: number,
	args: Args,
): string | null {
	const entry = messageFrom(domain, context, one);

	if (Array.isArray(entry) && entry.length > 0) {
		const form = entry[domain.rule(n)] ?? entry[entry.length - 1];

		return interpolate(form, pluralArgs(args, n));
	}

	if (typeof entry === 'string') {
		return interpolate(entry, pluralArgs(args, n));
	}

	return null;
}

/** Binds `:count` to the count unless the caller already set it. */
function pluralArgs(args: Args, n: number): Args {
	return 'count' in args ? args : { ...args, count: n };
}
