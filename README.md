# Celemas Verba

<!-- prettier-ignore-start -->
[![ci](https://codeberg.org/celemas/verba/badges/workflows/ci.yml/badge.svg?style=flat&logo=codeberg&logoColor=white&label=ci)](https://codeberg.org/celemas/verba/actions)
[![code coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celemas.dev%2Fcelemas%2Fverba%2Fcode%2Fbadge.json)](https://cov.celemas.dev/celemas/verba/code)
[![type coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celemas.dev%2Fcelemas%2Fverba%2Ftypes%2Fbadge-cover.json)](https://cov.celemas.dev/celemas/verba/types)
[![psalm level](https://img.shields.io/endpoint?url=https%3A%2F%2Fcov.celemas.dev%2Fcelemas%2Fverba%2Ftypes%2Fbadge-level.json)](https://cov.celemas.dev/celemas/verba/types)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)
<!-- prettier-ignore-end -->

Gettext-style internationalization for PHP without the `gettext` extension.

Verba keeps the workflow you know from gettext — mark strings in code, extract them, translate, ship — but drops the parts that make the extension awkward to operate:

- **No server restarts.** Catalogs are plain PHP array files served from opcache; editing one takes effect on the next request.
- **No system locales.** Plural rules ship with Verba; nothing to install with `dpkg-reconfigure locales`.
- **Works in CLI and CI.** Extraction and status checks are pure PHP.

The interchange format is plain PHP arrays, not `.po`/`.mo`, and there is no fuzzy matching: a changed message id is simply a new entry.

Verba translates messages; it does not format numbers, dates, or currency. That is localization, and PHP already has the ICU-backed tool for it — `ext-intl` (`NumberFormatter`, `IntlDateFormatter`, `MessageFormatter`), the PHP counterpart to Babel. Verba deliberately stays out of it: formatting needs CLDR data and the intl extension, which would undo its "no extension, no system locales" premise. Pair the two when you need both.

## Marking strings

Eight global functions are always available:

```php
__('Save');                                      // simple
__n('one file', '%d files', $count);             // plural
__p('button', 'Open');                           // context
__np('inventory', 'one file', '%d files', $count);
__d('shop', 'Checkout');                         // explicit domain
__dn('shop', 'one order', '%d orders', $count);
__dp('shop', 'button', 'Open');                  // domain + context
__dnp('shop', 'inventory', 'one item', '%d items', $count);
```

Arguments interpolate in one of two styles. A single array argument fills named `:placeholder` tokens; anything else is passed to `sprintf`:

```php
__('Hello :name', ['name' => $user->name]);
__('Found %d results', $count);
```

In `__n`/`__np`/`__dn`/`__dnp`, `:count` is bound to the count automatically, so `__n(':count file', ':count files', $n)` needs no extra argument.

A context distinguishes uses of the same message id without becoming part of the output. For example, German may translate `__p('menu', 'Open')` as `Öffnen` but `__p('state', 'Open')` as `Offen`. Context is an exact lookup axis: a miss never falls back to an uncontextual translation or another context.

With no translator active the functions return the message id itself (after interpolation), so calls are safe in tests, CLI, and early boot.

### Escaping

Verba does not escape anything — it cannot, since the same catalog feeds HTML, attributes, JSON, and the terminal, and only the boundary that emits a string knows the right encoding. Two rules follow:

- **Translations are trusted, author-controlled content.** A `msgstr` may contain markup (`<strong>`, links) on purpose; Verba passes it through untouched. Your template decides whether the result is rendered as raw HTML.
- **Placeholder values are inserted verbatim.** A `:name` value goes in as-is, so escape untrusted input for its output context at the call site: `__('Hi :name', ['name' => htmlspecialchars($user->name)])` for HTML, and no escaping for CLI.

## Runtime

A `Translator` is bound to one locale and an ordered cascade of domains, each mapping to the directory that holds its catalog files. The first domain with a translation wins; a miss falls back to the message id.

```php
use Celemas\Verba\Translator;
use Celemas\Verba\Verba;

$translator = new Translator('de', [
    'app' => __DIR__ . '/i18n',       // application strings, searched first
    'cosray' => $cosrayDir . '/i18n', // framework strings
]);

Verba::activate($translator);         // wire the global functions
// ... handle the request ...
Verba::deactivate();                  // reset (matters for long-running workers)
```

`__d('cosray', …)` and the other `__d*` helpers pin the `cosray` domain; bare helpers search the cascade. A domain selects a catalog, while a context selects a translation variant inside each catalog.

A third argument names fallback locales, tried in order whenever the primary locale lacks a string. Resolution is per id and stays within a domain before the cascade continues, so the domain cascade outranks the locale fallback:

```php
$translator = new Translator('es', $domains, ['en', 'de']); // es → en → de → id
```

Locale ids may contain ASCII letters, digits, hyphens, and underscores, for example `zh-Hant` or `pt_BR`. They become catalog filename segments.

This keeps a partially translated locale usable — untranslated ids surface in `en` instead of as raw message ids. `Translator::exportMany()` ships locale-specific payload entries in resolution order, each with its own plural rule; entries with no reachable messages are omitted. The JavaScript runtime therefore resolves the same chain. Fallback is a runtime concern only: extraction and `i18n:status` stay per catalog file, so an empty `es` catalog still reports as untranslated.

## Catalog files

One file per domain and locale, named `<domain>.<locale>.php`. See the [catalog format reference](docs/catalog-format.md) for the complete schema and its design rationale.

```php
<?php

declare(strict_types=1);

return [
    'messages' => [
        'Save' => 'Speichern',
        'one file' => ['%d Datei', '%d Dateien'], // plural forms
        'Not translated yet' => null,             // falls back to the id
    ],
    'contexts' => [
        'menu' => ['Open' => 'Öffnen'],
        'state' => ['Open' => 'Offen'],
    ],
    'obsolete' => [
        'Old string' => 'Alter String', // parked by sync, never loaded
    ],
    'obsolete_contexts' => [
        'menu' => ['Old command' => 'Alter Befehl'],
    ],
];
```

- A `string` is a translation, a `list` holds the plural forms in rule order, and `null` marks a known-but-untranslated id.
- `contexts` groups active translations by context; `obsolete_contexts` parks vanished contextual messages. Both use the same value types as `messages`.
- Plural rules for common languages are built in. A catalog may borrow another language's rule with `'plural' => 'ru'`.

## Extraction

Scanners find the calls; a `Domain` ties them to a catalog directory and locale set.

```php
use Celemas\Verba\Tool\Domain;
use Celemas\Verba\Tool\JavascriptScanner;
use Celemas\Verba\Tool\PhpScanner;

$app = new Domain(
    name: 'app',
    dir: __DIR__ . '/i18n',
    locales: ['en', 'de'],
    scanners: [
        new PhpScanner([__DIR__ . '/src', __DIR__ . '/views']),
        new JavascriptScanner([__DIR__ . '/ui/src']),
    ],
    default: true, // also receives bare __()/__n()/__p()/__np() calls
);
```

- **`PhpScanner`** walks the PHP token stream — no parser, no regex — and reads all eight helpers with literal message, domain, context, and plural arguments. Boiler templates are PHP, so they are covered too.
- **`JavascriptScanner`** reads `.js`, `.ts`, `.jsx`, `.tsx`, `.svelte`, and `.vue`. Only literal arguments are captured; a dynamic id, domain, context, or plural is reported as a warning and skipped.

## JavaScript runtime

The [`@celemas/verba`](js/) npm package mirrors the runtime in the browser: the same eight functions, contexts, plural rules, and named `:placeholder` interpolation (positional `sprintf` arguments stay PHP-only). Hand it the catalogs with `Translator::exportMany()`, inlined as JSON — list only domains meant for the browser, since the payload is readable in the page source:

```php
<script id="verba-catalog" type="application/json">
	<?= json_encode($translator->exportMany(['app']), JSON_HEX_TAG) ?>
</script>
```

```js
import { __, __n, __p, loadAndActivate } from '@celemas/verba';

loadAndActivate(); // reads #verba-catalog, returns null during SSR

__('Save');
__p('menu', 'Open');
__n(':count file', ':count files', 3); // ':count' is bound automatically
```

With no translator active the functions return the interpolated message id, mirroring PHP.

## Commands

Register the two commands with your [`celemas/cli`](https://codeberg.org/celemas/cli) runner, passing the domains to maintain:

```php
use Celemas\Verba\Command\StatusCommand;
use Celemas\Verba\Command\SyncCommand;

$commands->add(new SyncCommand([$app]));
$commands->add(new StatusCommand([$app]));
```

- `i18n:sync` — scan sources and reconcile every catalog. New ids are added as untranslated, existing translations are kept, a reappearing id is restored from its obsolete section, and a vanished id is parked there. Running it twice changes nothing. `--prune` drops ordinary and contextual obsolete sections.
- `i18n:status` — report per locale how many ids are missing, untranslated, translated, and obsolete. `--strict` exits non-zero on any gap (a CI gate); `--where` lists the source locations of the gaps.

## License

This project is licensed under the [MIT license](LICENSE.md).
