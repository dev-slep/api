# src/Authentication/

Accounts and credentials: email+password, Google/Apple sign-in, admin TOTP 2FA, JWT access tokens and rotating refresh tokens, email verification, password reset. Reference implementation of the module layout. Plan and decisions: `../../../tasks/auth-plan.md`; spec §7.1 and §9 in `../../../internal-docs/backend-specification.md`.

Behaviour to know:
- Login requires a **verified email**. Registration returns no tokens. An unverified account that later signs in through a provider loses its password (anti pre-hijacking).
- Access tokens: stateless RS256, 15 minutes, `kid` header, `amr` claim. Refresh tokens: opaque, SHA-256 hashed, families with reuse detection.
- Admins are created only by `slep:auth:create-admin`; they need TOTP. After the password step they get a role-less 5-minute "2FA pending" token that only `/api/v1/admin/auth/2fa/*` accepts.
- Publishes eleven `*V1` events (no secrets) for Audit; consumes Penalty's `UserBannedV1`/`UserSuspendedV1` to revoke sessions. Depends on Penalty's `BlacklistChecker` and Authorization's `RoleLookup` contracts only. Token roles come from `RoleLookup`; the role the account registered with is only a fallback (`SessionFactory::securityRoles`).
- Failures that must be recorded (bad password, token reuse, wrong 2FA code) are returned as failed results, not thrown, so the transaction commits.

Other modules use `Contract/CurrentUser` (who is calling) and `Contract/AccountLookup`. Never import Domain or Infrastructure classes from here.
