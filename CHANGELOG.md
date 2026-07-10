# Changelog

## [Unreleased](https://codeberg.org/celemas/verba/commits/branch/main)

Initial version.

### Added

- Added gettext-style runtime translation with global `__()`, `__n()`, `__d()`, and `__dn()` helpers, domain cascades, plural handling, interpolation, and safe fallback behavior when no translator is active.
- Added per-id locale fallback: a `Translator` accepts an ordered list of fallback locales, tried within each domain when the primary locale lacks a string, before the message id. `Translator::exportMany()` ships ordered, locale-specific payload entries with their own plural rules, omitting entries with no reachable messages, so the JavaScript runtime resolves the same chain. Locale ids are restricted to ASCII letters, digits, hyphens, and underscores; empty plural-form lists count as untranslated in runtime, export, and status checks; and extraction and status stay per catalog file, so fallback never masks untranslated ids.
- Added PHP-array catalogs with built-in plural rules, optional per-catalog plural overrides, untranslated entries, obsolete entries, and JSON-ready export data through `Catalog::export()`, `Translator::export()`, and `Translator::exportMany()`.
- Added the `@celemas/verba` npm package under `js/`: a dependency-free JavaScript runtime for exported catalogs with the four translation functions, built-in plural rules, named `:placeholder` interpolation, and an inline JSON catalog loader.
- Added source extraction tooling for PHP and JavaScript files (`.js`, `.ts`, `.jsx`, `.tsx`, `.svelte`, and `.vue`) that captures literal translation calls and reports dynamic calls as warnings.
- Added catalog sync and status tooling, plus `i18n:sync` and `i18n:status` commands for maintaining catalogs, preserving existing translations, parking obsolete ids, pruning obsolete entries, strict CI checks, and source-location reports.
- Added README usage documentation, Forgejo CI, badges, and repository metadata.

### Fixed

- Fixed `i18n:status` so parked obsolete catalog entries count toward obsolete totals and strict checks.
- Fixed extraction edge cases for fully qualified PHP helper calls, JavaScript Unicode escapes, regex literals, Vue templates containing URLs, and conflicting plural call sites.
- Fixed docs and Psalm Composer scripts so local aggregate checks run against existing files only.
