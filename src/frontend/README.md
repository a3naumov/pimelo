# Pimelo frontend

## Gateway integration

Create the local environment file from the repository root before starting Vite:

```sh
cp src/frontend/.env.example src/frontend/.env
```

The `.env` file is ignored by Git; `.env.example` provides the shared defaults.

The browser sends requests to Caddy, which forwards `/pim/web` to gateway and then
to PIM's internal `/web` routes.
Vite only serves the frontend; neither development nor preview proxies API requests.
Axios uses a 15-second timeout; TanStack Query supplies cancellation signals for reads.

```dotenv
# src/frontend/.env
VITE_GATEWAY_URL=http://localhost
```

Use Caddy's browser-accessible HTTP(S) origin, including `SERVICE_CADDY_PORT` if it
is not the default port. Do not include `/pim/web`, credentials, a query, or a fragment:
the shared client appends `/pim/web`. Missing or invalid values cause a configuration
error when the client initializes. Docker service names and container-only host
addresses are not appropriate for this browser setting.

Start Caddy, gateway, PIM, and frontend with their Compose profiles, then start Vite:

```sh
docker compose --profile caddy --profile gateway --profile pim --profile frontend up -d
docker compose exec frontend npm run dev -- --host 0.0.0.0
```

Open `http://localhost:5173`. Requests go to `http://localhost/pim/web`, so gateway
must allow the frontend origin through CORS. `NelmioCorsBundle` handles preflight
and response headers for all four service prefixes. In dev and test, the default origins are
`http://localhost:5173`, `http://127.0.0.1:5173`, `http://localhost:4173`, and
`http://127.0.0.1:4173`. Configure the `CORS_ALLOW_ORIGIN` regular expression in
the gateway environment when changing frontend ports or domains. Anchor it with
`^` and `$` and escape dots in domain names, for example:

```dotenv
CORS_ALLOW_ORIGIN='^https://(app|admin)\.example\.com$'
```

The base gateway value `(?!)` matches no origins. Restart gateway workers after
changing the value. Cookie credentials are not enabled. CORS configuration lives
in `src/gateway/config/packages/nelmio_cors.yaml`.

`VITE_GATEWAY_URL` is public and embedded in the browser bundle at build time.
Restart Vite after editing the frontend env; rebuild for a different production
API origin. Production must set its public Caddy origin before building and its
allowed frontend origins in the gateway environment. The base gateway environment
allows no cross-origin clients. Caddy still needs an `index.html` fallback when
serving the production frontend.

## Service availability

The application polls `${VITE_GATEWAY_URL}/healthcheck` once at startup, every
15 seconds while healthy, and every 5 seconds during an outage/recovery. Requests
time out after 5 seconds without automatic retries. Focus, returning to a visible
tab, and reconnecting trigger an immediate check; background polling is paused.
Concurrent automatic/manual checks share the same request.

Nothing is displayed while services are healthy. A failed dependency or gateway
connection shows a status bar and marks affected navigation links `Unavailable`.
The links still work, but open an explanation screen rather than the business UI.
The home page remains available, including when gateway cannot be reached.

Product, category and attribute routes declare `requiredServices: ['pim']` in
route metadata. This applies to lists, creation/editing, direct URLs, reloads and
browser history. On a cold protected route, no page or PIM request starts until
the first successful healthcheck. Missing required services fail closed.

One failed check blocks access immediately. Two consecutive successful checks
restore it automatically; another failure resets that counter. HTTP 503 with a
valid health report is a dependency outage, not a lost gateway connection. Neither
health reports nor access permission are persisted across page reloads.

If a service fails during editing, the current page is retained but hidden and
inert. Page-owned dialogs, sheets, menus and tooltips are removed using a shared
interaction context, while application navigation stays usable. New PIM requests
are blocked and reads are cancelled; writes already sent are never retried
automatically. Recovery refreshes active reads without resetting form drafts.
Unsaved input survives only while staying on the same URL: leaving or reloading
discards it. The outage screen offers home navigation and a manual check, which
does not bypass the two-success recovery rule.

Monitoring composables use TanStack Query in `shared/composable`, with pure availability
state transitions in `shared/model`, gateway HTTP contracts in `shared/api`, and
application-level integration in `app/composable`. Browser tests share an
automatic healthy-gateway fixture, with explicit overrides for outage scenarios.

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

Run `npm run format` to apply Oxlint/ESLint fixes followed by Prettier. ESLint
requires braces for control-flow bodies and blank lines around block statements
(including `if`, loops, `switch`, and `try`), and before
`return` and `throw` when preceded by another statement. `else`, `catch`, and
`finally` remain attached to their parent blocks. Prettier preserves these blank
lines and handles indentation, semicolons, and quotes.

Use `npm run lint` for lint fixes only, or `npm run format:prettier` for Prettier
only. `npm run check` verifies both conventions without modifying files.

Tests are colocated with the source they cover. Vitest covers API contracts,
validation, query cancellation, and cache consistency. Playwright intercepts API
requests to exercise CRUD and error states without a running backend or changing
development data. CI supplies `VITE_GATEWAY_URL=http://api.pimelo.test` explicitly;
local checks use your frontend `.env`. API requests remain intercepted in both cases.
