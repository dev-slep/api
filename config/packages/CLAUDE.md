# config/packages/

One YAML file per bundle. Notable ones:

- `security.yaml`: stateless `api` firewall with the custom `JwtAuthenticator`; `access_control` decides which paths need which role.
- `messenger.yaml`: Doctrine transports, buses and middleware (transaction, outbox, audit, correlation).
- `doctrine.yaml`: one XML-mapping entry per module and `schema_filter` (excludes `processed_event` and `rate_limit`, which are managed by migrations).
- `rate_limiter.yaml`: limiters for the auth endpoints (Postgres-backed storage, in-memory in test).
- `mailer.yaml`, `lock.yaml`, `cache.yaml`, `nelmio_api_doc.yaml`, `translation.yaml`.

Files may have `when@test` / `when@prod` sections; check before assuming a value applies everywhere.
