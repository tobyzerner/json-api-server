# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0-beta.4]
### Added
- `Laravel\rules()`: Replace `{id}` placeholder in rules with the model's key.
  - This is useful for the `unique` rule, for example: `unique:users,email,{id}`
- `Laravel\can()`: Pass through additional arguments to Gate check.
  - This is needed to use policy methods without models, for example: `can('create', Post::class)`

### Changed
- Get a fresh copy of the model to display after create/update to ensure consistency
- Respond with `400 Bad Request` when attempting to filter on an attribute of a polymorphic relationship

## [0.2.0-beta.3] - 2021-09-03
### Fixed
- Fix dependency on `http-accept` now that a version has been tagged
- Change `EloquentAdapter` to load relationships using `load` instead of `loadMissing`, as they may need API-specific scopes applied

## [0.2.0-beta.2] - 2021-09-01
### Added
- Content-Type validation and Accept negotiation
- Include `jsonapi` object with `version` member in response
- Validate implementation-specific query parameters according to specification
- Added `Location` header to `201 Created` responses
- Improved error responses when creating and updating resources
- `Context::filter()` method to get the value of a filter
- `ResourceType::applyScope()`, `applyFilter()` and `applySort()` methods
- `ResourceType::url()` method to get the URL for a model
- `Forbidden` error details for CRUD actions, useful when running Atomic Operations
- `JsonApi::getExtensions()` method to get all registered extensions
- `ConflictException` class

### Changed
- Renamed `$linkage` parameter in `AdapterInterface` methods to `$linkageOnly`
- Renamed `Type::newModel()` to `model()` to be consistent with Adapter

### Fixed
- Properly respond with meta information added to `Context` instance

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

[0.2.0-beta.4]: https://github.com/tobyzerner/json-api-models/compare/v0.2.0-beta.4...v0.2.0-beta.3
[0.2.0-beta.3]: https://github.com/tobyzerner/json-api-models/compare/v0.2.0-beta.3...v0.2.0-beta.2
[0.2.0-beta.2]: https://github.com/tobyzerner/json-api-models/compare/v0.2.0-beta.2...v0.2.0-beta.1
[0.2.0-beta.1]: https://github.com/tobyzerner/json-api-models/compare/v0.2.0-beta.1...v0.1.0-beta.1
