# Correlation/

Carries a correlation id from the HTTP request into Messenger messages (`CorrelationHttpListener`, `CorrelationMiddleware`, `CorrelationStamp`, `CorrelationContext`) so logs of one request and its async handlers can be joined. `Logging/CorrelationProcessor` adds it to every log record.
