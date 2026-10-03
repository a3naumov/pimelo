import { expect, test } from '@playwright/test';

test.describe('Creating a product', () => {
  test('normalizes the SKU, blocks duplicate submission, and refreshes the list', async ({
    page,
  }) => {
    const id = '0195f582-9762-7c2a-9228-4060489e06d8';
    const products: { id: string; sku: string }[] = [];
    let release!: () => void;
    const gate = new Promise<void>((resolve) => {
      release = resolve;
    });
    const writes: unknown[] = [];
    await page.route('**/pim/web/products/', async (route) => {
      if (route.request().method() === 'GET') {
        return route.fulfill({ json: { products } });
      }

      writes.push(route.request().postDataJSON());
      await gate;
      const product = { id, sku: route.request().postDataJSON().sku as string, deleted_at: null };
      products.push(product);
      await route.fulfill({ status: 201, json: { product } });
    });
    await page.goto('/products');
    await expect(page.getByText('No products yet')).toBeVisible();
    await page.getByRole('link', { name: 'Create product' }).click();
    await page.getByRole('textbox', { name: 'SKU', exact: true }).fill('  SKU-NEW  ');
    await page.getByRole('button', { name: 'Create product' }).click();
    await expect(page.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    await expect(page.getByRole('textbox', { name: 'SKU', exact: true })).toBeDisabled();
    release();
    await expect(page).toHaveURL(`/products/${id}/edit`);
    await expect(page.getByRole('textbox', { name: 'SKU', exact: true })).toHaveValue('SKU-NEW');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    expect(writes).toEqual([{ sku: 'SKU-NEW' }]);
    await page.getByRole('link', { name: 'Back to products' }).click();
    await expect(page.getByRole('table').getByRole('link', { name: 'SKU-NEW' })).toBeVisible();
  });
});
