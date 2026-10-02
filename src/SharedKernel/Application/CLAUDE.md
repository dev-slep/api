# SharedKernel/Application/

Application-layer ports: `Command`/`Query` and their `*Handler` marker interfaces, `CommandBus` (dispatch returns the handler result), `QueryBus`, `EventBus`, `Transaction`, `AggregateEventCollector`/`DomainEventMapper` (domain event to integration event for the outbox), `ProcessedEvents` and `IdempotentHandling` (make event consumers idempotent), `CommandAuditor`. Implementations are in `Infrastructure/`.
