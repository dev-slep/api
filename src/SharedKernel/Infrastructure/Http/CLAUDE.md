# Http/

HTTP plumbing for every module: `ProblemDetailsFactory` and `ProblemDetailsListener` (RFC 9457 `application/problem+json`; Security exceptions are left to the firewall), `JsonOnlyListener` (415 for non-JSON bodies), `LocaleListener` (`Accept-Language`), `ApiPath`, `MoneyNormalizer`, and `IdempotentRequests` (`Idempotency-Key` replay for POST; wrap the handler in `run()`). Throw a `ProblemType` exception to answer with a problem response.
