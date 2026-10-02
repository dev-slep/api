# Social/

`JwksSocialIdentityVerifier` validates Google/Apple ID tokens (signature via cached JWKS, issuer, audience, expiry, `email_verified`); `RsaJwk` converts JWK to PEM; `SocialProviderSettings` holds client ids and URLs. Tests use WireMock stubs in `docker/wiremock`.
