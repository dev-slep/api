# docker/supervisor/

`supervisord.conf` runs nginx, php-fpm and the Messenger consumer inside the app container. After changing handlers or config, `make worker-restart`; follow output with `make worker-logs`.
