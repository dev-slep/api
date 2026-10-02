# Social sign-in test fixtures

`test-signing-key.pem` is a throw-away RSA key that exists only for tests. Its public half is served by WireMock as the
"Google" and "Apple" signing keys (`docker/wiremock/__files/*-jwks.json`, key id `test-key-1`), so integration tests can
sign ID tokens that the application accepts exactly like real ones. Never use it anywhere else.
