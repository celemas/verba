export type PluralRule = (n: number) => number;

/**
 * Returns the rule mapping a count to its zero-based plural form index —
 * the same classic gettext formulas as the PHP runtime. Only the exceptions
 * are listed; every other language falls through to the two-form `n !== 1`
 * default. Region subtags are honored where they change the rule.
 */
export function pluralRule(key: string): PluralRule {
	const norm = key.toLowerCase().replaceAll('-', '_');
	const lang = norm.split('_')[0];

	if (norm === 'pt_br' || lang === 'fr') {
		return (n) => (n > 1 ? 1 : 0);
	}

	if (['ja', 'ko', 'zh', 'vi', 'th', 'id', 'fa'].includes(lang)) {
		return () => 0;
	}

	if (['ru', 'uk', 'be'].includes(lang)) {
		return (n) => {
			if (n % 10 === 1 && n % 100 !== 11) {
				return 0;
			}

			if (n % 10 >= 2 && n % 10 <= 4 && !(n % 100 >= 12 && n % 100 <= 14)) {
				return 1;
			}

			return 2;
		};
	}

	if (lang === 'pl') {
		return (n) => {
			if (n === 1) {
				return 0;
			}

			if (n % 10 >= 2 && n % 10 <= 4 && !(n % 100 >= 12 && n % 100 <= 14)) {
				return 1;
			}

			return 2;
		};
	}

	if (lang === 'cs' || lang === 'sk') {
		return (n) => {
			if (n === 1) {
				return 0;
			}

			return n >= 2 && n <= 4 ? 1 : 2;
		};
	}

	if (lang === 'ar') {
		return (n) => {
			if (n === 0 || n === 1 || n === 2) {
				return n;
			}

			if (n % 100 >= 3 && n % 100 <= 10) {
				return 3;
			}

			return n % 100 >= 11 ? 4 : 5;
		};
	}

	return (n) => (n === 1 ? 0 : 1);
}
