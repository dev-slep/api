# src/Audit/

Append-only record of every state-changing command and of the other modules' integration events, readable by admins at `GET /api/v1/admin/audit` (spec §8). No other module depends on Audit.

Status: implemented.

- **Commands:** `Infrastructure/Messaging/AuditCommandAuditor` implements `SharedKernel\Application\CommandAuditor` (the bus middleware calls it outside the transaction, so failures are recorded too). Actor: signed-in user (id, role), `anonymous` (HTTP request, nobody signed in, e.g. login) or `system` (worker, scheduler, console). A failure while writing is logged and swallowed: auditing never breaks the command.
- **Events:** `RecordIntegrationEventSubscriber` handles every `IntegrationEvent` on the event bus, idempotent through `audit.processed_event`. Events carry no request, so the actor is the account named by `accountId`, otherwise the system.
- **Payloads:** `Application/Service/PayloadExtractor` flattens a command or event to its public properties; `Domain/Policy/PayloadMasker` replaces the value of credential-looking keys (password, token, secret, code, hash, key, ...) with `***` before storing.
- **Storage:** `audit.audit_entry`, plain SQL through `DbalAuditEntryRepository` (no ORM mapping, so `Persistence/Mapping/` stays empty and the table is excluded from the Doctrine `schema_filter`; the migration owns it). A trigger rejects UPDATE and DELETE. Retention is still open (spec §16, question 4).
- **Reading:** `Contract/AuditTrail` (admin features of other modules) and the `ListAuditEntries` query behind `ListAuditEntriesController` (filters: actorId, targetId, name, kind, from, to; pages of at most 100, newest first). `/api/v1/admin/*` is `ROLE_ADMIN` only (`security.yaml`).
- **Tests:** application tests run with `InMemoryAuditEntryRepository` (set in `ApplicationTestCase`), so every command is audited there without a database.

Layout and dependency rules are in `../CLAUDE.md`.
