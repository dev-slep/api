# docker/wiremock/

WireMock serves fake third parties for integration tests (Google/Apple JWKS, FCM, payment gateway).

- `mappings/<service>/*.json`: request matchers and responses.
- `__files/`: response bodies (for example `google-jwks.json`, `apple-jwks.json`).

Add a stub here rather than calling a real service. `make test-integration` resets WireMock first. Test-signing keys live in `tests/Support/Fixtures/Social/`.
