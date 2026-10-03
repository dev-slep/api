# migrations/

Doctrine migrations, one folder (and PostgreSQL schema) per module, namespace `Migrations\<Module>`. `Messenger/` holds the transport tables.

- Generate for one module: `make migration m=<Module>` (the filter keeps the diff to that module's schema; `processed_event`, `rate_limit` and `audit_entry` are excluded because migrations own them).
- Apply: `make migrate`; reset dev and test databases: `make db-reset`.
- Schema changes always go through migrations, never `doctrine:schema:update` or hand-run SQL.
- Version numbers are timestamps ordered across modules; never edit a migration that has run elsewhere, add a new one.
