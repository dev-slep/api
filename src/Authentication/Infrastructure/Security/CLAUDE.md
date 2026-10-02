# Security/

Symfony Security glue for the stateless `api` firewall: `JwtAuthenticator`, `JwtUserProvider`, `AuthenticatedUser`, `SecurityCurrentUser` (implements the `CurrentUser` contract), `ProblemEntryPoint` (401/403 as problem+json), `TokenAuthenticationException`. A 2FA-pending token yields no roles; protect admin routes with role checks, not `IS_AUTHENTICATED` alone.
