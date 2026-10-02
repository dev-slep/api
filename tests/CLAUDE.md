# tests/

Three PHPUnit suites sharing one `test` environment (`.env.test`, database `slep_test`). Details: `README.md`.

- `Unit/`: pure PHP, no kernel, no DB. Double **interfaces** (classes are `final`).
- `Application/`: kernel with in-memory transports and fakes, no DB.
- `Integration/`: everything real (Postgres/PostGIS, Messenger, Mailpit, MinIO), WireMock for third parties.
- `Support/`: shared base classes, fakes, builders, policy checker.

Directories mirror `src/<Module>/`. Run with `make test-unit f=<name>`, `make test-application`, `make test-integration`, or `make test`. `make test-policy` fails if any public method, endpoint, console command or contract method lacks its test. Every test class needs coverage metadata (`#[CoversClass]`, `#[CoversEndpoint]`, ...).
