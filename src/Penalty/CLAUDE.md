# src/Penalty/

Strikes, escalation (warning, suspension, ban), blacklist, appeals.

Only the contract exists so far: `BlacklistChecker` (placeholder `AllowAllBlacklistChecker`), `UserBannedV1`, `UserSuspendedV1`. Authentication consumes the two events to revoke sessions.

Status: partially built (contract only).

Layout and dependency rules are in `../CLAUDE.md`. When implementing:
1. Domain first (model, repository interface, exceptions implementing `ProblemType`), then Application (commands, handlers, ports), then Infrastructure (Doctrine records and XML mapping, controllers, adapters). Keep `Contract/` to what other modules truly need.
2. Own PostgreSQL schema per module: create it with `make migration m=Penalty`.
3. Wire services in `config/services/penalty.yaml`; the controller path is already imported in `config/routes/modules.yaml`.
4. Tests mirror `src/` under `tests/Unit|Application|Integration/Penalty/` (see `../../tests/CLAUDE.md`). The authentication module is the reference implementation.
5. Run `make qa` before finishing.
