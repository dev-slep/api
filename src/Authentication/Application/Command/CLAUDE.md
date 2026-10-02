# Application/Command/

A command is a readonly message (`RegisterUser`, `Login`, `SocialLogin`, ...); its `*Handler` does the work and returns a result or `void`. Handlers load aggregates through repositories, call domain methods, save, and publish through `SecurityEvents`. To add a use case: command class, handler, wire the controller to `CommandBus`, tests (`tests/Unit/Authentication/Application`, `AuthenticationWorld` builds handlers for unit tests).
