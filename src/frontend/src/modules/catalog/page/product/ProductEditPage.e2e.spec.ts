import { expect, test } from '../../../../shared/test/browser';

const id = '0195f582-9762-7c2a-9228-4060489e06d8';
const product = { id, sku: 'SKU-01', deleted_at: null };

test.describe('Editing a product', () => {
  test('loads the SKU, saves in place, and refreshes the list', async ({ page }) => {
    let product = { id, sku: 'ORIGINAL', deleted_at: null };
    await page.route('**/pim/web/products/', (route) =>
      route.fulfill({ json: { products: [product] } }),
    );
    await page.route(`**/pim/web/products/${id}{,?*}`, async (route) => {
      if (route.request().method() === 'PATCH') {
        product = { id, sku: route.request().postDataJSON().sku as string, deleted_at: null };
      }

      await route.fulfill({ json: { product } });
    });
    await page.goto('/products');
    await page.getByRole('button', { name: 'Actions for ORIGINAL' }).click();
    await expect(page.getByRole('menuitem', { name: 'View', exact: true })).toHaveCount(0);
    await page.getByRole('menuitem', { name: 'Edit', exact: true }).click();
    const input = page.getByRole('textbox', { name: 'SKU', exact: true });
    await expect(input).toHaveValue('ORIGINAL');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await expect(
      page
        .getByRole('navigation', { name: 'Main navigation' })
        .getByRole('link', { name: 'Products', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
    await expect(
      page.getByRole('navigation', { name: 'breadcrumb' }).getByRole('link', { name: 'Products' }),
    ).toBeVisible();
    await input.fill('UPDATED');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page).toHaveURL(`/products/${id}/edit`);
    await expect(input).toHaveValue('UPDATED');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await page.getByRole('link', { name: 'Back to products' }).click();
    await expect(page.getByRole('table').getByRole('link', { name: 'UPDATED' })).toBeVisible();
    await expect(page.getByRole('table').getByText('ORIGINAL')).toHaveCount(0);
  });

  test('preserves the draft when saving fails', async ({ page }) => {
    await page.route(`**/pim/web/products/${id}{,?*}`, (route) =>
      route.request().method() === 'PATCH'
        ? route.fulfill({ status: 409, json: { error: 'A product with this SKU already exists.' } })
        : route.fulfill({ json: { product: { id, sku: 'ORIGINAL', deleted_at: null } } }),
    );
    await page.goto(`/products/${id}/edit`);
    const input = page.getByRole('textbox', { name: 'SKU', exact: true });
    await expect(input).toHaveValue('ORIGINAL');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await input.fill('RESERVED');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('A product with this SKU already exists.')).toBeVisible();
    await expect(input).toHaveValue('RESERVED');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeEnabled();
    await expect(page).toHaveURL(`/products/${id}/edit`);
  });
});

test.describe('Product editor navigation and deletion', () => {
  test('redirects the old product URL to the editor', async ({ page }) => {
    await page.route(`**/pim/web/products/${id}{,?*}`, (route) =>
      route.fulfill({ json: { product } }),
    );
    await page.goto(`/products/${id}?source=bookmark#product-sku`);
    await expect(page).toHaveURL(`/products/${id}/edit?source=bookmark#product-sku`);
    await expect(page.getByRole('textbox', { name: 'SKU', exact: true })).toHaveValue(product.sku);
  });

  test('opens the editor through the SKU link and survives reloading', async ({ page }) => {
    await page.route('**/pim/web/products/', (route) =>
      route.fulfill({ json: { products: [product] } }),
    );
    await page.route(`**/pim/web/products/${product.id}{,?*}`, (route) =>
      route.fulfill({ json: { product } }),
    );
    await page.goto('/products');
    await page.getByRole('table').getByRole('link', { name: product.sku }).click();
    await expect(page).toHaveURL(`/products/${product.id}/edit`);
    await expect(page.getByRole('textbox', { name: 'SKU', exact: true })).toHaveValue(product.sku);
    await page.reload();
    await expect(page.getByRole('textbox', { name: 'SKU', exact: true })).toHaveValue(product.sku);
    await expect(page).toHaveTitle('Edit product · Pimelo');
  });

  test('shows not found for a missing product', async ({ page }) => {
    await page.route(`**/pim/web/products/${product.id}{,?*}`, (route) =>
      route.fulfill({
        status: 404,
        json: { error: 'Product not found.' },
      }),
    );
    await page.goto(`/products/${product.id}/edit`);
    await expect(page.getByRole('alert')).toContainText('Product not found');
    await expect(page.getByRole('link', { name: 'Back to products' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Delete product' })).toHaveCount(0);
  });

  test('deletes from the editor and returns to the refreshed list', async ({ page }) => {
    await page.route(`**/pim/web/products/${product.id}{,?*}`, (route) =>
      route.request().method() === 'DELETE'
        ? route.fulfill({ status: 204 })
        : route.fulfill({ json: { product } }),
    );
    await page.route('**/pim/web/products/', (route) => route.fulfill({ json: { products: [] } }));
    await page.goto(`/products/${product.id}/edit`);
    await page.getByRole('button', { name: 'Delete product' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete product' }).click();
    await expect(page).toHaveURL('/products');
    await expect(page.getByText('No products yet')).toBeVisible();
  });
});

test.describe('Editing deleted products', () => {
  const archived = { ...product, deleted_at: '2026-09-25T10:00:00+00:00' };

  test('shows saved values in disabled fields and restores in place', async ({ page }) => {
    await page.route(`**/pim/web/products/${product.id}?include_deleted=1`, (route) =>
      route.fulfill({ json: { product: archived } }),
    );
    await page.route(`**/pim/web/products/${product.id}/restore`, (route) =>
      route.fulfill({ json: { product } }),
    );
    await page.goto(`/products/${product.id}/edit`);
    await expect(page.getByText('Deleted', { exact: true })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'SKU', exact: true })).toHaveValue(product.sku);
    await expect(page.getByRole('textbox', { name: 'SKU', exact: true })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await expect(page.getByRole('link', { name: 'Back to products' })).toHaveAttribute(
      'href',
      '/products?status=deleted',
    );
    await page.getByRole('button', { name: 'Restore product' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Restore product?' });
    await expect(dialog).toContainText(product.sku);
    await dialog.getByRole('button', { name: 'Restore product' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('textbox', { name: 'SKU', exact: true })).toBeEnabled();
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await expect(page.getByText('Deleted', { exact: true })).toHaveCount(0);
    await expect(page).toHaveURL(`/products/${product.id}/edit`);
  });

  test('returns to the deleted list after permanent deletion', async ({ page }) => {
    await page.route(`**/pim/web/products/${product.id}?include_deleted=1`, (route) =>
      route.fulfill({ json: { product: archived } }),
    );
    await page.route(`**/pim/web/products/${product.id}/permanent`, (route) =>
      route.fulfill({ status: 204 }),
    );
    await page.route('**/pim/web/products/?status=deleted', (route) =>
      route.fulfill({ json: { products: [] } }),
    );
    await page.goto(`/products/${product.id}/edit`);
    await page.getByRole('button', { name: 'Delete permanently' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete permanently' }).click();
    await expect(page).toHaveURL('/products?status=deleted');
    await expect(page.getByText('No deleted products', { exact: true })).toBeVisible();
  });
});
