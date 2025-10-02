# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0-beta.6] - 2025-10-02

### ⚠️ Breaking Changes

- Change `Resource\Paginatable::paginate()` signature to accept the resolved
  offset and limit integers (plus the request context) and return the page of
  results instead of mutating the query in place
- New contract for custom `Pagination` implementations:
    - Add `paginate(object $query, Context $context): Page` method which should
      return a page of results
    - Remove `meta` and `links` methods; set this data in `paginate` via the
      `Context` object
- `Resource\\Listable` implementations must now provide `defaultSort()` and
  `pagination()` methods so defaults can be reused when listing related data
- Refactor `Serializer` so it is instantiated without a `Context` and expects
  the resource context to be provided to `addPrimary()` / `addIncluded()`

### Added

- Add cursor pagination support via `Endpoint\Index::cursorPaginate()` and the
  `Resource\CursorPaginatable` contract, following the
  `ethanresnick/cursor-pagination` JSON:API profile
- Allow exceptions to attach `meta` and `links` members to the error response
- Add `Context::$documentMeta` and `Context::$documentLinks` as `ArrayObject`
  instances to allow callbacks to add meta information to the response document
- Support JSON:API relationship URLs via the `Show` endpoint, adding automatic
  `self` / `related` links, and paginated/filterable/sortable to-many responses
  when the related resource is `Listable`
- Allow the `Update` endpoint to handle `PATCH` / `POST` / `DELETE` requests to
  relationship URLs and return the updated relationship document
- Implement the `Resource\Attachable` contract and add `ToMany::attachable()`,
  `validateAttach()`, and `validateDetach()` helpers for controlling
  relationship mutation endpoints with attach/detach hooks
- Introduce `Endpoint\ResourceEndpoint` and `Endpoint\RelationshipEndpoint` so
  endpoints can contribute relationship and resource links during serialization

## [1.0.0-beta.5] - 2025-09-27

### ⚠️ Breaking Changes

- `Tobyz\JsonApiServer\Laravel\Filter\EloquentFilter` renamed to `ColumnFilter`
- Remove the `Has` Laravel filter; use `WhereExists` instead
- Remove the `WhereDoesntHave` Laravel filter; use the operator support on
  `WhereHas` instead
- Remove `Where::asNumber()`; express numeric comparisons with operators such as
  `filter[score][gt]=...` or `filter[score][lte]=...`

### Added

- Add support for boolean filter groups (`filter[and]`, `filter[or]`,
  `filter[not]`) for resources that implement the `SupportsBooleanFilters`
  interface
- Laravel: Add support for boolean filter groups to `EloquentResource`
- Laravel: Overhaul filter implementations to support operators like `eq`, `ne`,
  `in`, `lt`, `lte`, `gt`, `gte`, `like`, `notlike`, `null`, and `notnull`

## [1.0.0-beta.4] - 2025-05-02

### Added

- Add support for generating OpenAPI Definitions
- Add `CollectionAction` and `ResourceAction` endpoints
- Allow including relationships by default
  ([#96](https://github.com/tobyzerner/json-api-server/pull/96) by @SychO9)
- Add support for conditional linkage
  ([#110](https://github.com/tobyzerner/json-api-server/pull/110) by @SychO9)
- Add `Enum` support to `Str` type
- Add the ability to make a field `sparse` by default
- Add the ability to add `after` callbacks to the `Create` endpoint
- Add the ability to pass a condition to `writableOnCreate`
- Add `Relationship::notIncludable()`
- Allow `psr/http-message` v2
- Laravel: Add `ToOne` and `ToMany` subclasses which support constraining
  relationship queries
  ([#103](https://github.com/tobyzerner/json-api-server/pull/103) by @SychO9)
- Laravel: Support array of abilities in `can` helper

### Changed

- Laravel: `EloquentCollection` queries now keep the resource's
  `Eloquent\Builder` instead of converting to the base `Query\Builder`
- Laravel: Apply `EloquentResource` scope when applying `WhereHas` filter

### Fixed

- Fix error when request body `data.attributes` is null
  ([#98](https://github.com/tobyzerner/json-api-server/pull/98) by @SychO9)
- Fix support for nested includes on polymorphic relationships
- Fix `multipleOf` bugs
  ([#114](https://github.com/tobyzerner/json-api-server/pull/114) by
  @bertramakers)
- Fix error when using `Context::withRequest`
  ([#107](https://github.com/tobyzerner/json-api-server/pull/107) by @SychO9)
- The `self` link for a resource now only appears if the `Show` endpoint is
  present in a collection
- Laravel: Support dynamically resolved relationships
  ([#100](https://github.com/tobyzerner/json-api-server/pull/100) by @SychO9)
- Laravel: Fix serialization for `MorphTo` relationships
  ([#97](https://github.com/tobyzerner/json-api-server/pull/97) by @SychO9)
- Laravel: Only attempt to preload related `EloquentResource`s
- Laravel: Fix error parameter name in `WhereBelongsTo` filter
- Laravel: Fix limiting `ToMany` relation results
  ([#112](https://github.com/tobyzerner/json-api-server/pull/112) by @SychO9)

## [1.0.0-beta.3] - 2023-12-09

### ⚠️ Breaking Changes

- Drop `Interface` suffix from various interfaces:
    - `Resource` class renamed to `AbstractResource`
    - `ResourceInterface` renamed to `Resource`
    - `CollectionInterface` renamed to `Collection`
    - `ErrorProviderInterface` renamed to `ErrorProvider`
    - `PaginationInterface` renamed to `Pagination`
    - `EndpointInterface` renamed to `Endpoint`
    - `TypeInterface` renamed to `Type`

### Fixed

- Call user-defined serializer before type serializer
  ([#91](https://github.com/tobyzerner/json-api-server/issues/91))
- Fix finding resource when creating polymorphic relationship
  ([#93](https://github.com/tobyzerner/json-api-server/issues/93))
- Prevent relationship value from being retrieved if it won't be used (not
  included and no linkage)

## [1.0.0-beta.2] - 2023-12-02

### ⚠️ Breaking Changes

- Types are now their own construct instead of being subclasses of `Attribute`.
  See the
  [Attributes](https://tobyzerner.github.io/json-api-server/attributes.html)
  documentation for more information.
- Removed support for defining polymorphic relationships by passing a map of
  model classes to resource types. You should use heterogeneous collections
  instead. See the
  [Relationships](https://tobyzerner.github.io/json-api-server/relationships.html#polymorphic-relationships)
  documentation for more information.

### Added

- Add support for
  [heterogeneous collections](https://tobyzerner.github.io/json-api-server/collections.html)
- Add `Arr` type for defining array attributes
  ([#88](https://github.com/tobyzerner/json-api-server/pull/88) by
  @bertramakers)
- Laravel: Allow `WhereHas` field to be specified manually

### Fixed

- When creating a resource, set the context model prior to field validation
- Fix error when updating a resource with a conflicting ID
  ([#85](https://github.com/tobyzerner/json-api-server/issues/85))
- Laravel: Fix `Has` filter not working without a `scope`
- Laravel: Don't apply relationship loading constraints if there aren't any

## [1.0.0-beta.1] - 2023-09-24

### Added

- Add `Str::enum()` method
  ([#75](https://github.com/tobyzerner/json-api-server/pull/75) by
  @bertramakers)
- Allow literal values in `Field::default()` method
  ([#80](https://github.com/tobyzerner/json-api-server/pull/80) by
  @bertramakers)

### Fixed

- Fix `Number` properties not being initialized
- Fix validators not being run for null values
- Fix `DateTime` values containing milliseconds not being accepted
- Fix nested filters not receiving correct resource in context
- Laravel: Fix `EloquentResource` sometimes using incorrect relation name when
  setting value
- Laravel: Convert `DateTime` values to Laravel app's storage timezone
- Laravel: Validate that `WhereBelongsTo` filter input is a list

## [1.0.0-alpha.2] - 2023-08-19

### Added

- Finish Laravel integration
- Add basic field schema configuration in preparation for OpenAPI generation
- Add `Context::$query` to access the query used in the `Index` endpoint
- Add `Context::fieldRequested()` and `Context::sortRequested()` methods
- Add `BooleanDateTime` attribute for exposing internal date-time values as
  booleans
- Improve error sources in Bad Request errors
- Add a performance benchmark

### Changed

- Add `void` return type to `Filter::apply()` signature

### Fixed

- Fix typed attribute values being deserialized and always passing validation
- Fix visibility callback result not being cast to a boolean
- Fix `Integer` incorrectly not extending `Number`
- Fix empty to-many relationships not being present in the response at all
- Fix TypeError when removing non-nullable to-one relationship
  ([#74](https://github.com/tobyzerner/json-api-server/issues/74) by
  @bertramakers)

## [1.0.0-alpha.1] - 2023-06-21

- **New class-based API.** More ergonomic for managing large resource
  definitions and inheriting/overriding behavior. Complex fields can be
  extracted into their own classes and reused across resources.

- **Typed attributes.** Implementations of typed attributes are provided to
  match the data types in the OpenAPI specification. Attributes can be marked as
  required and nullable.

- **Customizable endpoints.** Each endpoint is now opt-in for each resource and
  can be configured and implemented separately. Also adds the ability for custom
  endpoints to be added.

- **Restructured internals.** The codebase is cleaner and easier to reason
  about, especially the serialization process.

Still to come:

- Implementation of Laravel stuff (currently it is documented but not
  implemented)
- Ability to generate OpenAPI definitions
- Additional attribute types (array, object)
- Benchmarks

## [0.2.0] - 2022-06-21

### Fixed

- Fix `EloquentAdapter::filterByIds()` getting key name from query model instead
  of adapter model
- Fix deprecation notice on PHP 8.1

## [0.2.0-beta.6] - 2022-04-22

### Changed

- Add support for `doctrine/inflector:^2.0`

## [0.2.0-beta.5] - 2022-01-03

### Added

- `Context::getBody()` method to retrieve the parsed JSON:API payload from the
  request
- `Context::sortRequested()` method to determine if a sort field has been
  requested

### Fixed

- `Laravel\rules()`: Fix regression disallowing use of advanced validation rules
  like callbacks and `Rule` instances. (@SychO9)

## [0.2.0-beta.4] - 2021-09-05

### Added

- `Laravel\rules()`: Replace `{id}` placeholder in rules with the model's key.
    - This is useful for the `unique` rule, for example:
      `unique:users,email,{id}`
- `Laravel\can()`: Pass through additional arguments to Gate check.
    - This is needed to use policy methods without models, for example:
      `can('create', Post::class)`

### Changed

- Get a fresh copy of the model to display after create/update to ensure
  consistency
- Respond with `400 Bad Request` when attempting to filter on an attribute of a
  polymorphic relationship

## [0.2.0-beta.3] - 2021-09-03

### Fixed

- Fix dependency on `http-accept` now that a version has been tagged
- Change `EloquentAdapter` to load relationships using `load` instead of
  `loadMissing`, as they may need API-specific scopes applied

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
- `Forbidden` error details for CRUD actions, useful when running Atomic
  Operations
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
- Support filtering by nested relationships/attributes (eg.
  `filter[relationship.attribute]=value`)
- Add new methods to Context object: `getApi`, `getPath`, `fieldRequested`,
  `meta`
- Eloquent adapter: apply scopes when including polymorphic relationships
- Laravel validation helper: support nested validation messages
- Allow configuration of sort and filter visibility
- Add new `setId` method to `AdapterInterface`

### Changed

- Change paradigm for eager loading relationships; allow fields to return
  `Deferred` values to be evaluated after all other fields, so that resource
  loading can be buffered.
- Remove `on` prefix from field event methods

### Removed

- Removed `load` and `dontLoad` field methods

### Fixed

- Fix pagination next link appearing when it shouldn't

[1.0.0-beta.6]: https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.5...v1.0.0-beta.6
[1.0.0-beta.5]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.4...v1.0.0-beta.5
[1.0.0-beta.4]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.3...v1.0.0-beta.4
[1.0.0-beta.3]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.2...v1.0.0-beta.3
[1.0.0-beta.2]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.1...v1.0.0-beta.2
[1.0.0-beta.1]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-alpha.2...v1.0.0-beta.1
[1.0.0-alpha.2]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-alpha.1...v1.0.0-alpha.2
[1.0.0-alpha.1]:
    https://github.com/tobyzerner/json-api-server/compare/v0.2.0...v1.0.0-alpha.1
[0.2.0]:
    https://github.com/tobyzerner/json-api-server/compare/v0.2.0...v0.2.0-beta.6
[0.2.0-beta.6]:
    https://github.com/tobyzerner/json-api-server/compare/v0.2.0-beta.6...v0.2.0-beta.5
[0.2.0-beta.5]:
    https://github.com/tobyzerner/json-api-server/compare/v0.2.0-beta.5...v0.2.0-beta.4
[0.2.0-beta.4]:
    https://github.com/tobyzerner/json-api-server/compare/v0.2.0-beta.4...v0.2.0-beta.3
[0.2.0-beta.3]:
    https://github.com/tobyzerner/json-api-server/compare/v0.2.0-beta.3...v0.2.0-beta.2
[0.2.0-beta.2]:
    https://github.com/tobyzerner/json-api-server/compare/v0.2.0-beta.2...v0.2.0-beta.1
[0.2.0-beta.1]:
    https://github.com/tobyzerner/json-api-server/compare/v0.2.0-beta.1...v0.1.0-beta.1
[unreleased]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-alpha.2...HEAD
[unreleased]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.1...HEAD
[unreleased]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.2...HEAD
[unreleased]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.3...HEAD
[unreleased]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.4...HEAD
[unreleased]:
    https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.5...HEAD

[unreleased]: https://github.com/tobyzerner/json-api-server/compare/v1.0.0-beta.6...HEAD
