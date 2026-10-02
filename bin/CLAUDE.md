# bin/

Entry scripts. Run them inside the app container (`make bash`, or `docker compose exec app ...`); the host PHP is not the project's PHP version.

- `console`: Symfony console. Use `make console c="debug:router"`.
- `phpunit`: PHPUnit wrapper. Prefer `make test-unit f=<name>`, `make test-application`, `make test-integration`.
