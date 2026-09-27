/** @typedef {Record<string, string | number>} Args */

/**
 * Fills named `:key` placeholders in a message template. Longer keys are
 * replaced first and replacements are never re-scanned, mirroring PHP's
 * strtr. An empty args object leaves the template untouched. Positional
 * sprintf arguments are PHP-only; a stray `%s` passes through unchanged.
 *
 * @param {string} template
 * @param {Args} [args]
 * @returns {string}
 */
export function interpolate(template, args = {}) {
	const keys = Object.keys(args);

	if (keys.length === 0) {
		return template;
	}

	const pattern = keys
		.sort((a, b) => b.length - a.length)
		.map((key) => escape(':' + key))
		.join('|');

	return template.replace(new RegExp(pattern, 'g'), (token) => String(args[token.slice(1)]));
}

/**
 * @param {string} literal
 * @returns {string}
 */
function escape(literal) {
	return literal.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
