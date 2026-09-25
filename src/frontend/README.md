# Pimelo frontend

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
