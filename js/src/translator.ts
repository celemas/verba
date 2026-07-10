import { type Args, interpolate } from './interpolate.js';
import { type PluralRule, pluralRule } from './plurals.js';

export type Messages = Record<string, string | string[]>;

export type DomainPayload = {
	domain: string;
	plural?: string;
	messages?: Messages;
};

export type Payload = {
	locale?: string;
	domains?: DomainPayload[];
};

type Domain = {
	name: string;
	messages: Messages;
	rule: PluralRule;
};

/**
 * Resolves messages for one locale across an ordered cascade of domains —
 * the JavaScript mirror of the PHP Translator, fed by the payload that
 * `Translator::exportMany()` produces. The first domain whose catalog holds
 * a translation wins; a miss falls back to the message id itself.
 */
export class Translator {
	readonly locale: string;

	private readonly domains: Domain[];

	constructor(payload: Payload = {}) {
		this.locale = payload.locale ?? 'en';
		this.domains = (payload.domains ?? []).map((entry) => ({
			name: entry.domain,
			messages: readMessages(entry.messages),
			rule: pluralRule(entry.plural ?? this.locale),
		}));
	}

	translate(id: string, args: Args = {}): string {
		for (const domain of this.domains) {
			const entry = domain.messages[id];

			if (typeof entry === 'string') {
				return interpolate(entry, args);
			}
		}

		return interpolate(id, args);
	}

	translateDomain(name: string, id: string, args: Args = {}): string {
		const entry = this.domain(name)?.messages[id];

		return interpolate(typeof entry === 'string' ? entry : id, args);
	}

	translatePlural(one: string, many: string, n: number, args: Args = {}): string {
		for (const domain of this.domains) {
			const form = pluralFrom(domain, one, n, args);

			if (form !== null) {
				return form;
			}
		}

		return interpolate(n === 1 ? one : many, pluralArgs(args, n));
	}

	translateDomainPlural(
		name: string,
		one: string,
		many: string,
		n: number,
		args: Args = {},
	): string {
		const domain = this.domain(name);
		const form = domain === undefined ? null : pluralFrom(domain, one, n, args);

		return form ?? interpolate(n === 1 ? one : many, pluralArgs(args, n));
	}

	private domain(name: string): Domain | undefined {
		return this.domains.find((domain) => domain.name === name);
	}
}

/** PHP encodes an empty message map as a JSON array, so lists read as empty. */
function readMessages(messages: Messages | undefined): Messages {
	return messages === undefined || Array.isArray(messages) ? {} : messages;
}

function pluralFrom(domain: Domain, one: string, n: number, args: Args): string | null {
	const entry = domain.messages[one];

	if (Array.isArray(entry)) {
		if (entry.length === 0) {
			return interpolate(one, pluralArgs(args, n));
		}

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
