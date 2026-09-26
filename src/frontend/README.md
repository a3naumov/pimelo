# Pimelo frontend

## Backend integration

Create the local environment file from the repository root before starting Vite:

```sh
cp src/frontend/.env.example src/frontend/.env
```

The `.env` file is ignored by Git; `.env.example` provides the shared defaults.

The browser sends JSON requests to `/web` on the frontend origin. Axios uses a
15-second timeout; TanStack Query supplies cancellation signals for reads.
Vite proxies `/web` in development and preview to the `BACKEND_URL` origin.
The default Docker configuration reaches the backend through its published host port:

```dotenv
# src/frontend/.env, read directly by Vite.
BACKEND_URL=http://host.docker.internal:8080
```

Set the port to match `SERVICE_BACKEND_PORT`. Restart Vite after changing this value:

```sh
docker compose exec frontend npm run dev -- --host 0.0.0.0
```

When running Vite directly on the host, set `BACKEND_URL=http://localhost:8080`
in `src/frontend/.env.local` to override the Docker default. Compose does not
forward this variable from the repository root `.env`. Use an HTTP(S) origin
without credentials, a path, query, or fragment.
Do not prefix this variable with `VITE_`: the target belongs to the development
server and is not embedded in the browser bundle. A production deployment must
route `/web` to the backend and provide an `index.html` fallback for frontend URLs.

## Run all checks

```sh
docker compose exec -T frontend npm run check
```

Inside the frontend container, run `npm run check` from `/app` directly.
The command runs these checks in order and stops when a step fails:

1. Prettier formatting verification.
2. Oxlint and ESLint without automatic fixes.
3. TypeScript checking and the production build.
4. Vitest tests in a single run.
5. Playwright tests in Chromium, Firefox, and WebKit.

The command uses CI mode locally as well: browsers run headlessly, and Playwright
starts and stops a preview server for the production build on port 4173. Keep that
port free inside the container before running checks. The HTML report is written
to `playwright-report` without opening a browser; failure artifacts are written to
`test-results`. These generated files are ignored by Git.

For automatic code fixes, use `npm run lint` or `npm run format` separately.

Tests are colocated with the source they cover. Vitest covers API contracts,
validation, query cancellation, and cache consistency. Playwright intercepts API
requests to exercise CRUD and error states without a running backend or changing
development data.
