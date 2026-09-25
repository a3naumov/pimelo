FROM node:24.18.1-bookworm AS base

WORKDIR /app

FROM base AS dev

ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

WORKDIR /tmp/playwright-install
COPY src/frontend/package.json src/frontend/package-lock.json ./

# Install the browser revisions required by the locked Playwright version.
RUN npm ci --ignore-scripts --no-audit --no-fund \
    && npx --no-install playwright install --with-deps chromium firefox webkit \
    && chmod -R a+rX /ms-playwright \
    && rm -rf /tmp/playwright-install /root/.npm /var/lib/apt/lists/*

WORKDIR /app
USER node

FROM base AS production

USER node
