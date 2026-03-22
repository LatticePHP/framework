# ADR-012: API Defaults

**Date:** 2026-03-21
**Status:** Accepted

## Context

An API-first framework must ship with opinionated defaults for API design, error handling, and documentation. Without strong defaults, every team reinvents the wheel, leading to inconsistent APIs across the ecosystem. The defaults should follow established standards and best practices while remaining configurable for teams with different requirements.

## Decision

### Response Format: REST + JSON

The default response format is JSON over REST. All framework-generated responses (errors, validation failures, health checks) use `application/json` content type.

- Controllers return data objects or arrays that are automatically serialized to JSON.
- Content negotiation is built into the HTTP layer for teams that need alternative formats.
- No XML support in core. Available as a community package if needed.

### Error Responses: Problem Details (RFC 9457)

All error responses follow the **RFC 9457 Problem Details for HTTP APIs** specification:

```json
{
  "type": "https://latticephp.com/errors/validation-failed",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The request body contains 2 validation errors.",
  "instance": "/api/users",
  "errors": [
    {
      "field": "email",
      "message": "The email field must be a valid email address.",
      "code": "invalid_format"
    }
  ]
}
```

**Key properties:**
- `type`: A URI identifying the error type. Framework errors use `https://latticephp.com/errors/*`. Applications define their own URIs.
- `title`: A short, human-readable summary of the problem type.
- `status`: The HTTP status code.
- `detail`: A human-readable explanation specific to this occurrence.
- `instance`: The URI of the request that caused the error.
- Extension members (like `errors` above) provide additional structured data.

The `lattice/problem-details` package provides:
- Automatic exception-to-Problem-Details mapping.
- Validation error formatting.
- Custom problem type registration.
- Content negotiation (JSON and XML Problem Details).

### API Documentation: OpenAPI First-Class

The `lattice/openapi` package provides **automatic OpenAPI 3.1 specification generation** from code:

- Route definitions, request/response types, and validation rules are introspected to produce the OpenAPI spec.
- Attributes augment generated specs with descriptions, examples, and metadata.
- The spec is generated at build time (compile step) and served as a static JSON/YAML file.
- A Swagger UI or Scalar endpoint is available in development mode via `lattice/devtools`.

```php
#[Get('/users/{id}')]
#[Summary('Get a user by ID')]
#[Response(200, UserResource::class)]
#[Response(404, description: 'User not found')]
public function show(string $id): UserResource { ... }
```

### JSON:API: Optional Module

The JSON:API specification (`application/vnd.api+json`) is supported via the optional `lattice/jsonapi` package:

- Resource serialization following JSON:API structure.
- Relationship handling (includes, sparse fieldsets).
- Pagination, sorting, and filtering conventions.
- Not installed by default. Teams opt in when they need JSON:API compliance.

### Default Response Headers

All API responses include:
- `Content-Type: application/json` (or `application/problem+json` for errors).
- `X-Request-Id: <uuid>` for request tracing.
- Appropriate cache headers (`Cache-Control`, `ETag` where applicable).
- CORS headers configured via middleware (permissive in development, restrictive in production).

### Pagination Default

List endpoints use cursor-based pagination by default:

```json
{
  "data": [...],
  "meta": {
    "per_page": 25,
    "has_more": true
  },
  "links": {
    "next": "/api/users?cursor=eyJpZCI6MjV9"
  }
}
```

Offset-based pagination is available as an alternative.

## Consequences

**Positive:**
- Every LatticePHP API starts with consistent, standards-compliant error handling and documentation.
- Problem Details (RFC 9457) is an IETF standard, ensuring interoperability with API gateways and clients.
- OpenAPI generation eliminates documentation drift -- the spec always matches the code.
- JSON:API as an optional module avoids imposing its opinionated structure on teams that do not need it.
- Cursor-based pagination performs better than offset-based for large datasets.

**Negative:**
- Problem Details is more verbose than simple `{"error": "message"}` responses. Some teams may find it heavyweight for simple APIs.
- OpenAPI generation requires careful attribute usage to produce high-quality specs.
- Two serialization formats (default JSON and optional JSON:API) create some ecosystem fragmentation.

## Alternatives Considered

1. **JSON:API as default:** Too opinionated for general use. JSON:API's envelope structure adds complexity that not all APIs need. Better as opt-in.

2. **Custom error format:** Would require every API client to learn a non-standard format. RFC 9457 is widely supported by API tooling.

3. **No built-in OpenAPI generation:** Would leave documentation as a manual task, leading to outdated specs. First-class generation is essential for an API-first framework.

4. **GraphQL as default:** While powerful, GraphQL has a different operational model (single endpoint, query language) that does not align with REST-based routing. Can be supported via a community package.

5. **Offset-based pagination default:** Simpler to understand but performs poorly on large tables and has consistency issues with concurrent writes. Cursor-based is the better default for production APIs.
