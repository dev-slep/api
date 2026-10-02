# Health/

`HealthCheck` implementations (`DatabaseHealthCheck`, `MessengerTransportHealthCheck`) returning `HealthResult`, served by `Http/Controller/HealthController` at `/health/live` and `/health/ready`. Add a class implementing `HealthCheck` to add a probe.
