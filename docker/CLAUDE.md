# docker/

Container configuration used by `docker-compose.yml` and the `Dockerfile`.

- `nginx/`: web server config in front of PHP-FPM.
- `php/`: `php.ini-dev|prod`, `php-fpm.conf`, `xdebug.ini`, `entrypoint.sh`.
- `postgres/init/`: first-start script creating `slep_test` and enabling PostGIS.
- `supervisor/`: runs nginx, php-fpm and the Messenger worker in the app container.
- `wiremock/`: stubs for third parties (JWKS, FCM, payment) used by integration tests.

Everything is driven through the Makefile (`make up`, `make logs`, `make worker-restart`).
