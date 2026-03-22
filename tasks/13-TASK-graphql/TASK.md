# 13 — GraphQL (Attribute-Based GraphQL Module)

> Build an attribute-driven GraphQL module: `#[ObjectType]`, `#[Field]`, `#[Query]`, `#[Mutation]`, `#[InputType]`, `#[EnumType]` attributes, schema builder with auto-type inference, execution endpoint, guard/pipe/interceptor integration, DataLoader, subscriptions via SSE, and developer tooling

## Dependencies
- Wave 1-3 packages complete
- Packages: `packages/core/`, `packages/module/`, `packages/pipeline/`, `packages/http/`, `packages/compiler/`, `packages/events/`
- Library: `webonyx/graphql-php` as the underlying execution engine
- Optional: `packages/cache/` (for persisted queries)

## Subtasks

### 1. [ ] Core attributes — `#[ObjectType]`, `#[Field]`, `#[Query]`, `#[Mutation]`, `#[InputType]`, `#[EnumType]`

#### `#[ObjectType]` Attribute
- Define `#[ObjectType]` attribute with parameters: name (optional, defaults to class name), description
- Support applying to PHP classes; auto-generate GraphQL object type from class properties and methods
- Support class inheritance (child types inherit parent fields)

#### `#[Field]` Attribute
- Define `#[Field]` attribute with parameters: name, type (override), description, deprecationReason, nullable
- Support applying to class properties (explicit field config) and methods (computed/resolved fields)
- Support field arguments via method parameters
- Default to property name and inferred type when no overrides provided

#### `#[Query]` Attribute
- Define `#[Query]` attribute with parameters: name (optional, defaults to method name), description, deprecationReason
- Support applying to methods on resolver classes
- Auto-extract return type as GraphQL response type; auto-extract method parameters as query arguments
- Support constructor dependency injection in resolver classes and method parameter injection (context, info, arguments)

#### `#[Mutation]` Attribute
- Define `#[Mutation]` attribute with parameters: name (optional, defaults to method name), description
- Support applying to methods on resolver classes
- Auto-extract return type and method parameters for mutation schema generation

#### `#[InputType]` Attribute
- Define `#[InputType]` attribute with parameters: name (optional, defaults to class name), description
- Auto-generate GraphQL input object type from class properties
- Support nested input types and validation rules via pipes

#### `#[EnumType]` Attribute
- Define `#[EnumType]` attribute with parameters: name (optional), description
- Support applying to PHP backed enums (string-backed and int-backed)
- Auto-generate GraphQL enum values from enum cases
- Support `#[EnumValue]` attribute on individual cases for description/deprecation

#### `#[InterfaceType]` and `#[UnionType]`
- Define `#[InterfaceType]` for interfaces/abstract classes with auto-discovery of implementing types
- Define `#[UnionType]` with types parameter and type resolution support

#### Detailed Steps
1. Create `Attributes/ObjectType.php`, `Attributes/Field.php`, `Attributes/Query.php`, `Attributes/Mutation.php`, `Attributes/InputType.php`, `Attributes/EnumType.php`, `Attributes/EnumValue.php`, `Attributes/InterfaceType.php`, `Attributes/UnionType.php`
2. Each attribute is a PHP 8 attribute with `#[Attribute(Attribute::TARGET_CLASS)]` or `TARGET_METHOD`/`TARGET_PROPERTY` as appropriate
3. Create `TypeRegistry` that stores all discovered types and their attribute metadata
4. Unit tests for each attribute with all parameter combinations

#### Verification
- [ ] `#[ObjectType]` on a class produces a valid GraphQL object type with fields from properties
- [ ] `#[Field]` overrides type, description, and deprecation for specific fields
- [ ] `#[Query]` and `#[Mutation]` methods produce root query/mutation fields with arguments
- [ ] `#[InputType]` generates valid input types with nested type support
- [ ] `#[EnumType]` maps PHP backed enums to GraphQL enums

### 2. [ ] Schema builder — discover annotated classes, auto-type inference, build GraphQL schema

#### Auto Type Inference
- Map PHP `int` -> GraphQL `Int`, `float` -> `Float`, `string` -> `String`, `bool` -> `Boolean`
- Map nullable PHP types (`?string`) to nullable GraphQL fields
- Map `array` with docblock `@return Type[]` to GraphQL `[Type]` lists
- Map PHP enums with `#[EnumType]` to GraphQL enum types
- Map PHP classes with `#[ObjectType]` to GraphQL object types
- Handle nested type resolution and circular references
- Support custom scalar types (DateTime, JSON, UUID) via `#[Scalar]` attribute
- Handle union types via docblock annotations or attribute

#### Schema Builder
- Implement `SchemaBuilder` that collects all annotated classes from the compiler
- Build GraphQL schema object from discovered attributes and types
- Generate Query root type from all `#[Query]` methods, Mutation root from all `#[Mutation]` methods, Subscription root from all `#[Subscription]` methods
- Validate schema completeness at compile time: missing types, circular refs, unresolved references
- Support schema caching: serialize/deserialize compiled schema for production

#### Detailed Steps
1. Create `TypeInference/TypeMapper.php` with `mapPhpType(ReflectionType $type): GraphQLType` method
2. Create `TypeInference/DocblockParser.php` for extracting array item types and union types from docblocks
3. Create `Schema/SchemaBuilder.php` that: scans compiled attributes, builds type map, resolves references, constructs `webonyx/graphql-php` Schema object
4. Implement schema validation: check all referenced types exist, detect circular type references, verify resolver return types match declared types
5. Implement schema cache: serialize the built schema to file, load from cache in production to skip compilation
6. Unit tests for all type inference mappings and schema builder with complex type graphs

#### Verification
- [ ] PHP scalar types correctly map to GraphQL scalar types
- [ ] Nullable types, arrays, enums, and object types resolve correctly
- [ ] Schema builder produces a valid GraphQL schema from discovered attributes
- [ ] Schema validation catches missing types and unresolved references at compile time
- [ ] Cached schema loads without re-compilation in production

### 3. [ ] Execution — POST /graphql endpoint, error formatting, GraphqlModule

#### GraphQL Execution Endpoint
- Register `POST /graphql` route (path configurable)
- Accept JSON body with `query`, `variables`, and `operationName` fields
- Parse and validate the GraphQL query
- Execute against compiled schema via `webonyx/graphql-php`
- Return JSON with `data` and `errors` fields per GraphQL spec
- Support batched queries (array of operations in single request)

#### Error Formatting
- Catch resolver exceptions and convert to GraphQL error format
- Include `message`, `locations`, `path`, and `extensions` per spec
- Map specific exception types to user-friendly messages
- Hide internal details in production (configurable debug mode)
- Support custom error formatters

#### GraphqlModule and GraphqlServiceProvider
- Create `GraphqlModule` with `#[Module]` attribute
- Register endpoint controller, GraphiQL playground (dev-only), schema builder, type registry, execution engine
- Create `GraphqlServiceProvider` with `graphql.php` config loading
- Support per-module schema contributions (modules add their own types/queries)
- Register CLI commands

#### Detailed Steps
1. Create `Http/GraphqlController.php` handling `POST /graphql` with JSON body parsing
2. Use `GraphQL::executeQuery($schema, $query, null, $context, $variables, $operationName)` from webonyx
3. Create `Error/ErrorFormatter.php` that wraps exceptions: in debug mode include `debugMessage` and `trace` in extensions; in production strip internals
4. Create `GraphqlModule.php` with `#[Module]` attribute registering routes, providers, and commands
5. Create `GraphqlServiceProvider.php` binding SchemaBuilder, TypeRegistry, and GraphqlController
6. Unit tests for query execution, variable substitution, operation selection, error formatting

#### Verification
- [ ] `POST /graphql` with valid query returns correct `data` response
- [ ] Variables are substituted correctly in queries
- [ ] Resolver exceptions produce well-formatted GraphQL errors
- [ ] Internal exception details are hidden in production mode
- [ ] Batched queries return array of results

### 4. [ ] Integration — guards, pipes, interceptors, module scoping, context injection

#### Guard Integration
- Support `#[UseGuards(AuthGuard::class)]` on query/mutation resolver methods and resolver classes
- Execute guards before resolver invocation
- Return proper GraphQL error (not HTTP 403) for unauthorized access
- Support public queries alongside authenticated queries in same schema

#### Pipe Integration
- Support `#[UsePipes(ValidationPipe::class)]` on mutations
- Validate input arguments using existing LatticePHP validation pipes
- Map validation failures to GraphQL errors with field-specific messages

#### Interceptor Integration
- Support `#[UseInterceptors(LoggingInterceptor::class)]` on resolvers
- Execute interceptors around resolver method calls
- Support response transformation and timing/performance interceptors

#### Module Scoping
- Each module's resolvers contribute to the global schema
- Support optional module-level prefixing for query/mutation names
- Module removal cleanly removes its types and resolvers

#### Context Injection
- Implement `#[CurrentUser]` parameter attribute to inject authenticated user
- Implement `#[Context]` parameter attribute to inject GraphQL execution context
- Implement `#[Info]` parameter attribute to inject GraphQL ResolveInfo
- Support custom context values set by middleware

#### Detailed Steps
1. Create `Middleware/GuardExecutor.php` that runs guards before resolver invocation, catches `UnauthorizedException` and converts to GraphQL error
2. Create `Middleware/PipeExecutor.php` that validates mutation inputs via pipes, converts `ValidationException` to field-level GraphQL errors
3. Create `Middleware/InterceptorExecutor.php` wrapping resolver calls with before/after interceptor hooks
4. Modify `SchemaBuilder` to namespace types by module when prefix option is set
5. Create `Attributes/CurrentUser.php`, `Attributes/Context.php`, `Attributes/Info.php` parameter attributes
6. Unit tests for guard allow/deny, pipe validation, interceptor execution order, module scoping, context injection

#### Verification
- [ ] `#[UseGuards(AuthGuard::class)]` blocks unauthenticated queries with GraphQL error
- [ ] `#[UsePipes(ValidationPipe::class)]` rejects invalid mutation input with field errors
- [ ] Interceptors execute around resolvers in correct order
- [ ] Multi-module schema assembles types from all registered modules
- [ ] `#[CurrentUser]` injects the authenticated user into resolver parameters

### 5. [ ] DataLoader — N+1 prevention with batch loading pattern
- Implement `DataLoader` class with batch loading and per-request caching
- Implement `#[BatchLoader]` attribute to mark batch-loading methods
- Integrate DataLoader lifecycle with request lifecycle (clear cache per request)
- Support automatic batching of related entity loading
- Support custom DataLoader factories

#### Additional Integration
- Implement Relay-style cursor-based pagination (Connection, Edge, PageInfo types)
- Support `first`, `after`, `last`, `before` pagination arguments; auto-generate Connection types for list fields
- Support offset-based pagination as alternative
- Implement file upload support via multipart form data per GraphQL multipart request spec
- Define `Upload` scalar type, map files to resolver parameters, support single and multiple uploads

#### Detailed Steps
1. Create `DataLoader/DataLoader.php` with `load(mixed $key): Promise`, `loadMany(array $keys): Promise`, `dispatch(): void`
2. Batch function receives array of keys, returns array of results in same order
3. Per-request cache: once a key is loaded, return cached value on subsequent `load()` calls
4. Create `DataLoader/DataLoaderFactory.php` for creating DataLoaders with batch functions
5. Integrate with request lifecycle: create fresh DataLoaders per request, dispatch at field resolution boundaries
6. Create `Pagination/ConnectionType.php`, `Pagination/EdgeType.php`, `Pagination/PageInfo.php` for Relay-style pagination
7. Unit tests for batch loading, deferred resolution, caching, pagination, and file uploads

#### Verification
- [ ] DataLoader batches multiple individual loads into a single batch call
- [ ] Per-request cache prevents duplicate loads for the same key
- [ ] N+1 query problem is eliminated when using DataLoader in resolvers
- [ ] Cursor-based pagination returns correct Connection with edges and page info
- [ ] File upload resolves uploaded files in mutation resolvers

### 6. [ ] Subscriptions — `#[Subscription]`, SSE transport, event triggers
- Define `#[Subscription]` attribute with parameters: name, description
- Support applying to methods that define subscription topics
- Auto-extract return type as subscription payload type
- Support subscription resolver method for transforming event data

#### SSE Transport
- Implement SSE endpoint (`GET /graphql/subscriptions`) for subscription connections
- Parse subscription query from client, register with topic manager
- Stream events as SSE `data:` messages with keepalive heartbeat
- Handle client disconnect cleanup and `Last-Event-ID` for reconnection

#### Event-Driven Triggers
- Implement subscription manager (topic registration, client tracking)
- Integrate with `lattice/events`: fire subscription updates when events are dispatched
- Support `#[SubscriptionTrigger(EventClass::class)]` to bind subscriptions to events
- Support Redis pub/sub for multi-process distribution

#### Subscription Filtering
- Support filter callbacks to scope events per client
- `#[SubscriptionFilter]` attribute for declarative filtering

#### Detailed Steps
1. Create `Attributes/Subscription.php` and `Attributes/SubscriptionTrigger.php` attributes
2. Create `Subscriptions/SubscriptionManager.php` tracking active subscriptions per client
3. Create `Http/SseController.php` handling `GET /graphql/subscriptions` with SSE response streaming
4. On event dispatch, match to subscriptions by trigger, apply filters, push to connected clients
5. Create `Subscriptions/SubscriptionFilter.php` interface for custom filtering logic
6. Unit tests for subscription lifecycle, SSE transport, event triggering, and filtering

#### Verification
- [ ] `#[Subscription]` method defines a valid subscription in the schema
- [ ] SSE endpoint streams events to connected clients
- [ ] Event dispatch triggers subscription payload delivery
- [ ] Subscription filters scope events to relevant clients
- [ ] Client disconnect cleans up subscription registrations

### 7. [ ] DX — GraphiQL playground, schema export CLI, query complexity limiting, testing utilities + docs

#### GraphiQL Playground
- Serve GraphiQL UI at configurable endpoint (default `GET /graphiql`)
- Include auth header support, custom headers, dark mode
- Restrict in production (configurable middleware)

#### Schema Introspection & Export
- Enable standard GraphQL introspection queries (configurable, disable in production)
- Implement `php lattice graphql:schema` CLI command printing full SDL
- Support `--output=schema.graphql` for file export
- Include descriptions and deprecation notices in exported SDL

#### Query Complexity & Security
- Implement query depth limiting (configurable max depth, default 10)
- Implement query complexity analysis with per-field cost via `#[Complexity]` attribute
- Reject queries exceeding limits with descriptive error
- Implement persisted queries (APQ) via query hash stored in `lattice/cache`
- Accept `extensions.persistedQuery.sha256Hash`, return `PERSISTED_QUERY_NOT_FOUND` when unknown

#### Testing Utilities
- Create `GraphqlTestCase` trait with assertion helpers
- `query()` and `mutation()` test helper methods
- `assertGraphqlSuccess()`, `assertGraphqlError(string $message)`, `assertGraphqlData(array $expected)`
- Support variable passing and operation name selection

#### Documentation
- Getting-started guide with installation and first query
- All attributes documented with examples
- Type mapping rules, guard/pipe/interceptor integration, DataLoader usage
- Pagination, subscription setup, testing utilities, schema export
- Query complexity and persisted queries configuration

#### Detailed Steps
1. Create `Http/PlaygroundController.php` serving GraphiQL HTML page with configured endpoint URL
2. Create `Commands/SchemaExportCommand.php` that renders schema to SDL string
3. Create `Validation/QueryDepthValidator.php` and `Validation/QueryComplexityValidator.php` as `webonyx/graphql-php` validation rules
4. Create `Cache/PersistedQueryStore.php` wrapping `lattice/cache` for APQ lookup
5. Create `Testing/GraphqlTestCase.php` trait with typed assertion methods
6. Unit tests for playground endpoint, SDL export, depth/complexity limiting, persisted queries, and test utilities

#### Verification
- [ ] GraphiQL playground loads and executes queries against the schema
- [ ] `php lattice graphql:schema` exports valid SDL matching the runtime schema
- [ ] Deeply nested queries are rejected with depth limit error
- [ ] High-complexity queries are rejected with complexity limit error
- [ ] Persisted queries are stored, looked up, and executed from cache
- [ ] `GraphqlTestCase` assertions correctly validate query results

## Integration Verification
- [ ] Query resolves data from annotated `#[Query]` method with correct type mapping
- [ ] Mutation persists data and returns result from `#[Mutation]` method
- [ ] Guards block unauthorized queries with proper GraphQL error (not HTTP error)
- [ ] DataLoader batches N individual entity loads into a single batch query
- [ ] Subscription delivers real-time updates via SSE when event is triggered
- [ ] Full round-trip: define types with attributes, build schema, execute query, validate response in test
