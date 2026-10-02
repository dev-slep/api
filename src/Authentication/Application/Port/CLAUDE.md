# Application/Port/

Interfaces the use cases need from the outside: `AccessTokenIssuer`/`AccessTokenVerifier`, `AuthEmailSender`, `SocialIdentityVerifier`, `TotpProvisioner`/`TotpVerifier`, plus value types (`AuthenticatedPrincipal`, `AuthenticationSettings`, `VerifiedSocialIdentity`). Adapters live in `Infrastructure`; fakes for tests in `tests/Support/Authentication`.
