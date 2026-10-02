# tests/Unit/

Fast tests with no framework. One folder per module mirroring `src/`, plus `Policy/` and `Support/` (tests of the test tooling). Extend `PHPUnit\Framework\TestCase`; build handlers with the module's `*World` helper where it exists (`Support/Authentication/AuthenticationWorld`). Every concrete `src` class needs a test with `#[CoversClass]` covering each of its public methods (Doctrine records, controllers, Request/Response classes, Contract DTOs and message classes are exempt).
