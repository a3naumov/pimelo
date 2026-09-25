import { expect, test } from '@playwright/test';

test.describe('SidebarProvider desktop state', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('collapses the sidebar with the trigger', async ({ page }) => {
    const trigger = page.locator('[data-sidebar="trigger"]');
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');

    await trigger.click();

    await expect(page.locator('[data-slot="sidebar"][data-state]')).toHaveAttribute(
      'data-state',
      'collapsed',
    );
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  });

  test('preserves the collapsed state after a reload', async ({ page }) => {
    await page.locator('[data-sidebar="trigger"]').click();
    const sidebar = page.locator('[data-slot="sidebar"][data-state]');
    await expect(sidebar).toHaveAttribute('data-state', 'collapsed');

    await page.reload();

    await expect(sidebar).toHaveAttribute('data-state', 'collapsed');
    await expect(page.locator('[data-sidebar="trigger"]')).toHaveAttribute(
      'aria-expanded',
      'false',
    );
  });

  test('toggles the sidebar by activating the trigger with the keyboard', async ({ page }) => {
    const trigger = page.locator('[data-sidebar="trigger"]');
    await trigger.focus();

    await page.keyboard.press('Enter');

    await expect(page.locator('[data-slot="sidebar"][data-state]')).toHaveAttribute(
      'data-state',
      'collapsed',
    );
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');

    await page.keyboard.press('Enter');

    await expect(page.locator('[data-slot="sidebar"][data-state]')).toHaveAttribute(
      'data-state',
      'expanded',
    );
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  });
});
