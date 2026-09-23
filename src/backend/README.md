# Pimelo backend

Symfony 8.1 application

## Checks before pushing

Run the same application checks as GitHub Actions from the backend directory:

```sh
composer check
```

Or from the repository root with the backend and PostgreSQL containers running:

```sh
docker compose exec -T backend composer check
```

The command checks formatting without changing files, runs PHPStan, creates the
test database if missing, applies pending test migrations, validates the Doctrine
schema and runs PHPUnit, in that order. It stops at the first failed step and
returns a non-zero exit code. In particular, PHPUnit does not run if PHPStan fails.

Install dependencies first with `composer install` and configure the test database
as described below. Database commands explicitly use `--env=test` and Doctrine's
`_test` suffix; they do not reset or drop the database. They can create the test
database and modify its schema through migrations. Docker image builds and
dependency installation remain separate setup steps, as in CI.

## Web API documentation

Nelmio generates an OpenAPI 3.0.3 specification for products, categories,
product-category links and the healthcheck. In `dev` and `test`, fetch
`GET /web/api-docs.json` (by default, `http://localhost:8080/web/api-docs.json` in Docker).
There is no documentation route in `prod`, and no UI, Twig or Asset dependency.
The specification uses the `web` area. A separate `api` area and documentation
route will be added when API endpoints exist; `/api/doc.json` is not registered.

Export the specification from the repository root:

```sh
docker compose exec -T backend php bin/console nelmio:apidoc:dump --env=dev --area=web --format=json
docker compose exec -T backend php bin/console nelmio:apidoc:dump --env=dev --area=web --format=yaml
```

Redirect stdout to a local file when needed; do not commit generated documents.
Routes and request schemas are inferred from Symfony metadata. Operation
descriptions live in infrastructure controllers; reusable response schemas live
in `config/packages/nelmio_api_doc.yaml`. Domain and application models do not
depend on Nelmio or OpenAPI attributes. Keep these descriptions up to date
when changing the HTTP contract.

The current development RoadRunner configuration starts a fresh worker for each
request. If using persistent workers during development, restart those backend
workers after changing documentation metadata so cached code/specifications are
reloaded.

## Tests

PHPUnit runs in the `test` environment using `phpunit.dist.xml`. Tests are grouped
by type, with each directory mirroring the corresponding path in `src/`:

- `tests/Unit/`: isolated logic, without a database.
- `tests/Integration/`: Doctrine repositories and real PostgreSQL operations.
- `tests/Functional/`: HTTP requests through the Symfony kernel.

### Configure the test database

Database tests use the existing `postgres` service, but a separate database.
Doctrine appends `_test` to the database name in `DATABASE_URL`: `appdb` becomes
`appdb_test`. If `TEST_TOKEN` is set, it is appended after `_test` for worker-specific
databases. Do not add the suffix to the URL yourself.

Create `src/backend/.env.test.local` and set your local PostgreSQL credentials:

```dotenv
DATABASE_URL="postgresql://USER:PASSWORD@postgres:5432/appdb?serverVersion=18.4&charset=utf8"
```

Replace `USER` and `PASSWORD` with the credentials of your Docker PostgreSQL service.
The example uses the Docker hostname `postgres`; use the published host and port
if running PHP outside Docker. `.env.test.local` is ignored by Git. Symfony does
not load `.env.local` in the `test` environment. Alternatively, provide
`DATABASE_URL` as an environment variable, as CI does.

With dependencies installed and the `backend` and `postgres` containers running,
prepare the database from the repository root:

```sh
docker compose exec -T backend php bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec -T backend php bin/console doctrine:migrations:migrate --env=test --no-interaction
docker compose exec -T backend php bin/console doctrine:schema:validate --env=test
```

Create the database once and rerun migrations when the schema changes. Preparation
is separate from PHPUnit. Always use `--env=test` for these commands.

### Run tests

From the backend directory with a compatible local PHP installation, or inside the
backend container:

```sh
php bin/phpunit
```

From the repository root using Docker:

```sh
# Full suite
docker compose exec -T backend php bin/phpunit

# Unit tests only; no database required
docker compose exec -T backend php bin/phpunit tests/Unit

# A specific test class
docker compose exec -T backend php bin/phpunit --filter ProductControllerTest

# Check independence from test order
docker compose exec -T backend php bin/phpunit --order-by=random
```

### Data isolation

`dama/doctrine-test-bundle` and its PHPUnit extension automatically isolate tests
using transactions and roll back their changes between tests and at the end of
the run. Each test creates its own fixtures; manual cleanup or a custom trait is
not needed. The schema and migration history remain available for subsequent runs.

Start with an empty test database: rollback does not remove data inserted manually
outside the tests. PostgreSQL sequences are not rolled back, so tests must not
depend on exact auto-increment values. Products use UUIDs.

Transaction isolation covers requests made through the Symfony kernel in the same
PHP process, not external HTTP servers or separate workers. Apply migrations before
the suite, not inside tests. For parallel runs, prepare a separate test database
for each worker using its `TEST_TOKEN`.

The [Backend checks workflow](../../.github/workflows/backend-checks.yml) installs
dependencies and runs the same `composer check` command against PostgreSQL.

## Static analysis

Run PHPStan at level 10 for `src/` from the backend directory:

```sh
composer analyse
```

From the repository root using Docker:

```sh
docker compose exec -T backend composer analyse
```

The backend GitHub Actions workflow runs this check after installing dependencies
and before running migrations and tests. PHPStan errors fail the job.

## Code style

PHP CS Fixer uses the `@Symfony` rules in `.php-cs-fixer.dist.php`. It checks PHP
files throughout the backend, including `src/`, `tests/`, and migrations. Vendor
dependencies, `var/`, and generated `config/bundles.php` and `config/reference.php`
are excluded.

From the backend directory:

```sh
# Apply formatting fixes
composer format

# Check formatting without changing files
composer format:check
```

From the repository root using Docker:

```sh
docker compose exec -T backend composer format
docker compose exec -T backend composer format:check
```

The check prints a diff and exits with a non-zero status if formatting changes
are needed. CI runs it before PHPStan and tests; it never fixes files automatically.
Formatting checks complement static analysis and tests; they do not replace them.
