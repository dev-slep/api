# RateLimit/

`RateLimitListener` applies the per-endpoint limits from `config/packages/rate_limiter.yaml` (429 + `Retry-After`); `DbalRateLimiterStorage` keeps counters in `authentication.rate_limit` (in-memory in the test env).
