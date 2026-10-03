import { expect, test } from '../test/browser';

test.use({ locale: 'ru-RU' });

test('keeps English navigation and validation regardless of browser language', async ({ page }) => {
  await page.goto('/products/new');
  await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  await expect(page).toHaveTitle('Create product · Pimelo');
  await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Create product' })).toBeVisible();
  await page.getByRole('button', { name: 'Create product' }).click();
  await expect(page.getByRole('alert')).toHaveText('SKU must be a non-empty string.');
  await page.getByRole('textbox', { name: 'SKU', exact: true }).fill('x'.repeat(256));
  await page.getByRole('button', { name: 'Create product' }).click();
  await expect(page.getByRole('alert')).toHaveText('SKU must not exceed 255 characters.');
});
