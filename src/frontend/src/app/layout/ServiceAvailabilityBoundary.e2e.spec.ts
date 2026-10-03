import type { Page } from '@playwright/test';
import { expect, test } from '../../shared/test/browser';

const healthy = { service: 'gateway', status: 'ok', services: { pim: { status: 'ok' } } };
const degraded = {
  service: 'gateway',
  status: 'degraded',
  services: { pim: { status: 'unavailable' } },
};
const id = '0195f582-9762-7c2a-9228-4060489e06d8';

async function mockAvailability(page: Page, available = true) {
  const state = { available, unreachable: false, requests: 0, writes: 0 };
  await page.clock.install();
  await page.route('**/healthcheck', (route) => {
    state.requests += 1;

    if (state.unreachable) {
      return route.abort();
    }

    return route.fulfill({
      status: state.available ? 200 : 503,
      json: state.available ? healthy : degraded,
    });
  });
  await page.route('**/pim/web/**', (route) => {
    if (route.request().method() !== 'GET') {
      state.writes += 1;
    }

    return route.fulfill({
      json: { products: [], product: { id, sku: 'ORIGINAL', deleted_at: null } },
    });
  });

  return state;
}

test.describe('Service-aware navigation', () => {
  test('stays invisible while healthy and marks unavailable menu items without blocking home', async ({
    page,
  }) => {
    const state = await mockAvailability(page);
    await page.goto('/products');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Products');
    await expect(page.getByTestId('connection-status')).toHaveCount(0);
    state.available = false;
    await page.clock.runFor(15000);
    await expect(page.getByTestId('service-unavailable')).toBeVisible();
    await expect(page.getByTestId('connection-status')).toContainText('PIM');
    const navigation = page.getByRole('navigation', { name: 'Main navigation' });
    await expect(navigation.getByText('Unavailable', { exact: true })).toHaveCount(3);
    await page.getByRole('link', { name: 'Go to home page' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Overview');
    await expect(page.getByTestId('connection-status')).toBeVisible();
    await navigation.getByRole('link', { name: 'Categories', exact: true }).click();
    await expect(page.getByTestId('service-unavailable')).toBeVisible();
    await page.goBack();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Overview');
    await page.goForward();
    await expect(page.getByTestId('service-unavailable')).toBeVisible();
  });

  for (const path of [
    '/products',
    '/products/new',
    `/products/${id}/edit`,
    '/categories',
    `/categories/${id}`,
    '/attributes',
    '/attributes/new',
    `/attributes/${id}/edit`,
  ]) {
    test(`blocks direct entry and reload of ${path} before sending data requests`, async ({
      page,
    }) => {
      await mockAvailability(page, false);
      const requests: string[] = [];
      page.on('request', (request) => {
        if (request.url().includes('/pim/web/')) {
          requests.push(request.url());
        }
      });
      await page.goto(path);
      await expect(
        page.getByRole('heading', { name: 'This page is temporarily unavailable' }),
      ).toBeVisible();
      await page.reload();
      await expect(
        page.getByRole('heading', { name: 'This page is temporarily unavailable' }),
      ).toBeVisible();
      expect(requests).toEqual([]);
    });
  }

  test('shows the connection screen for a gateway network failure and restores only after two successes', async ({
    page,
  }) => {
    const state = await mockAvailability(page);
    state.unreachable = true;
    await page.goto('/products/new');
    await expect(page.getByTestId('connection-status')).toContainText('gateway');
    await expect(page.getByTestId('service-unavailable')).toBeVisible();
    state.unreachable = false;
    await page.getByRole('button', { name: 'Check again' }).click();
    await expect(page.getByRole('button', { name: 'Check again' })).toBeEnabled();
    await expect(page.getByTestId('service-unavailable')).toBeVisible();
    await page.getByRole('button', { name: 'Check again' }).click();
    await expect(page.getByRole('heading', { name: 'Create product' })).toBeVisible();
    await expect(page.getByTestId('connection-status')).toHaveCount(0);
  });
});

test.describe('Outages during editing', () => {
  test('retains unsaved fields through automatic recovery without submitting them', async ({
    page,
  }) => {
    const state = await mockAvailability(page);
    await page.goto(`/products/${id}/edit`);
    const sku = page.getByRole('textbox', { name: 'SKU', exact: true });
    await expect(sku).toHaveValue('ORIGINAL');
    await sku.fill('UNSAVED');
    state.available = false;
    await page.clock.runFor(15000);
    await expect(page.getByTestId('service-unavailable')).toBeVisible();
    await expect(sku).toBeHidden();
    state.available = true;
    await page.clock.runFor(5000);
    await expect(page.getByTestId('service-unavailable')).toBeVisible();
    await page.clock.runFor(5000);
    await expect(sku).toHaveValue('UNSAVED');
    await expect(sku).toBeVisible();
    expect(state.writes).toBe(0);
  });

  test('removes a teleported confirmation and leaves home navigation usable', async ({ page }) => {
    const state = await mockAvailability(page);
    await page.goto(`/products/${id}/edit`);
    await page.getByRole('button', { name: 'Delete product', exact: true }).click();
    await expect(page.getByRole('alertdialog')).toBeVisible();
    state.available = false;
    await page.clock.runFor(15000);
    await expect(page.getByTestId('service-unavailable')).toBeVisible();
    await expect(page.getByRole('alertdialog')).toHaveCount(0);
    await page.getByRole('link', { name: 'Go to home page' }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Overview');
    expect(state.writes).toBe(0);
  });

  test('discards the hidden draft when leaving its URL', async ({ page }) => {
    const state = await mockAvailability(page);
    await page.goto('/products/new');
    await page.getByRole('textbox', { name: 'SKU', exact: true }).fill('DISCARD-ME');
    state.available = false;
    await page.clock.runFor(15000);
    await expect(page.getByTestId('service-unavailable')).toBeVisible();
    await page.getByRole('link', { name: 'Go to home page' }).click();
    state.available = true;
    await page.clock.runFor(5000);
    await page.clock.runFor(5000);
    await expect(page.getByTestId('connection-status')).toHaveCount(0);
    await page.goto('/products/new');
    await expect(page.getByRole('textbox', { name: 'SKU', exact: true })).toHaveValue('');
  });
});
