# openapi/

`openapi.yaml` is the generated API contract. Regenerate with `make openapi` (`bin/console nelmio:apidoc:dump --format=yaml`) whenever a controller attribute changes. A test fails if the committed file is stale. Integration tests validate real responses against it (`assertMatchesOpenApiSchema`).
