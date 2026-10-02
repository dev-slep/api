# Authentication/Application/

Use cases. `Command/` (one command + one handler per use case), `Command/Result/` (return values, including failures that must commit), `Service/` (`SessionFactory`, `SecurityEvents`, `AuthenticationEventMapper`, `TwoFactorEnroller`), `Port/` (interfaces to adapters: access tokens, mail, social verifier, TOTP), `ContractImplementation/` (`AccountLookupFacade`). No vendor code. Handlers implement `CommandHandler` and are called through the `CommandBus`.
