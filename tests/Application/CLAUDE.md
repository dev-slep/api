# tests/Application/

Booted-kernel tests without a database: in-memory repositories, in-memory Messenger, fakes. Extend `Support\ApplicationTestCase` (or the module's own base such as `AuthenticationApplicationTestCase`). One class-level `#[CoversContractMethod(Interface::class, 'method')]` per module contract method. Use these for end-to-end flows across handlers without I/O.
