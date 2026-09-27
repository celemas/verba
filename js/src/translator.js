/** @import { Args } from './interpolate.js' */
/** @import { PluralRule } from './plurals.js' */

import { interpolate } from './interpolate.js';
import { pluralRule } from './plurals.js';

/** @typedef {Record<string, string | string[]>} Messages */

/** @typedef {Record<string, Messages>} Contexts */

/**
 * @typedef {object} DomainPayload
 * @property {string} domain
 * @property {string} [plural]
 * @property {Messages} [messages]
 * @property {Contexts} [contexts]
 */

/**
 * @typedef {object} Payload
 * @property {string} [locale]
 * @property {DomainPayload[]} [domains]
 */

/**
 * @typedef {object} Domain
 * @property {string} name
 * @property {Messages} messages
 * @property {Contexts} contexts
 * @property {PluralRule} rule
 */

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
	/**
	 * @readonly
	 * @type {string}
	 */
	locale;

	/**
	 * @private
	 * @readonly
	 * @type {Domain[]}
	 */
	domains;

	/** @param {Payload} [payload] */
	constructor(payload = {}) {
		this.locale = payload.locale ?? 'en';
		this.domains = (payload.domains ?? []).map((entry) => ({
			name: entry.domain,
			messages: readMessages(entry.messages),
			contexts: readContexts(entry.contexts),
			rule: pluralRule(entry.plural ?? this.locale),
		}));
	}

	/**
	 * @param {string} id
	 * @param {Args} [args]
	 * @returns {string}
	 */
	translate(id, args = {}) {
		return this.translateFrom(null, id, args);
	}

	/**
	 * @param {string} context
	 * @param {string} id
	 * @param {Args} [args]
	 * @returns {string}
	 */
	translateContext(context, id, args = {}) {
		return this.translateFrom(context, id, args);
	}

	/**
	 * @param {string} name
	 * @param {string} id
	 * @param {Args} [args]
	 * @returns {string}
	 */
	translateDomain(name, id, args = {}) {
		return this.translateDomainFrom(name, null, id, args);
	}

	/**
	 * @param {string} name
	 * @param {string} context
	 * @param {string} id
	 * @param {Args} [args]
	 * @returns {string}
	 */
	translateDomainContext(name, context, id, args = {}) {
		return this.translateDomainFrom(name, context, id, args);
	}

	/**
	 * @param {string} one
	 * @param {string} many
	 * @param {number} n
	 * @param {Args} [args]
	 * @returns {string}
	 */
	translatePlural(one, many, n, args = {}) {
		return this.translatePluralFrom(null, one, many, n, args);
	}

	/**
	 * @param {string} context
	 * @param {string} one
	 * @param {string} many
	 * @param {number} n
	 * @param {Args} [args]
	 * @returns {string}
	 */
	translateContextPlural(context, one, many, n, args = {}) {
		return this.translatePluralFrom(context, one, many, n, args);
	}

	/**
	 * @param {string} name
	 * @param {string} one
	 * @param {string} many
	 * @param {number} n
	 * @param {Args} [args]
	 * @returns {string}
	 */
	translateDomainPlural(name, one, many, n, args = {}) {
		return this.translateDomainPluralFrom(name, null, one, many, n, args);
	}

	/**
	 * @param {string} name
	 * @param {string} context
	 * @param {string} one
	 * @param {string} many
	 * @param {number} n
	 * @param {Args} [args]
	 * @returns {string}
	 */
	translateDomainContextPlural(name, context, one, many, n, args = {}) {
		return this.translateDomainPluralFrom(name, context, one, many, n, args);
	}

	/**
	 * @private
	 * @param {string | null} context
	 * @param {string} id
	 * @param {Args} args
	 * @returns {string}
	 */
	translateFrom(context, id, args) {
		for (const domain of this.domains) {
			const entry = messageFrom(domain, context, id);

			if (typeof entry === 'string') {
				return interpolate(entry, args);
			}
		}

		return interpolate(id, args);
	}

	/**
	 * @private
	 * @param {string} name
	 * @param {string | null} context
	 * @param {string} id
	 * @param {Args} args
	 * @returns {string}
	 */
	translateDomainFrom(name, context, id, args) {
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

	/**
	 * @private
	 * @param {string | null} context
	 * @param {string} one
	 * @param {string} many
	 * @param {number} n
	 * @param {Args} args
	 * @returns {string}
	 */
	translatePluralFrom(context, one, many, n, args) {
		for (const domain of this.domains) {
			const form = pluralFrom(domain, context, one, n, args);

			if (form !== null) {
				return form;
			}
		}

		return interpolate(n === 1 ? one : many, pluralArgs(args, n));
	}

	/**
	 * @private
	 * @param {string} name
	 * @param {string | null} context
	 * @param {string} one
	 * @param {string} many
	 * @param {number} n
	 * @param {Args} args
	 * @returns {string}
	 */
	translateDomainPluralFrom(name, context, one, many, n, args) {
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

/**
 * PHP encodes an empty message map as a JSON array, so lists read as empty.
 *
 * @param {Messages | undefined} messages
 * @returns {Messages}
 */
function readMessages(messages) {
	return messages === undefined || Array.isArray(messages) ? {} : messages;
}

/**
 * @param {Contexts | undefined} contexts
 * @returns {Contexts}
 */
function readContexts(contexts) {
	if (contexts === undefined || Array.isArray(contexts)) {
		return {};
	}

	return Object.fromEntries(
		Object.entries(contexts).map(([context, messages]) => [context, readMessages(messages)]),
	);
}

/**
 * @param {Domain} domain
 * @param {string | null} context
 * @param {string} id
 * @returns {string | string[] | undefined}
 */
function messageFrom(domain, context, id) {
	return context === null ? domain.messages[id] : domain.contexts[context]?.[id];
}

/**
 * An empty form list counts as untranslated, like a missing id — mirrors PHP.
 *
 * @param {Domain} domain
 * @param {string | null} context
 * @param {string} one
 * @param {number} n
 * @param {Args} args
 * @returns {string | null}
 */
function pluralFrom(domain, context, one, n, args) {
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

/**
 * Binds `:count` to the count unless the caller already set it.
 *
 * @param {Args} args
 * @param {number} n
 * @returns {Args}
 */
function pluralArgs(args, n) {
	return 'count' in args ? args : { ...args, count: n };
}
