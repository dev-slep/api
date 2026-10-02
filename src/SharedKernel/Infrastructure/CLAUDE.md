# SharedKernel/Infrastructure/

Adapters for the SharedKernel ports and cross-cutting framework wiring. Subfolders: `Correlation/` (request/message correlation id), `Health/` (liveness/readiness checks), `Http/` (listeners, problem details, idempotency), `Id/` (UUIDv7), `Logging/` (masking and correlation processors), `Messaging/` (buses, outbox, transaction and audit middleware), `Persistence/` (transaction, processed events, schema declaration), `Scheduler/` (`ScheduledTaskProvider`), `Time/` (`SystemClock`).
