# GraphQL -- Task List

## Phase 1: Core

### `#[ObjectType]` Attribute for PHP Classes as GraphQL Types
- [ ] Define `#[ObjectType]` attribute with parameters: name (optional, defaults to class name), description
- [ ] Support applying `#[ObjectType]` to PHP classes
- [ ] Auto-generate GraphQL object type from class properties and methods
- [ ] Support class inheritance (child types inherit parent fields)
- [ ] Unit tests for ObjectType discovery and schema generation

### `#[Field]` Attribute with Type, Description, Deprecation
- [ ] Define `#[Field]` attribute with parameters: name, type (override), description, deprecationReason, nullable
- [ ] Support applying `#[Field]` to class properties for explicit field configuration
- [ ] Support applying `#[Field]` to methods (computed/resolved fields)
- [ ] Support field arguments via method parameters
- [ ] Default to property name and inferred type when no overrides provided
- [ ] Unit tests for Field attribute with all parameter combinations

### `#[Query]` Attribute on Resolver Methods
- [ ] Define `#[Query]` attribute with parameters: name (optional, defaults to method name), description, deprecationReason
- [ ] Support applying `#[Query]` to methods on resolver classes
- [ ] Auto-extract return type as the GraphQL response type
- [ ] Auto-extract method parameters as GraphQL query arguments
- [ ] Support constructor dependency injection in resolver classes
- [ ] Support method parameter injection (context, info, arguments)
- [ ] Unit tests for Query discovery, argument extraction, and return type mapping

### `#[Mutation]` Attribute on Resolver Methods
- [ ] Define `#[Mutation]` attribute with parameters: name (optional, defaults to method name), description
- [ ] Support applying `#[Mutation]` to methods on resolver classes
- [ ] Auto-extract return type as the GraphQL response type
- [ ] Auto-extract method parameters as GraphQL mutation arguments
- [ ] Unit tests for Mutation discovery and schema generation

### `#[InputType]` Attribute for Input Objects
- [ ] Define `#[InputType]` attribute with parameters: name (optional, defaults to class name), description
- [ ] Support applying `#[InputType]` to PHP classes
- [ ] Auto-generate GraphQL input object type from class properties
- [ ] Support nested input types (input type referencing another input type)
- [ ] Support validation rules on input type fields via pipes
- [ ] Unit tests for InputType generation with nested types

### `#[EnumType]` Attribute Mapping PHP Enums to GraphQL Enums
- [ ] Define `#[EnumType]` attribute with parameters: name (optional), description
- [ ] Support applying `#[EnumType]` to PHP backed enums (string-backed and int-backed)
- [ ] Auto-generate GraphQL enum values from enum cases
- [ ] Support `#[EnumValue]` attribute on individual cases for description/deprecation overrides
- [ ] Unit tests for EnumType mapping with both string and int backed enums

### `#[InterfaceType]` and `#[UnionType]`
- [ ] Define `#[InterfaceType]` attribute with parameters: name, description
- [ ] Support applying `#[InterfaceType]` to PHP interfaces or abstract classes
- [ ] Auto-discover implementing types for the interface
- [ ] Define `#[UnionType]` attribute with parameters: name, description, types (array of class references)
- [ ] Support type resolution for unions (resolve concrete type from value)
- [ ] Unit tests for InterfaceType with implementing classes
- [ ] Unit tests for UnionType with multiple member types

### Auto Type Inference from PHP Types to GraphQL Types
- [ ] Map PHP `int` to GraphQL `Int`
- [ ] Map PHP `float` to GraphQL `Float`
- [ ] Map PHP `string` to GraphQL `String`
- [ ] Map PHP `bool` to GraphQL `Boolean`
- [ ] Map nullable PHP types (`?string`) to nullable GraphQL fields
- [ ] Map PHP `array` with docblock `@return Type[]` to GraphQL `[Type]` lists
- [ ] Map PHP enums with `#[EnumType]` to GraphQL enum types
- [ ] Map PHP classes with `#[ObjectType]` to GraphQL object types
- [ ] Handle nested type resolution (class references within types)
- [ ] Support custom scalar types (DateTime, JSON, UUID) via `#[Scalar]` attribute
- [ ] Handle union types via PHP docblock annotations or attribute
- [ ] Unit tests for all type inference mappings

### Schema Builder via Compiler Discovery
- [ ] Implement `SchemaBuilder` that collects all annotated classes from the compiler
- [ ] Build GraphQL schema object from discovered attributes and types
- [ ] Generate Query root type from all `#[Query]` methods
- [ ] Generate Mutation root type from all `#[Mutation]` methods
- [ ] Generate Subscription root type from all `#[Subscription]` methods
- [ ] Validate schema completeness at compile time (missing types, circular refs, unresolved references)
- [ ] Support schema caching (serialize/deserialize compiled schema for production)
- [ ] Unit tests for schema builder with complex type graphs
- [ ] Unit tests for schema validation error messages

### GraphQL Execution Endpoint (POST /graphql)
- [ ] Register `POST /graphql` route (path configurable)
- [ ] Accept JSON body with `query`, `variables`, and `operationName` fields
- [ ] Parse and validate the GraphQL query
- [ ] Execute the query against the compiled schema via webonyx/graphql-php
- [ ] Return JSON response with `data` and `errors` fields per GraphQL spec
- [ ] Support batched queries (array of operations in single request)
- [ ] Unit tests for query execution, variable substitution, and operation selection

### Error Formatting (Convert Exceptions to GraphQL Errors)
- [ ] Catch exceptions thrown by resolvers and convert to GraphQL error format
- [ ] Include error `message`, `locations`, `path`, and `extensions` per spec
- [ ] Map specific exception types to user-friendly error messages
- [ ] Hide internal exception details in production (configurable debug mode)
- [ ] Support custom error formatters
- [ ] Unit tests for error formatting with various exception types

### GraphqlModule and GraphqlServiceProvider
- [ ] Create `GraphqlModule` with `#[Module]` attribute
- [ ] Register GraphQL HTTP endpoint controller
- [ ] Register GraphiQL playground endpoint (dev-only by default)
- [ ] Create `GraphqlServiceProvider` that registers schema builder, type registry, execution engine
- [ ] Load configuration from `graphql.php` config file
- [ ] Support per-module schema contributions (modules add their own types/queries)
- [ ] Register CLI commands via module
- [ ] Unit tests for module and provider registration


## Phase 2: Integration

### Guard Integration (`#[UseGuards]` on Queries/Mutations)
- [ ] Support `#[UseGuards(AuthGuard::class)]` on query/mutation resolver methods
- [ ] Support `#[UseGuards]` on resolver classes (applies to all methods)
- [ ] Execute guards before resolver method invocation
- [ ] Return proper GraphQL error (not HTTP 403) for unauthorized access
- [ ] Support public queries alongside authenticated queries in the same schema
- [ ] Unit tests for guard integration with allow and deny scenarios

### Pipe Integration (Input Validation via Existing Pipes)
- [ ] Support `#[UsePipes(ValidationPipe::class)]` on mutations
- [ ] Validate input arguments using existing LatticePHP validation pipes
- [ ] Map validation failures to GraphQL errors with field-specific messages
- [ ] Support custom pipe chains per resolver
- [ ] Unit tests for pipe integration with valid and invalid inputs

### Interceptor Integration
- [ ] Support `#[UseInterceptors(LoggingInterceptor::class)]` on resolvers
- [ ] Execute interceptors around resolver method calls
- [ ] Support response transformation via interceptors
- [ ] Support timing/performance interceptors
- [ ] Unit tests for interceptor execution order and response modification

### Module Scoping (Queries/Mutations Belong to Modules)
- [ ] Each module's resolvers contribute to the global schema
- [ ] Support module-level prefixing for query/mutation names (optional)
- [ ] Module removal cleanly removes its types and resolvers from the schema
- [ ] Unit tests for multi-module schema assembly

### N+1 Prevention with DataLoader Pattern (Batch Loading)
- [ ] Implement `DataLoader` class with batch loading and per-request caching
- [ ] Implement `#[BatchLoader]` attribute to mark batch-loading methods
- [ ] Integrate DataLoader lifecycle with request lifecycle (clear cache per request)
- [ ] Support automatic batching of related entity loading
- [ ] Support custom DataLoader factories
- [ ] Document DataLoader usage patterns with examples
- [ ] Unit tests for batch loading with deferred resolution

### Pagination Support (Cursor-Based, Relay-Style Connections)
- [ ] Implement Relay-style Connection and Edge types
- [ ] Support `first`, `after`, `last`, `before` pagination arguments
- [ ] Auto-generate Connection types for list fields
- [ ] Implement `PageInfo` type with `hasNextPage`, `hasPreviousPage`, `startCursor`, `endCursor`
- [ ] Support offset-based pagination as alternative
- [ ] Unit tests for cursor-based pagination with forward and backward navigation

### File Upload Support
- [ ] Support multipart form data for file uploads per GraphQL multipart request spec
- [ ] Define `Upload` scalar type
- [ ] Map uploaded files to resolver method parameters
- [ ] Support single and multiple file uploads
- [ ] Unit tests for file upload parsing and resolver injection

### Context Injection (`#[CurrentUser]`, `#[Param]`)
- [ ] Implement `#[CurrentUser]` parameter attribute to inject authenticated user
- [ ] Implement `#[Context]` parameter attribute to inject GraphQL execution context
- [ ] Implement `#[Info]` parameter attribute to inject GraphQL ResolveInfo
- [ ] Support custom context values set by middleware
- [ ] Unit tests for context injection in resolvers


## Phase 3: Subscriptions

### `#[Subscription]` Attribute
- [ ] Define `#[Subscription]` attribute with parameters: name, description
- [ ] Support applying `#[Subscription]` to methods that define subscription topics
- [ ] Auto-extract return type as the subscription payload type
- [ ] Support subscription resolver method for transforming event data before sending
- [ ] Unit tests for subscription attribute discovery

### SSE Transport for Subscriptions
- [ ] Implement SSE endpoint for subscription connections (`GET /graphql/subscriptions`)
- [ ] Parse subscription query from client
- [ ] Register client subscription with topic manager
- [ ] Stream events to client as SSE `data:` messages
- [ ] Handle client disconnect cleanup (remove subscription registration)
- [ ] Send keepalive heartbeat comments at configurable interval
- [ ] Support `Last-Event-ID` for reconnection
- [ ] Unit tests for SSE transport lifecycle

### Event-Driven Subscription Triggers
- [ ] Implement subscription manager (topic registration, client tracking)
- [ ] Integrate with `lattice/events` -- fire subscription updates when events are dispatched
- [ ] Support `#[SubscriptionTrigger(EventClass::class)]` to bind subscriptions to events
- [ ] Map event payload to subscription return type
- [ ] Support Redis pub/sub for multi-process subscription distribution
- [ ] Unit tests for event-driven subscription triggers

### Subscription Filtering
- [ ] Support filter callbacks to scope subscription events per client
- [ ] Allow filter functions to access subscription arguments and context
- [ ] Support `#[SubscriptionFilter]` attribute for declarative filtering
- [ ] Only deliver events that pass the client's filter criteria
- [ ] Unit tests for subscription filtering with various criteria


## Phase 4: Developer Experience

### GraphiQL/Playground Endpoint (GET /graphql with HTML UI)
- [ ] Serve GraphiQL UI at configurable endpoint (default `GET /graphiql`)
- [ ] Include authentication header support in GraphiQL config
- [ ] Support custom headers configuration
- [ ] Support dark mode
- [ ] Restrict access in production (configurable middleware)
- [ ] Unit tests for playground endpoint serving

### Schema Introspection
- [ ] Enable standard GraphQL introspection queries by default
- [ ] Support disabling introspection in production (configurable)
- [ ] Ensure all types, fields, arguments, and descriptions are introspectable
- [ ] Unit tests for introspection query responses

### Schema SDL Export (`php lattice graphql:schema`)
- [ ] Implement `graphql:schema` CLI command that prints the full SDL to stdout
- [ ] Support output to file: `php lattice graphql:schema --output=schema.graphql`
- [ ] Include descriptions and deprecation notices in exported SDL
- [ ] Unit tests for SDL export correctness

### Query Complexity Analysis and Depth Limiting
- [ ] Implement query depth limiting (configurable max depth, default 10)
- [ ] Implement query complexity analysis (assign cost to fields, reject queries over threshold)
- [ ] Support `#[Complexity]` attribute to set per-field cost
- [ ] Return descriptive error when query exceeds limits
- [ ] Unit tests for depth limiting and complexity rejection

### Persisted Queries
- [ ] Support automatic persisted queries (APQ) via query hash
- [ ] Store persisted queries in cache via `lattice/cache`
- [ ] Accept `extensions.persistedQuery.sha256Hash` in request
- [ ] Return `PERSISTED_QUERY_NOT_FOUND` error when hash is unknown and full query not provided
- [ ] Support pre-registered persisted queries from a file
- [ ] Unit tests for persisted query registration and lookup

### Testing Utilities (GraphqlTestCase, assertGraphql Helper)
- [ ] Create `GraphqlTestCase` trait with assertion helpers
- [ ] Implement `query(string $query, array $variables = [])` test helper
- [ ] Implement `mutation(string $mutation, array $variables = [])` test helper
- [ ] Implement `assertGraphqlSuccess()` -- no errors in response
- [ ] Implement `assertGraphqlError(string $message)` -- specific error present
- [ ] Implement `assertGraphqlData(array $expected)` -- data matches expected structure
- [ ] Support variable passing and operation name selection in test queries
- [ ] Unit tests for the test utilities themselves

### Documentation with Examples
- [ ] Write getting-started guide with installation and first query
- [ ] Document all attributes (`#[Query]`, `#[Mutation]`, `#[ObjectType]`, `#[Field]`, `#[InputType]`, `#[EnumType]`, `#[InterfaceType]`, `#[UnionType]`, `#[Subscription]`) with examples
- [ ] Document type mapping rules (PHP to GraphQL)
- [ ] Document guard, pipe, and interceptor integration with examples
- [ ] Document DataLoader usage with N+1 prevention examples
- [ ] Document pagination (cursor-based and offset-based) with examples
- [ ] Document subscription setup and SSE transport
- [ ] Document testing utilities with example test cases
- [ ] Document schema SDL export and introspection configuration
- [ ] Document query complexity and depth limiting configuration
- [ ] Document persisted queries setup
