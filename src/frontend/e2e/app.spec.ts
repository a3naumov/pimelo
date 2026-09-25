import { test, expect } from '@playwright/test';

test('shows the greeting on the home page', async ({ page }) => {
  await page.goto('/');

  await expect(page).toHaveTitle('Pimelo');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Hello world');
});
