# Application/Service/

Shared application logic used by several handlers: `SessionFactory` (issue access + refresh token), `SecurityEvents` (publish the security events), `AuthenticationEventMapper` (domain to integration events), `TwoFactorEnroller`. No vendor code.
