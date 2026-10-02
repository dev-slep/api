# Console/

`app:auth:create-admin` (the only way to create an admin; asks for the password interactively or reads it with `--password-from-stdin`; the admin then enrols TOTP through the API) and `app:auth:purge-expired-tokens`. Each needs an integration test with `#[CoversConsoleCommand]`.
