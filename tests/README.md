# Tests

Three suites, one `test` environment (`.env.test`, database `slep_test`). See spec §12.

| Suite | Base class | What it touches |
|---|---|---|
| `unit` | PHPUnit `TestCase` | Nothing: pure domain and application logic |
| `application` | `Support\ApplicationTestCase` | Kernel with in-memory Messenger/Lock and fakes; **never** the DB |
| `integration` | `Support\IntegrationTestCase` | Everything real (Postgres+PostGIS, Messenger, MinIO, Mailpit); WireMock for third parties |

`make test` runs the suites as separate PHPUnit processes (unit → application → integration).

## Classes are `final`: double interfaces, not classes

Production classes are `final` by default, and PHPUnit cannot create doubles of final classes. Unit tests therefore double **interfaces** (ports, repositories, contracts) and use the fakes in `tests/Support/Fake/` (`FrozenClock`, `SequentialIdGenerator`, `InMemory*` repositories). If you feel the need to mock a concrete class, extract a port.

## Coverage metadata is mandatory

`requireCoverageMetadata` is on: every test class needs `#[CoversClass]`, `#[CoversNothing]` (smoke tests only) or one of the custom attributes below, otherwise the run fails.

- `#[CoversEndpoint('GET', '/api/v1/...')]` — integration test of an HTTP endpoint
- `#[CoversConsoleCommand('app:...')]` — integration test of a console command
- `#[CoversContractMethod(Interface::class, 'method')]` — application test of a contract method (PHPUnit's `CoversMethod` does not accept interfaces)

PHPUnit itself only understands its own attributes, so a test that uses a custom attribute also declares what it executes with `#[CoversClass]` (for example `#[CoversClass(HealthController::class)]` next to `#[CoversEndpoint('GET', '/health/live')]`).

`make test-policy` fails if an endpoint, command, contract method or public method has no such test (spec §12.2).

## Repository contract tests

`Support\RepositoryContractTestCase` is the pattern for running the same behavioural tests against the in-memory and the Doctrine implementation of a repository:

```php
abstract class TowRequestRepositoryContractTest extends RepositoryContractTestCase
{
    abstract protected function repository(): TowRequestRepository;

    public function testSavedRequestCanBeFound(): void { /* ... */ }
}

#[CoversClass(InMemoryTowRequestRepository::class)]
final class InMemoryTowRequestRepositoryTest extends TowRequestRepositoryContractTest { /* repository(): in-memory */ }

#[CoversClass(DoctrineTowRequestRepository::class)]
final class DoctrineTowRequestRepositoryTest extends TowRequestRepositoryContractTest { /* repository(): Doctrine */ }
```

No concrete repository exists yet.

## Layout

`tests/Support/{Attribute,Builder,Fake,Fixtures,Mother,Policy,WireMock}` hold shared test code; `tests/{Unit,Application,Integration}/<Module>/` mirror `src/`.
