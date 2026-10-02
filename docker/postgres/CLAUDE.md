# docker/postgres/

`init/01-databases.sh` runs once on first initialisation of the data volume: creates `slep_test` and enables PostGIS in both databases. To re-run it, drop the volume (`make down` plus removing it) or use `make db-reset` for the schemas only.
