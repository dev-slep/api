# Http/

`Controller/` (one per endpoint, thin, full `/api/v1/...` path in `#[Route]`, OpenAPI attributes), `Request/` (validated body DTOs), `Response/` (response DTOs and shared OpenAPI responses), `RequestContextFactory` (client IP and user agent). After changing a controller: `make openapi`.
