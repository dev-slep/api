# src/SharedKernel/

Code every module may use: base types, buses, and cross-cutting infrastructure. Keep it small and free of business rules; anything specific to one module belongs in that module.

- `Domain/`: `AggregateRoot`, `EntityId`, `DomainEvent`, `Money`/`Currency`, `Clock`, `IdGenerator`, `ProblemType` and `DomainException`.
- `Contract/`: `IntegrationEvent`, `UserId`, `Currency`, the types modules expose across boundaries.
- `Application/`: `Command`, `Query`, handler marker interfaces, `CommandBus`/`QueryBus`/`EventBus`, `Transaction`, `IdempotentHandling` and `ProcessedEvents` (consumer idempotency), `CommandAuditor`.
- `Infrastructure/`: Messenger buses and middleware (transaction, transactional outbox, audit, correlation), HTTP listeners and problem details, health checks, logging, scheduler, UUIDv7 and system clock.

Changes here affect all modules: run `make qa`.
