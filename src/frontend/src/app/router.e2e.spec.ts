import { expect, test } from '@playwright/test';

test.describe('Router browser integration', () => {
  test('updates the document title after navigation', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle('Overview · Pimelo');

    await page
      .getByRole('navigation', { name: 'Main navigation' })
      .getByRole('link', { name: 'Products', exact: true })
      .click();

    await expect(page).toHaveURL(/\/products$/);
    await expect(page).toHaveTitle('Products · Pimelo');
  });

  test('restores a nested route after a reload', async ({ page }) => {
    await page.goto('/products');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Products');

    await page.reload();

    await expect(page).toHaveURL(/\/products$/);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Products');
  });

  test('returns to the previous page with browser Back', async ({ page }) => {
    await page.goto('/');
    await page
      .getByRole('navigation', { name: 'Main navigation' })
      .getByRole('link', { name: 'Products', exact: true })
      .click();
    await expect(page).toHaveURL(/\/products$/);

    await page.goBack();

    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Overview');
  });
});
