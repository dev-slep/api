# config/routes/

- `modules.yaml`: imports each module's controller directory as attribute routes. A new module needs an entry here.
- `framework.yaml`, `nelmio_api_doc.yaml`: framework and docs routes.
- `test.yaml`: test-only routes (fixture controllers). Loaded only when `APP_ENV=test`.

Controllers declare the full path (`/api/v1/...`) and explicit `methods` in `#[Route]`, so imports have no prefix.
