export type Args = Record<string, string | number>;

/**
 * Fills named `:key` placeholders in a message template. Longer keys are
 * replaced first and replacements are never re-scanned, mirroring PHP's
 * strtr. An empty args object leaves the template untouched. Positional
 * sprintf arguments are PHP-only; a stray `%s` passes through unchanged.
 */
export function interpolate(template: string, args: Args = {}): string {
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

function escape(literal: string): string {
	return literal.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
