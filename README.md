# Slep API

Backend of Slep, built with Symfony 8.1 on PHP 8.5 as a modular monolith. This repository holds the backend only; other apps live in their own repositories.

## Layout

| Path | Purpose |
|---|---|
| `src/` | Application code, one folder per module plus `SharedKernel/` |
| `config/` | Symfony configuration, one DI file per module in `config/services/` |
| `docker/` | PHP, nginx, supervisor, Postgres and WireMock files |
| `migrations/` | Doctrine migrations, one sub-folder per module |
| `openapi/` | Generated OpenAPI documentation |
| `tools/` | Custom QA tooling (the Deptrac config generator) |
| `tests/` | `Unit`, `Application`, `Integration` and `Support` |

## Getting started

Only **Docker** and **Make** are required on the host.

```sh
make install   # build images, start the stack, composer install, create + migrate both databases, JWT keys
make qa        # code style, PHPStan, Deptrac, test policy and all three test suites
```

Run `make help` for every target. After `make install`:

| What | Where |
|---|---|
| API | http://localhost:8080 (`/health/live`, `/health/ready`, Swagger UI at `/api/doc` in dev) |
| Mailpit (caught emails) | http://localhost:8025 |
| MinIO console | http://localhost:9001 (user `slep`, password `slep-secret`) |
| Postgres | `postgres:5432` inside the stack, `localhost:3307` from the host (user, password and database: `slep`; tests use `slep_test`) |

Notes from setting the project up:

- The `app` container runs nginx, PHP-FPM and the Messenger worker (`async` + `scheduler_default`) under supervisord: `make bash`, then `supervisorctl status`. After code changes that affect handlers or config, `make worker-restart`.
- Xdebug is installed but off; switch it on with `XDEBUG_MODE=debug` in `docker-compose.override.yml`.
- Tests: `make test` runs the unit, application and integration suites as separate processes; `make test f=SomeTest` filters. The application suite never touches Postgres.
- Logs are structured JSON in prod (one object per line, with `correlationId`) and readable lines in dev; `make logs` / `make worker-logs` follow them.
- `make openapi` exports `openapi/openapi.yaml`, which is committed (an integration test fails when it is stale).
- `make prod-build` builds the production image (non-root, no dev tools, migrations on start with `RUN_MIGRATIONS=1`).
- Secrets: the Symfony vault public key is committed; `config/secrets/prod/prod.decrypt.private.php` and `config/jwt/*.pem` are git-ignored.
- The `minio/minio` and `minio/mc` images are no longer published; the stack uses Chainguard's builds (`cgr.dev/chainguard/minio`, `minio-client`).

## Authentication

Stateless JWT authentication (module `Authentication`, spec §7.1). Public endpoints are under `/api/v1/auth/*`; everything else under `/api/v1` needs `Authorization: Bearer <access token>`, and `/api/v1/admin/*` needs an admin token issued after the second factor.

- **Keys and secrets:** `make jwt-keys` creates the RS256 key pair in `config/jwt/` (git-ignored). `AUTH_ENCRYPTION_KEY` (32 random bytes, base64: `openssl rand -base64 32`) encrypts the TOTP secrets at rest. The value in `.env` is for local development only; production must set its own. Social sign-in needs `GOOGLE_CLIENT_ID` and `APPLE_CLIENT_ID`; the verification and reset links in emails open the web app, whose origin is `WEB_APP_URL` (one per environment, e.g. `http://localhost:8081` locally); add the same origin to `CORS_ALLOW_ORIGIN`.
- **First admin:** `bin/console slep:auth:create-admin <email>` asks for a password (or reads it with `--password-from-stdin`), creates a verified admin and prints the authenticator URI. The admin confirms the first code with `POST /api/v1/admin/auth/2fa/enrol/confirm` (using the pending token from the password login) and logs in again with a code.
- **Housekeeping:** `bin/console slep:auth:purge-expired-tokens` deletes long-expired tokens; the scheduler runs it daily.
- Until the Authorization module stores roles, the role an account registered with becomes its token role. Until Penalty keeps a blacklist, registration blocks no contact.

## Guard rails

`make qa` enforces the architecture from the specification: Deptrac (layer and module boundaries), PHPStan at level max with no baseline, PHP CS Fixer (strict types, final classes), and the test policy (every endpoint, console command, contract method and public method has its required test). Details: [`src/README.md`](src/README.md) and [`tests/README.md`](tests/README.md).

## Database and migrations

One Postgres database, **one schema per module** (`tow_request`, `bidding`, …) plus `messenger` for the transport tables. PostGIS is enabled in `slep` and `slep_test`.

- Each module has its own migration namespace `Migrations\<Module>` in `migrations/<Module>/`; all modules share the `doctrine_migration_versions` table in `public`.
- Create a migration for one module with `make migration m=<Module>` (for example `make migration m=TowRequest`). The diff is filtered to that module's schema, so it never touches other modules or PostGIS objects.
- Run all migrations with `make migrate`; start over with `make db-reset`.
- **Cross-schema foreign keys are forbidden.** Modules reference each other by ID only, never by database constraint (spec §5.2).
- Table names in XML mappings are always schema-qualified (`tow_request.tow_request`). Quote reserved words: the schema `authorization` must be written as `"authorization"` in SQL.
- New module: add its folder, `config/services/<module>.yaml`, the ORM mapping and migration path, a schema migration, the name in `app.database_schemas` (`config/services/shared_kernel.yaml`), and regenerate `deptrac.yaml` with `make deptrac-generate`.

## Documentation

- [Backend specification](../internal-docs/backend-specification.md)
- [Business logic](../internal-docs/business-logic.md)
- [Setup plan](../tasks/setup-plan.md)
- [Authentication plan](../tasks/auth-plan.md)
