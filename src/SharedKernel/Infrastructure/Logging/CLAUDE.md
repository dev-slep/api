# Logging/

Monolog processors: `MaskingProcessor` hides secrets (passwords, tokens) before they reach a log line; `CorrelationProcessor` adds the correlation id. Register new processors in `config/packages/monolog.yaml`.
