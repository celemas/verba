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

## Marking strings

Four global functions are always available:

```php
__('Save');                              // simple
__n('one file', '%d files', $count);     // plural
__d('shop', 'Checkout');                 // explicit domain
__dn('shop', 'one order', '%d orders', $count);
```

Arguments interpolate in one of two styles. A single array argument fills named `:placeholder` tokens; anything else is passed to `sprintf`:

```php
__('Hello :name', ['name' => $user->name]);
__('Found %d results', $count);
```

In `__n`/`__dn`, `:count` is bound to the count automatically, so `__n(':count file', ':count files', $n)` needs no extra argument.

With no translator active the functions return the message id itself (after interpolation), so calls are safe in tests, CLI, and early boot.

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

`__d('cosray', …)` pins the `cosray` domain; bare `__(…)` searches the cascade.

## Catalog files

One file per domain and locale, named `<domain>.<locale>.php`:

```php
<?php

declare(strict_types=1);

return [
    'messages' => [
        'Save' => 'Speichern',
        'one file' => ['%d Datei', '%d Dateien'], // plural forms
        'Not translated yet' => null,             // falls back to the id
    ],
    'obsolete' => [
        'Old string' => 'Alter String',           // parked by sync, never loaded
    ],
];
```

- A `string` is a translation, a `list` holds the plural forms in rule order, and `null` marks a known-but-untranslated id.
- Plural rules for common languages are built in. A catalog may borrow another language's rule with `'plural' => 'ru'`.

## Extraction

Scanners find the calls; a `Domain` ties them to a catalog directory and locale set.

```php
use Celemas\Verba\Tool\Domain;
use Celemas\Verba\Tool\FrontendScanner;
use Celemas\Verba\Tool\PhpScanner;

$app = new Domain(
    name: 'app',
    dir: __DIR__ . '/i18n',
    locales: ['en', 'de'],
    scanners: [
        new PhpScanner([__DIR__ . '/src', __DIR__ . '/views']),
        new FrontendScanner([__DIR__ . '/ui/src']),
    ],
    default: true, // also receives bare __()/__n() calls
);
```

- **`PhpScanner`** walks the PHP token stream — no parser, no regex — and reads `__`/`__n`/`__d`/`__dn` calls with literal string arguments. Boiler templates are PHP, so they are covered too.
- **`FrontendScanner`** reads `.js`, `.ts`, `.jsx`, `.tsx`, `.svelte`, and `.vue`. Only literal arguments are captured; a dynamic id is reported as a warning and skipped.

## Commands

Register the two commands with your [`celemas/cli`](https://codeberg.org/celemas/cli) runner, passing the domains to maintain:

```php
use Celemas\Verba\Command\StatusCommand;
use Celemas\Verba\Command\SyncCommand;

$commands->add(new SyncCommand([$app]));
$commands->add(new StatusCommand([$app]));
```

- `i18n:sync` — scan sources and reconcile every catalog. New ids are added as untranslated, existing translations are kept, a reappearing id is restored from `obsolete`, and a vanished id is parked there. Running it twice changes nothing. `--prune` drops the obsolete section.
- `i18n:status` — report per locale how many ids are missing, untranslated, translated, and obsolete. `--strict` exits non-zero on any gap (a CI gate); `--where` lists the source locations of the gaps.

## License

This project is licensed under the [MIT license](LICENSE.md).
