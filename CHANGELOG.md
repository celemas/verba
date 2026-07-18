# Changelog

## [Unreleased](https://codeberg.org/celema/verba/compare/0.2.1...HEAD)

### Changed

- Renamed the Composer package to `celema/verba` and moved PHP classes from `Celemas\Verba` to `Celema\Verba`.
- Renamed the npm package to `@celema/verba`.
- Replaced the command dependency with `celema/console:^0.3` and moved command integrations from `Celemas\Cli` to `Celema\Console`.

### Removed

- Removed the previous Composer package name, npm package name, and PHP namespaces; consumers must update their dependencies and imports.

## [0.2.1](https://codeberg.org/celema/verba/src/tag/0.2.1) (2026-07-14)

### Changed

- Improved internal maintainability and code-quality checks without changing public behavior.

## [0.2.0](https://codeberg.org/celema/verba/src/tag/0.2.0) (2026-07-14)

### Added

- Added `loadAndActivate()` for one-step JavaScript catalog loading and activation.
- Added contextual translation across PHP and JavaScript with `__p`, `__np`, `__dp`, and `__dnp`, including nested catalog storage, extraction, synchronization, status reporting, locale fallback, and browser payload export.
- Added a catalog format reference covering the schema, synchronization behavior, gettext differences, and design rationale.

## [0.1.0](https://codeberg.org/celema/verba/src/tag/0.1.0) (2026-07-13)

Initial version.

### Added

- Added gettext-style runtime translation with global `__()`, `__n()`, `__d()`, and `__dn()` helpers, domain cascades, plural handling, interpolation, and safe fallback behavior when no translator is active.
- Added PHP-array catalogs with built-in plural rules, optional per-catalog plural overrides, untranslated and obsolete entries, and JSON-ready export through `Catalog::export()`, `Translator::export()`, and `Translator::exportMany()`.
- Added ordered per-message locale fallback within each domain, with matching JavaScript payload resolution and locale-specific plural rules through `Translator::exportMany()`.
- Added the `@celemas/verba` npm package: a dependency-free JavaScript runtime for exported catalogs with the four translation functions, built-in plural rules, named `:placeholder` interpolation, and an inline JSON catalog loader.
- Added source extraction for PHP, JavaScript, TypeScript, JSX, TSX, Svelte, and Vue that captures nested literal translation calls, including JavaScript template interpolations, and warns about dynamic ids and conflicting plural usages.
- Added atomic catalog synchronization and status reporting through `i18n:sync` and `i18n:status`, including translation preservation, obsolete-id handling, strict CI checks, and source-location reports.
