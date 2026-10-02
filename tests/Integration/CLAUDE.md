# tests/Integration/

Tests against the real stack. Extend `Support\IntegrationTestCase` (or `AuthenticationIntegrationTestCase`): each test runs in a rolled-back transaction (DAMA). Each HTTP route needs a test with `#[CoversEndpoint('METHOD', '/path')]` that also asserts the response against the OpenAPI spec; each console command needs `#[CoversConsoleCommand]`. `make test-integration` resets the test DB and WireMock first, so the stack must be up (`make up`).
