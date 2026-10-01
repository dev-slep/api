# Source layout

One folder per module under `src/` plus `SharedKernel/`. Root namespace: `App\`.

```
src/<Module>/
  Domain/          Model, Event, Repository, Policy, Exception   (framework-free)
  Application/     Command, Query, Port, ContractImplementation
  Contract/        Dto, Event                                     (the module's public API)
  Infrastructure/  Persistence/{Entity,Mapping,Mapper}, Http/{Controller,Request,Response}, Messaging, Scheduler
```

## Layer rules (enforced by Deptrac)

- **Domain** depends only on `SharedKernel\Domain`. No vendor code.
- **Application** depends on its own Domain and Contract, `SharedKernel` and **other modules' Contract**. No vendor code.
- **Contract** depends only on `SharedKernel\Contract`. No vendor code.
- **Infrastructure** may use its own module, other modules' Contract, `SharedKernel` and vendor code.
- PHP internal classes (`DateTimeImmutable`, `JsonException`, …) are allowed everywhere.

## Conventions

- Controllers declare the **full path** (`/api/v1/...`) and explicit `methods` in `#[Route]`; route imports have no prefix.
- Because Application can't use vendor code, message handlers are **not** annotated with `#[AsMessageHandler]`: they implement the `CommandHandler` / `QueryHandler` marker interfaces and are tagged through DI.
- Doctrine mappings are XML, in `Infrastructure/Persistence/Mapping`, mapping the records in `Infrastructure/Persistence/Entity` (never domain classes). Table names are schema-qualified.
- Classes are `final` by default; integration events are named `<Thing>V1`.
