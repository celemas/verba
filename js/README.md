# Celemas Verba — JavaScript runtime

The browser half of [Celemas Verba](../README.md): a dependency-free ESM
runtime for catalogs exported by the PHP `Translator`. Verba's
`JavascriptScanner` extracts the same four calls from `.js`, `.ts`, `.jsx`,
`.tsx`, `.svelte`, and `.vue` sources, so marked strings flow through
`i18n:sync` exactly like PHP ones.

## Usage

Inline the payload from `Translator::exportMany()` server-side and boot once
per page:

```html
<script id="verba-catalog" type="application/json">
	{
		"locale": "de",
		"domains": [{ "domain": "app", "plural": "de", "messages": { "Save": "Speichern" } }]
	}
</script>
```

```js
import { __, __n, activate, load } from '@celemas/verba';

const translator = load(); // reads #verba-catalog, null during SSR
if (translator) activate(translator);

__('Save'); // 'Speichern'
__('Hello :name', { name: 'Ada' }); // named args only — no sprintf in JS
__n(':count file', ':count files', 3); // ':count' is bound automatically
```

The semantics mirror PHP: bare `__`/`__n` walk the domain cascade in payload
order, `__d`/`__dn` pin one domain, and a miss falls back to the message id.
With no translator active the functions return the interpolated id, so calls
are safe during SSR and in tests.

Only positional `sprintf` interpolation is PHP-only; strings shared with the
frontend should use named `:placeholder` arguments.

## Scripts

- `pnpm test` runs the Vitest suite.
- `pnpm check` type-checks and verifies formatting.
- `pnpm build` emits ESM and type declarations to `dist/`.

## License

This project is licensed under the [MIT license](LICENSE.md).
