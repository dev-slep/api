# Contract/Event/

Versioned integration events consumed by Audit and others. Event names look like `authentication.user_registered.v1`. Add a new event here **and** to `tests/Unit/Authentication/Contract/ContractEventsTest` (it counts them and rejects secret-like field names).
