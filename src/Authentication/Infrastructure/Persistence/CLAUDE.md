# Persistence/

`Doctrine*Repository` and `InMemory*Repository` per aggregate (same contract tests), plus `AuthenticationProcessedEvents`. Doctrine never maps domain classes: `Entity/` holds records, `Mapping/` the XML, `Mapper/` converts both ways. Schema: `authentication` (migrations in `migrations/Authentication`).
