# tests/Integration/Authentication/

One `*EndpointTest` per route (with `#[CoversEndpoint]` and OpenAPI assertions), plus access control, console commands, rate-limiter storage/schema, ban handling and `Persistence/` (Doctrine repositories). Extend `AuthenticationIntegrationTestCase`: `createAccount()`, `login()`, `call()`, `mailsTo()`, `tokenFromMail()`.
