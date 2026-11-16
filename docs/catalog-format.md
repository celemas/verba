# Catalog format

Verba catalogs are trusted PHP files containing one domain and locale. They keep ordinary messages in a direct map and contextual messages in nested context maps; together, those sections represent one logical catalog keyed by `(context|null, message id)`.

## File naming and structure

Store each catalog as `<domain>.<locale>.php` in the directory configured for its domain. For example, the `shop` domain in German uses `i18n/shop.de.php`.

A complete catalog can contain these sections:

```php
<?php

declare(strict_types=1);

return [
    'plural' => 'de',
    'messages' => [
        'Save' => 'Speichern',
        'one file' => ['eine Datei', '%d Dateien'],
        'Not translated yet' => null,
    ],
    'contexts' => [
        'menu' => [
            'Open' => 'Öffnen',
        ],
        'state' => [
            'Open' => 'Offen',
        ],
        'inventory' => [
            'one item' => ['ein Artikel', '%d Artikel'],
        ],
    ],
    'obsolete' => [
        'Old message' => 'Alte Nachricht',
    ],
    'obsolete_contexts' => [
        'menu' => [
            'Old command' => 'Alter Befehl',
        ],
    ],
];
```

A newly created empty catalog contains only `messages`. The other sections are optional and appear when configured or populated.

Catalog files are executable PHP. Treat them as trusted, author-controlled code and never populate them directly from request data or untrusted translation uploads.

## Top-level sections

### `extends`

The optional `extends` string names one parent locale in the **same domain and directory**. Omit it or use `null` for no parent. It is a locale ID, not a filename or path; ASCII letters, digits, hyphens, and underscores are allowed.

```php
// app.en_US.php
return [
    'extends' => 'en_GB',
    'messages' => [
        'Choose a colour' => 'Choose a color',
    ],
];
```

This catalog inherits from `app.en_GB.php`, which may itself extend another locale. Different domains may choose different parents for the same locale. There is no implicit parent based on the locale's spelling and no multiple inheritance.

Within each domain, `Translator` resolves:

1. The requested locale and its parent chain.
2. Each configured fallback locale and its parent chain, in order.
3. Only then, the next domain in the cascade.

If all domains miss, the original message ID is returned. A pinned-domain lookup searches only that domain.

Missing entries, `null`, and empty plural lists fall through. An empty string is a translation and overrides the parent. Contexts are matched exactly. Plural lists are inherited as whole messages, never merged form by form; the supplying catalog's plural rule applies. Singular lookups skip plural lists, as they do with runtime fallbacks.

Both runtime and tooling reject invalid parent IDs, missing parent files, and inheritance cycles with `UnexpectedValueException`. The runtime reads and validates a locale's parent chain when a lookup first reaches that locale in a domain: the requested locale's chain on the first lookup, a fallback locale's chain only once the catalogs before it lack the message. `exportMany()` reads and validates the whole chain. A missing primary or runtime fallback catalog still behaves as an empty catalog; an explicitly declared parent must exist. Tooling makes one exception: a parent listed in the domain's configured `locales` may lack a file, because sync creates it. Until then sync and status read it as an empty catalog.

`Catalog::load()` reads one file and exposes its `extends` property; it does not resolve parents itself, so the loaded catalog uses its own plural rule until `Catalog::inherit()` applies a parent's. `CatalogFile::load()` likewise reads a single file for editing. `Translator` and the sync/status tooling resolve the chains.

### `plural`

The optional `plural` string selects a built-in plural rule. It may be a locale such as `de` or another supported rule identifier. Without it, a catalog with `extends` uses the rule its nearest ancestor declares. When no ancestor declares one either, Verba derives the rule from the catalog locale in the filename, so `pt_BR` extending `pt` keeps the Brazilian rule.

### `messages`

`messages` maps uncontextual message IDs directly to translation values:

```php
'messages' => [
    'Save' => 'Speichern',
],
```

This section is always present in catalogs rendered by `i18n:sync`, even when it is empty.

### `contexts`

`contexts` maps each context to another message map:

```php
'contexts' => [
    'menu' => ['Open' => 'Öffnen'],
    'state' => ['Open' => 'Offen'],
],
```

The context is translator guidance and is never included in output. It is an exact lookup dimension:

- `__('Open')`
- `__p('menu', 'Open')`
- `__p('state', 'Open')`

are three independent messages. A contextual miss does not use an uncontextual translation or another context. An empty-string context is also distinct from no context.

### `obsolete` and `obsolete_contexts`

`i18n:sync` moves messages that disappear from source into their matching obsolete section. These entries preserve completed translations in case a message returns later, but the runtime never loads them.

`i18n:sync --prune` removes both obsolete sections.

## Translation values

Every message map uses the same value types:

| Value          | Meaning                                   |
| -------------- | ----------------------------------------- |
| `string`       | A translated value                        |
| `list<string>` | Plural forms in the selected rule's order |
| `null`         | Known but untranslated                    |
| `[]`           | An untranslated plural entry              |

`i18n:sync` and `i18n:status` reject a catalog containing any other value and name its file and entry. The runtime trusts catalogs and does not check their values when loading them.

Plural calls are keyed by their singular source ID:

```php
__n('one file', '%d files', $count);
__np('inventory', 'one item', '%d items', $count);
```

```php
'messages' => [
    'one file' => ['eine Datei', '%d Dateien'],
],
'contexts' => [
    'inventory' => [
        'one item' => ['ein Artikel', '%d Artikel'],
    ],
],
```

The plural source ID is used only as the untranslated fallback. It is not a second catalog key.

A string is also valid for a plural lookup and is returned for every count. When a plural list has fewer forms than its rule requests, Verba uses its final form. `null` and an empty list continue through locale and domain fallback.

## Numeric IDs and contexts

PHP stores numeric-string array keys such as `'404'` or `'0'` as integers. Verba reads such keys as the corresponding message IDs and contexts, so lookups, synchronization, and export handle them like any other key. Code that iterates catalog maps itself, such as a consumer of `Catalog::export()` or `CatalogFile`, receives these keys as integers and must convert them where it needs strings.

## Why ordinary and contextual messages are split

The split is a Verba serialization choice, not the representation used by gettext PO files.

A PO file stores context as an optional property on each entry:

```po
msgid "Open"
msgstr "Öffnen"

msgctxt "state"
msgid "Open"
msgstr "Offen"
```

Compiled MO catalogs flatten a contextual identity into a composite key equivalent to `<context>\x04<message-id>`. That representation is compact for machines but unsuitable for a PHP file maintained by people: the separator is invisible, easy to damage, and unclear in reviews.

Verba uses separate maps because they provide:

- **Readable keys.** Context names remain visible without encoded separators.
- **A concise common case.** Most messages remain simple `id => translation` rows.
- **Backward compatibility.** Catalogs created before contextual translation keep their existing `messages` structure.
- **Unambiguous identity.** No context and the empty-string context remain different without a reserved sentinel key.
- **Direct lookup.** PHP and JavaScript can address ordinary and contextual entries through associative maps without first indexing a list of records.
- **A direct JSON shape.** Exported browser catalogs can use the same optional `contexts` map.

Conceptually, the sections are not separate catalogs. The logical identity is still:

```text
(domain, locale, context|null, message id)
```

The split only determines how that identity is serialized.

## Alternatives not used

### Composite message keys

A flat map could follow the MO convention:

```php
'messages' => [
    "state\x04Open" => 'Offen',
],
```

This keeps one map but exposes an implementation separator in the source format. It is difficult to read and edit safely, so Verba does not use it.

### Entry records

A list could model context as an optional property:

```php
'entries' => [
    ['id' => 'Open', 'context' => null, 'translation' => 'Öffnen'],
    ['id' => 'Open', 'context' => 'state', 'translation' => 'Offen'],
],
```

This resembles PO semantics but is verbose for hand-maintained catalogs and requires building lookup indexes at load time.

### A sentinel context

All messages could live under one context map using a reserved key such as `default`. That key could collide with a real context and would require special handling to distinguish no context from an empty-string context.

## Synchronization and canonical rendering

`i18n:sync` owns catalog layout. It:

1. Adds newly extracted ordinary and contextual messages as `null`.
2. Preserves existing translations.
3. Restores returning messages from the corresponding obsolete section.
4. Parks vanished messages in the corresponding obsolete section.
5. Sorts context names and message IDs with bytewise string ordering.
6. Omits empty `contexts`, `obsolete`, and `obsolete_contexts` sections.

For catalogs with `extends`, sync preserves the declaration and keeps the variant sparse. A source message translated by an ancestor needs no local placeholder; existing `null` and empty-list placeholders covered by ancestors are removed. Uncovered messages still receive placeholders. Explicit local translations remain, even when identical to a parent, and returning obsolete translations are restored before considering inheritance. Obsolete parent entries do not count as translations.

Sync validates every configured locale's chain before writing files, then processes configured parents before children regardless of locale order. A configured parent without a file is created like any other configured locale. Parents outside the domain's configured `locales` are read and validated, but never rewritten. Sync's `total` counts the variant's local active entries, not inherited ones.

Status counts locally `translated` and `inherited` source messages separately; together with `missing` and `untranslated`, they sum to `total`. Inherited messages do not appear in gap locations and satisfy `--strict`. Obsolete counts remain local to each catalog. A plural list cannot cover a singular source call. Runtime fallback locales are not considered by sync or status: only explicit catalog inheritance supplies tooling coverage.

The writer rewrites the complete file deterministically. Do not rely on manually added comments, custom top-level fields, or hand-chosen ordering surviving synchronization.

## Runtime export

`Catalog::export()`, `Translator::export()`, and `Translator::exportMany()` produce JSON-ready data with:

- `plural`
- `messages`
- optional `contexts`

`Catalog::export()` and `Translator::export()` remain single-catalog exports: neither includes inherited translations or an `extends` field. `Translator::export()` names the plural rule the catalog declares or inherits; `Catalog::export()` knows only its own file. Flattening parents into one plural rule would change translation behavior. Use `Translator::exportMany()` for the complete resolution chain; it expands both inheritance and runtime fallbacks into ordered entries with the existing payload shape and each supplying catalog's plural rule. The JavaScript runtime needs no parent lookup or inheritance configuration.

All exports omit `null`, empty plural lists, and both obsolete sections. `Translator::exportMany()` also removes parent and fallback entries that can no longer be reached, tracking ordinary IDs and each `(context, ID)` pair independently. A locale-specific payload entry is removed only when both its ordinary and contextual maps are empty.

`json_encode()` turns a map whose keys are exactly `0` to `n-1` into a JSON array. This includes an empty map. The JavaScript runtime reads such arrays as maps.
