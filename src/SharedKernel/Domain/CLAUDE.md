# SharedKernel/Domain/

Framework-free base types: `AggregateRoot` (records domain events), `EntityId`, `Money` with `Currency` (integer minor units, overflow and mismatch guarded), `Clock` and `IdGenerator` ports, `ProblemType` (exceptions that map to an HTTP problem) and `DomainException`. Depend on nothing but PHP.
