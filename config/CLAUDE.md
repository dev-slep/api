# config/

Symfony configuration. Autowiring and attributes are the default; YAML is for what attributes cannot express.

- `packages/`: one file per bundle (security, messenger, doctrine, rate_limiter, mailer, ...).
- `routes/`: `modules.yaml` imports each module's `Infrastructure/Http/Controller/` (attribute routes, no prefix); `test.yaml` is imported only in the test env.
- `services/`: one file per module (`<module>.yaml`) with its bindings and settings defaults, plus `shared_kernel.yaml`.
- `services.yaml`: global defaults. `bundles.php`, `reference.php`, `preload.php` are Flex/Symfony generated: do not hand-edit bundles.
- `jwt/`: RS256 key pair for access tokens. Generate with `make jwt-keys`; never commit real keys.
- `secrets/`: Symfony secrets vault. Real secrets go in `.env.local` or the vault, never in `.env`.

Adding a module service: edit `services/<module>.yaml`. Adding a bundle: `composer require`, let the recipe configure it.
