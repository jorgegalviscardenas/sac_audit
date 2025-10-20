#!/bin/bash
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS audit_test;
    GRANT ALL PRIVILEGES ON DATABASE audit_test TO postgres;
EOSQL
