import { test as base, expect } from '@playwright/test';

// Application tests are independent of running gateway/PIM containers.
export const test = base.extend<{ healthyGateway: void }>({
  healthyGateway: [
    async ({ page }, use) => {
      await page.route('**/healthcheck', (route) =>
        route.fulfill({
          json: {
            service: 'gateway',
            status: 'ok',
            services: { pim: { status: 'ok' } },
          },
        }),
      );
      await use();
    },
    { auto: true },
  ],
});

export { expect };
