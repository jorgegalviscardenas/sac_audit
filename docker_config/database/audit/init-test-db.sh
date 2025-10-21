#!/bin/bash
set -e

# Check if database exists, create if it doesn't
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    SELECT 'CREATE DATABASE audit_test'
    WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'audit_test')\gexec
    GRANT ALL PRIVILEGES ON DATABASE audit_test TO postgres;
EOSQL
