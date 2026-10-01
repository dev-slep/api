#!/bin/bash
# Runs once, on first initialisation of the data volume.
set -euo pipefail

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
    -c "CREATE DATABASE slep_test OWNER \"$POSTGRES_USER\""

for db in "$POSTGRES_DB" slep_test; do
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$db" \
        -c "CREATE EXTENSION IF NOT EXISTS postgis"
done
