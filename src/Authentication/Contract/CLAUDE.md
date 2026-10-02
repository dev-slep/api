# Authentication/Contract/

The module's public API; other modules import only from here. `CurrentUser` (id, roles, 2FA state of the caller), `AccountLookup` + `Dto/AccountView`, and the eleven `Event/*V1` integration events (extend `AuthenticationEvent`, immutable, no secrets). Changing an event is a breaking change: add a `V2` instead.
