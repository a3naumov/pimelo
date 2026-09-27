import { expect, test } from '@playwright/test';

test.describe('SidebarProvider fixed desktop state', () => {
  test.use({ viewport: { width: 1440, height: 960 } });

  test('ignores a previously saved collapsed state', async ({ page, context, baseURL }) => {
    await context.addCookies([{ name: 'sidebar_state', value: 'false', url: baseURL! }]);
    await page.goto('/');
    const sidebar = page.locator('[data-slot="sidebar"][data-state]');
    await expect(sidebar).toHaveAttribute('data-state', 'expanded');
    await expect(page.locator('.workspace-name')).toBeVisible();
    await page.reload();
    await expect(sidebar).toHaveAttribute('data-state', 'expanded');
  });

  test('does not collapse with desktop keyboard shortcuts', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('Control+b');
    await page.keyboard.press('Meta+b');
    await expect(page.locator('[data-slot="sidebar"][data-state]')).toHaveAttribute(
      'data-state',
      'expanded',
    );
    await expect(page.locator('.workspace-name')).toBeVisible();
  });

  test('shows the toggle only on mobile and restores the full desktop sidebar', async ({
    page,
  }) => {
    await page.goto('/');
    const trigger = page.locator('[data-sidebar="trigger"]');
    await expect(trigger).toHaveCount(0);
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(trigger).toBeVisible();
    await trigger.click();
    await expect(page.getByRole('dialog', { name: 'Sidebar', exact: true })).toBeVisible();
    await page.setViewportSize({ width: 1440, height: 960 });
    await expect(trigger).toHaveCount(0);
    await expect(page.getByRole('dialog', { name: 'Sidebar', exact: true })).toHaveCount(0);
    await expect(page.locator('[data-slot="sidebar"][data-state]')).toHaveAttribute(
      'data-state',
      'expanded',
    );
    await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible();
  });
});
