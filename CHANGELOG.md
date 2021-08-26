# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0-beta.1] - 2021-08-27
### Added
- Preliminary support for Extensions
- Support filtering by nested relationships/attributes (eg. `filter[relationship.attribute]=value`)
- Add new methods to Context object: `getApi`, `getPath`, `fieldRequested`, `meta`
- Eloquent adapter: apply scopes when including polymorphic relationships
- Laravel validation helper: support nested validation messages
- Allow configuration of sort and filter visibility
- Add new `setId` method to `AdapterInterface`

### Changed
- Change paradigm for eager loading relationships; allow fields to return `Deferred` values to be evaluated after all other fields, so that resource loading can be buffered.
- Remove `on` prefix from field event methods

### Removed
- Removed `load` and `dontLoad` field methods

### Fixed
- Fix pagination next link appearing when it shouldn't

[Unreleased]: https://github.com/tobyzerner/json-api-models/compare/v0.2.0-beta.1...HEAD
[0.2.0-beta.1]: https://github.com/tobyzerner/json-api-models/compare/v0.2.0-beta.1...v0.1.0-beta.1
