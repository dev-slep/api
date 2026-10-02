# Support/Fixtures/

Fixture classes and files loaded only in the test env (routes in `config/routes/test.yaml`): `Http/ProtectedFixtureController` (a protected route for access-control tests), `Health`, `Messaging`, `Outbox`, `Scheduler`, `Social` (the test signing key behind the WireMock JWKS).
