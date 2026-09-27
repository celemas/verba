/** @typedef {import('./interpolate.js').Args} Args */
/** @typedef {import('./plurals.js').PluralRule} PluralRule */
/** @typedef {import('./translator.js').Contexts} Contexts */
/** @typedef {import('./translator.js').DomainPayload} DomainPayload */
/** @typedef {import('./translator.js').Messages} Messages */
/** @typedef {import('./translator.js').Payload} Payload */

export { interpolate } from './interpolate.js';
export { pluralRule } from './plurals.js';
export { Translator } from './translator.js';
export {
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
} from './verba.js';
