# src/

One folder per module plus `SharedKernel/` and `Kernel.php`. Root namespace `App\`. Full rules are in `README.md`; the essentials:

```
src/<Module>/
  Domain/          framework-free model, repository interfaces, policies, domain events, exceptions
  Application/     commands, queries, handlers, ports, services, Contract implementations
  Contract/        the only part other modules may use: interfaces, DTOs, integration events (<Thing>V1)
  Infrastructure/  Doctrine, HTTP controllers, Messenger subscribers, adapters, console, schedulers
```

Rules (Deptrac enforces them, `make deptrac`):
- Domain: only `SharedKernel\Domain`. No vendor code.
- Application: own Domain/Contract, SharedKernel, other modules' **Contract**. No vendor code.
- Contract: only `SharedKernel\Contract`.
- Infrastructure: own module, other modules' Contract, SharedKernel, vendor.
- Modules talk through Contract interfaces and integration events, never through each other's internals.
- Handlers implement `CommandHandler`/`QueryHandler` (no `#[AsMessageHandler]`: Application has no vendor code). Classes are `final`.
- Refusals that must leave a trace (failed login) are returned as results; thrown exceptions roll the transaction back.

Quality gate for any change: `make qa` (cs, phpstan max, deptrac, test-policy, all suites). The test policy requires tests for every public method, endpoint, console command and contract method (see `tests/CLAUDE.md`).

Modules: Audit, Authentication (built), Authorization, Bidding, Driver, Job, Notification, Penalty, Review, Subscription, TowRequest, Tower. Responsibilities are in `../../internal-docs/backend-specification.md` §6.
