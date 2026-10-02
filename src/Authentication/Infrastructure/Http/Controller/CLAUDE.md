# Http/Controller/

One controller per endpoint (register, login, refresh, logout, verify/resend email, forgot/reset password, social login, 2FA enrol/confirm/verify). They parse the request, dispatch a command on `CommandBus` and map the result to a response; no business logic. Every route needs an integration test with `#[CoversEndpoint]`.
