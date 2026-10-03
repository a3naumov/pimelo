import { expect, test } from '@playwright/test';

test.describe('Product form changes', () => {
  const id = '0195f582-9762-7c2a-9228-4060489e06d8';

  test('requires a changed value and blocks unchanged submissions, including Enter', async ({
    page,
  }) => {
    const writes: string[] = [];
    await page.route(`**/pim/web/products/${id}{,?*}`, (route) => {
      if (route.request().method() === 'PATCH') {
        writes.push(route.request().method());
      }

      return route.fulfill({ json: { product: { id, sku: 'ORIGINAL', deleted_at: null } } });
    });
    await page.goto(`/products/${id}/edit`);
    const input = page.getByRole('textbox', { name: 'SKU', exact: true });
    const save = page.getByRole('button', { name: 'Save changes' });
    await expect(input).toHaveValue('ORIGINAL');
    await expect(save).toBeDisabled();
    await input.press('Enter');
    await input.fill('CHANGED');
    await expect(save).toBeEnabled();
    await input.fill('ORIGINAL');
    await expect(save).toBeDisabled();
    await input.fill('  ORIGINAL  ');
    await expect(save).toBeDisabled();
    await page.locator('form').dispatchEvent('submit');
    expect(writes).toEqual([]);
  });

  test('blocks repeat saves and compares against the last saved value', async ({ page }) => {
    let product = { id, sku: 'ORIGINAL', deleted_at: null };
    let writes = 0;
    let release!: () => void;
    const gate = new Promise<void>((resolve) => {
      release = resolve;
    });
    await page.route(`**/pim/web/products/${id}{,?*}`, async (route) => {
      if (route.request().method() === 'PATCH') {
        writes++;
        await gate;
        product = { ...product, sku: route.request().postDataJSON().sku as string };
      }

      return route.fulfill({ json: { product } });
    });
    await page.goto(`/products/${id}/edit`);
    const input = page.getByRole('textbox', { name: 'SKU', exact: true });
    await expect(input).toHaveValue('ORIGINAL');
    await input.fill('  UPDATED  ');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    await expect(input).toBeDisabled();
    await page.locator('form').dispatchEvent('submit');
    release();
    await expect(input).toHaveValue('UPDATED');
    const save = page.getByRole('button', { name: 'Save changes' });
    await expect(save).toBeDisabled();
    expect(writes).toBe(1);
    await input.fill('ORIGINAL');
    await expect(save).toBeEnabled();
    await input.fill('UPDATED');
    await expect(save).toBeDisabled();
  });
});

test.describe('Product form validation', () => {
  test('rejects blank and oversized SKUs before submitting', async ({ page }) => {
    const requests: string[] = [];
    await page.route('**/pim/web/products/', (route) => {
      requests.push(route.request().method());

      return route.abort();
    });
    await page.goto('/products/new');
    await page.getByRole('button', { name: 'Create product' }).click();
    await expect(page.getByText('SKU must be a non-empty string.')).toBeVisible();
    await page.getByRole('textbox', { name: 'SKU', exact: true }).fill('a'.repeat(256));
    await page.getByRole('button', { name: 'Create product' }).click();
    await expect(page.getByText('SKU must not exceed 255 characters.')).toBeVisible();
    expect(requests).toEqual([]);
  });

  const failures = [
    { status: 409, message: 'A product with this SKU already exists.' },
    { status: 422, message: 'This SKU is invalid.' },
    { status: 500, message: 'The server could not complete the request. Please try again.' },
    { status: 0, message: 'Could not reach the server. Check your connection and try again.' },
  ];

  for (const { status, message } of failures) {
    test(`preserves input after a ${status || 'network'} failure`, async ({ page }) => {
      await page.route('**/pim/web/products/', (route) =>
        status === 0
          ? route.abort()
          : route.fulfill({
              status,
              json:
                status === 422
                  ? { violations: [{ propertyPath: 'sku', title: 'This SKU is invalid.' }] }
                  : { error: 'A product with this SKU already exists.' },
            }),
      );
      await page.goto('/products/new');
      const input = page.getByRole('textbox', { name: 'SKU', exact: true });
      await input.fill('MY-SKU');
      await page.getByRole('button', { name: 'Create product' }).click();
      await expect(page.getByText(message, { exact: true })).toBeVisible();
      await expect(input).toHaveValue('MY-SKU');
      await expect(page.getByRole('button', { name: 'Create product' })).toBeEnabled();
    });
  }
});
