import { expect, test } from '@playwright/test';

const product = { id: '0195f582-9762-7c2a-9228-4060489e06d8', sku: 'SKU-01', deleted_at: null };

test.describe('Product list states', () => {
  test('searches SKUs and IDs and recovers from no matching products', async ({ page }) => {
    const other = { ...product, id: '0195f582-9762-7c2a-9228-4060489e06d9', sku: 'SECOND' };
    await page.route('**/web/products/', (route) =>
      route.fulfill({ json: { products: [product, other] } }),
    );
    await page.goto('/products');
    const search = page.getByRole('searchbox', { name: 'Search by SKU or ID' });
    const table = page.getByRole('table', { name: 'Products' });
    await expect(search).toBeEnabled();
    await search.fill(' sku-01 ');
    await expect(table.getByRole('link', { name: product.sku })).toBeVisible();
    await expect(table.getByRole('link', { name: other.sku })).toHaveCount(0);
    await search.fill(other.id);
    await expect(table.getByRole('link', { name: other.sku })).toBeVisible();
    await search.fill('missing');
    await expect(table.getByText('No matching products')).toBeVisible();
    await expect(page.getByRole('status')).toHaveText('0 of 2 products');
    await search.clear();
    await expect(table.getByRole('link')).toHaveCount(2);
  });

  test('shows loading until the response arrives, then renders products', async ({ page }) => {
    let release!: () => void;
    const gate = new Promise<void>((resolve) => {
      release = resolve;
    });
    await page.route('**/web/products/', async (route) => {
      await gate;
      await route.fulfill({ json: { products: [product] } });
    });
    await page.goto('/products');
    await expect(page.getByRole('status', { name: 'Loading products' })).toBeVisible();
    release();
    const table = page.getByRole('table', { name: 'Products' });
    await expect(table.getByRole('link', { name: product.sku })).toBeVisible();
    await expect(table.getByText(product.id)).toBeVisible();
  });

  test('offers creation when the catalog is empty', async ({ page }) => {
    await page.route('**/web/products/', (route) => route.fulfill({ json: { products: [] } }));
    await page.goto('/products');
    await expect(page.getByText('No products yet')).toBeVisible();
    await page.getByRole('link', { name: 'Create product' }).click();
    await expect(page).toHaveURL('/products/new');
  });

  test('retries a failed list request', async ({ page }) => {
    let failed = true;
    await page.route('**/web/products/', (route) =>
      route.fulfill(
        failed
          ? { status: 503, json: { error: 'Internal failure' } }
          : { json: { products: [product] } },
      ),
    );
    await page.goto('/products');
    await expect(page.getByRole('alert')).toContainText('Could not load products');
    failed = false;
    await page.getByRole('button', { name: 'Try again' }).click();
    await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
    await expect(page.getByRole('alert')).toHaveCount(0);
  });

  test('reports malformed successful responses', async ({ page }) => {
    await page.route('**/web/products/', (route) => route.fulfill({ json: { products: [{}] } }));
    await page.goto('/products');
    await expect(page.getByRole('alert')).toContainText('invalid response');
    await expect(page.getByRole('table')).toHaveCount(0);
  });
});

test.describe('Deleting products from the list', () => {
  test('requires confirmation and preserves the dialog after failure', async ({ page }) => {
    let products = [product];
    let failures = 1;
    await page.route('**/web/products/', (route) => route.fulfill({ json: { products } }));
    await page.route(`**/web/products/${product.id}{,?*}`, async (route) => {
      if (route.request().method() !== 'DELETE') {
        return route.abort();
      }

      if (failures-- > 0) {
        return route.fulfill({ status: 500, json: { error: 'Failure' } });
      }

      products = [];
      await route.fulfill({ status: 204 });
    });
    await page.goto('/products');
    await page.getByRole('button', { name: `Actions for ${product.sku}` }).click();
    await page.getByRole('menuitem', { name: 'Delete', exact: true }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Delete product?' });
    await expect(dialog).toContainText('SKU will remain reserved');
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
    await page.getByRole('button', { name: `Actions for ${product.sku}` }).click();
    await page.getByRole('menuitem', { name: 'Delete', exact: true }).click();
    await dialog.getByRole('button', { name: 'Delete product', exact: true }).click();
    await expect(dialog.getByRole('alert')).toContainText('Could not delete product');
    await dialog.getByRole('button', { name: 'Delete product', exact: true }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('No products yet')).toBeVisible();
  });
});

test.describe('Deleted product list', () => {
  const archived = {
    id: '0195f582-9762-7c2a-9228-4060489e06d9',
    sku: 'ARCHIVED',
    deleted_at: '2026-09-25T10:00:00+00:00',
  };

  test('persists the filter across reload and browser Back', async ({ page }) => {
    await page.route('**/web/products/**', (route) =>
      route.fulfill({
        json: {
          products:
            new URL(route.request().url()).searchParams.get('status') === 'deleted'
              ? [archived]
              : [product],
        },
      }),
    );
    await page.goto('/products');
    await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
    await page.getByRole('button', { name: 'Deleted', exact: true }).click();
    await expect(page).toHaveURL('/products?status=deleted');
    await expect(page.getByRole('table').getByText(archived.sku)).toBeVisible();
    await expect(page.getByRole('table').getByText(product.sku)).toHaveCount(0);
    await page.reload();
    await expect(page.getByRole('button', { name: 'Deleted', exact: true })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    await page.getByRole('button', { name: 'Active', exact: true }).click();
    await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
    await page.goBack();
    await expect(page).toHaveURL('/products?status=deleted');
    await expect(page.getByRole('table').getByText(archived.sku)).toBeVisible();
  });

  test('confirms restoration, supports cancellation and retry, and updates both filters', async ({
    page,
  }) => {
    let restored = false;
    let failures = 1;
    let release!: () => void;
    const gate = new Promise<void>((resolve) => {
      release = resolve;
    });
    await page.route('**/web/products/**', (route) =>
      route.fulfill({
        json: {
          products:
            new URL(route.request().url()).searchParams.get('status') === 'deleted'
              ? restored
                ? []
                : [archived]
              : restored
                ? [{ ...archived, deleted_at: null }]
                : [],
        },
      }),
    );
    await page.route(`**/web/products/${archived.id}/restore`, async (route) => {
      if (failures-- > 0) {
        return route.fulfill({ status: 503, json: { error: 'Failure' } });
      }

      await gate;
      restored = true;

      return route.fulfill({ json: { product: { ...archived, deleted_at: null } } });
    });
    await page.goto('/products?status=deleted');
    await page.getByRole('button', { name: 'Actions for ARCHIVED' }).click();
    await expect(page.getByRole('menuitem', { name: 'Edit', exact: true })).toHaveCount(0);
    await expect(page.getByRole('menuitem', { name: 'Delete', exact: true })).toHaveCount(0);
    await page.getByRole('menuitem', { name: 'Restore', exact: true }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Restore product?' });
    await expect(dialog).toContainText(archived.sku);
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();
    expect(failures).toBe(1);
    await page.getByRole('button', { name: 'Actions for ARCHIVED' }).click();
    await page.getByRole('menuitem', { name: 'Restore', exact: true }).click();
    await dialog.getByRole('button', { name: 'Restore product' }).click();
    await expect(dialog.getByRole('alert')).toContainText('Could not restore product');
    await dialog.getByRole('button', { name: 'Restore product' }).click();
    await expect(dialog.getByRole('button', { name: 'Restoring…' })).toBeDisabled();
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    await dialog.press('Escape');
    await expect(dialog).toBeVisible();
    release();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('No deleted products', { exact: true })).toBeVisible();
    await expect(page).toHaveURL('/products?status=deleted');
    await page.getByRole('button', { name: 'Active', exact: true }).click();
    await expect(page.getByRole('table').getByRole('link', { name: 'ARCHIVED' })).toBeVisible();
  });

  test('confirms permanent deletion, retains errors, and disables repeat submission', async ({
    page,
  }) => {
    let removed = false;
    let attempts = 0;
    let release!: () => void;
    const gate = new Promise<void>((resolve) => {
      release = resolve;
    });
    await page.route('**/web/products/**', (route) =>
      route.fulfill({ json: { products: removed ? [] : [archived] } }),
    );
    await page.route(`**/web/products/${archived.id}/permanent`, async (route) => {
      attempts++;

      if (attempts === 1) {
        return route.fulfill({ status: 500, json: { error: 'Failure' } });
      }

      await gate;
      removed = true;
      await route.fulfill({ status: 204 });
    });
    await page.goto('/products?status=deleted');
    await page.getByRole('button', { name: 'Actions for ARCHIVED' }).click();
    await page.getByRole('menuitem', { name: 'Delete permanently' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Permanently delete product?' });
    await expect(dialog).toContainText('ARCHIVED');
    await expect(dialog).toContainText('cannot be undone');
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    expect(attempts).toBe(0);
    await page.getByRole('button', { name: 'Actions for ARCHIVED' }).click();
    await page.getByRole('menuitem', { name: 'Delete permanently' }).click();
    await dialog.getByRole('button', { name: 'Delete permanently' }).click();
    await expect(dialog.getByRole('alert')).toContainText('Could not delete product');
    await dialog.getByRole('button', { name: 'Delete permanently' }).click();
    await expect(dialog.getByRole('button', { name: 'Deleting…' })).toBeDisabled();
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    release();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('No deleted products', { exact: true })).toBeVisible();
    expect(attempts).toBe(2);
  });
});
