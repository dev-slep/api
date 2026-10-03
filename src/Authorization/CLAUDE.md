# src/Authorization/

Roles (DRIVER, TOWER, ADMIN) and access decisions; policy registry. Plan and decisions: `../../../tasks/authz-plan.md`; spec §6 and §7.2 in `../../../internal-docs/backend-specification.md`.

Status: built.

Behaviour to know:
- One `RoleAssignment` per user (`"authorization".role_assignment`, unique on user). It is created by `GrantRoleOnRegistrationSubscriber` when `UserRegisteredV1` arrives (admins created by `slep:auth:create-admin` publish it too) and publishes `RoleGrantedV1`. The first role a user gets stays; granting again is a no-op. An unknown role name throws `UnknownRole` (a contract error, so the message fails loudly).
- `authorization` is a reserved word in PostgreSQL, so the schema name must be quoted (`"authorization".role_assignment`). The ORM mapping cannot quote a schema, so this module uses plain DBAL (`DbalRoleAssignmentRepository`) and has no ORM mapping or entities.
- Contract: `RoleLookup::rolesFor` (stored role as a security role, `[]` when none) and `AccessDecider` (`isGranted(UserId, Permission)` reads the stored role; `allows(roles, Permission)` is pure and is what the voter uses with the access token's roles).
- Authentication still falls back to the role an account registered with when `RoleLookup` returns nothing: that covers the short window before the event is handled and accounts created before this module existed.
- `Permission` (Contract) is the list of things a user can be allowed to do; `Application/Service/PermissionCatalogue` says which roles hold each. A unit test fails if a permission is held by nobody.

Adding a permission: a case in `Contract/Permission`, a line in `PermissionCatalogue::HOLDERS`, then `#[IsGranted(Permission::Foo->value)]` on the controller. `PermissionVoter` decides from the token's roles, so a role-less token (admin before the second factor) is always refused. Ownership rules ("only the owner of this request") stay in the owning module's domain, not here.

Layout and dependency rules are in `../CLAUDE.md`. Tests mirror `src/` under `tests/Unit|Application|Integration/Authorization/`; `Support/Authorization/RoleAssignmentRepositoryContract` runs the same repository tests against the in-memory and the DBAL implementation.
