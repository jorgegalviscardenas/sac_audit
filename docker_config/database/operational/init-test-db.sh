#!/bin/bash
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS operational_test;
    GRANT ALL PRIVILEGES ON DATABASE operational_test TO postgres;
EOSQL
