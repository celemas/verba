# Changelog

## [Unreleased](https://codeberg.org/celemas/verba/compare/0.1.0...HEAD)

No notable changes since the last release.

## [0.1.0](https://codeberg.org/celemas/verba/src/tag/0.1.0) (2026-07-13)

Initial version.

### Added

- Added gettext-style runtime translation with global `__()`, `__n()`, `__d()`, and `__dn()` helpers, domain cascades, plural handling, interpolation, and safe fallback behavior when no translator is active.
- Added PHP-array catalogs with built-in plural rules, optional per-catalog plural overrides, untranslated and obsolete entries, and JSON-ready export through `Catalog::export()`, `Translator::export()`, and `Translator::exportMany()`.
- Added ordered per-message locale fallback within each domain, with matching JavaScript payload resolution and locale-specific plural rules through `Translator::exportMany()`.
- Added the `@celemas/verba` npm package: a dependency-free JavaScript runtime for exported catalogs with the four translation functions, built-in plural rules, named `:placeholder` interpolation, and an inline JSON catalog loader.
- Added source extraction for PHP, JavaScript, TypeScript, JSX, TSX, Svelte, and Vue that captures nested literal translation calls, including JavaScript template interpolations, and warns about dynamic ids and conflicting plural usages.
- Added atomic catalog synchronization and status reporting through `i18n:sync` and `i18n:status`, including translation preservation, obsolete-id handling, strict CI checks, and source-location reports.
