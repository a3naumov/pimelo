#!/bin/sh
set -eu

. /etc/monitoring/check-env.sh

# psql variables are quoted as SQL literals; never interpolate secrets into SQL or argv.
psql -X --set=ON_ERROR_STOP=1 <<'SQL'
\getenv monitoring_password MONITORING_POSTGRES_PASSWORD
BEGIN;
SELECT 'CREATE ROLE pimelo_monitoring LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION'
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'pimelo_monitoring')
\gexec
DO $$
BEGIN
    IF EXISTS (
        SELECT FROM pg_roles
        WHERE rolname = 'pimelo_monitoring'
          AND (rolsuper OR rolcreatedb OR rolcreaterole OR rolreplication OR rolbypassrls)
    ) THEN
        RAISE EXCEPTION 'Refusing to reuse a privileged pimelo_monitoring role';
    END IF;
END;
$$;
SELECT format('ALTER ROLE pimelo_monitoring LOGIN PASSWORD %L', :'monitoring_password')
\gexec
GRANT pg_monitor TO pimelo_monitoring;
GRANT CONNECT ON DATABASE postgres TO pimelo_monitoring;
COMMIT;
SQL
