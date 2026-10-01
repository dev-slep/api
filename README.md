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
| `tools/` | Custom QA tooling (e.g. the test-policy check) |
| `tests/` | `Unit`, `Application`, `Integration` and `Support` |

## Getting started

```sh
make install
make qa
```

`make install` and `make qa` are added in later setup tasks (see `tasks/setup-plan.md`); only Docker and Make are required locally.

## Documentation

- [Backend specification](../internal-docs/backend-specification.md)
- [Business logic](../internal-docs/business-logic.md)
- [Setup plan](../tasks/setup-plan.md)
