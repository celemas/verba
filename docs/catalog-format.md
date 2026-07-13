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

### `plural`

The optional `plural` string selects a built-in plural rule. It may be a locale such as `de` or another supported rule identifier. Without it, Verba derives the rule from the catalog locale in the filename.

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

The writer rewrites the complete file deterministically. Do not rely on manually added comments, custom top-level fields, or hand-chosen ordering surviving synchronization.

## Runtime export

`Catalog::export()` and `Translator::exportMany()` produce JSON-ready data with:

- `plural`
- `messages`
- optional `contexts`

They omit `null`, empty plural lists, and both obsolete sections. `Translator::exportMany()` also removes fallback entries that can no longer be reached, tracking ordinary IDs and each `(context, ID)` pair independently. A locale-specific payload entry is removed only when both its ordinary and contextual maps are empty.
