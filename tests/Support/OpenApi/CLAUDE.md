# Support/OpenApi/

`OpenApiDocument` and `OpenApiResponseValidator` back `assertMatchesOpenApiSchema()`: a response must match `openapi/openapi.yaml` (follows `$ref` to `components/responses`; parameters need explicit `style` and `explode`). `Fixtures/` has a tiny spec for the validator's own tests.
