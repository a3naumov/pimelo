import { expect, test } from '../../shared/test/browser';

test.describe('AppSidebar desktop navigation', () => {
  test.use({ viewport: { width: 1440, height: 960 } });

  test('shows the complete sidebar without collapse controls', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('[data-sidebar="trigger"]')).toHaveCount(0);
    await expect(page.locator('[data-sidebar="rail"]')).toHaveCount(0);
    const logo = page.locator('.brand-logo');
    await expect(logo).toBeVisible();
    await expect
      .poll(() =>
        logo.evaluate(
          (element) => element instanceof SVGGraphicsElement && element.getBBox().width > 0,
        ),
      )
      .toBe(true);
    await expect(page.getByText('Pimelo workspace', { exact: true })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible();
  });
});

test.describe('AppSidebar mobile navigation', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('opens the navigation panel', async ({ page }) => {
    const trigger = page.locator('[data-sidebar="trigger"]');
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');

    await trigger.click();

    await expect(page.getByRole('dialog', { name: 'Sidebar', exact: true })).toBeVisible();
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  });

  test('closes the panel after following a navigation link', async ({ page }) => {
    const trigger = page.locator('[data-sidebar="trigger"]');
    await trigger.click();
    const panel = page.getByRole('dialog', { name: 'Sidebar', exact: true });

    await panel.getByRole('link', { name: 'Categories', exact: true }).click();

    await expect(page).toHaveURL(/\/categories$/);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Categories');
    await expect(panel).toBeHidden();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  });

  test('dismisses the panel with the close button', async ({ page }) => {
    await page.locator('[data-sidebar="trigger"]').click();
    const panel = page.getByRole('dialog', { name: 'Sidebar', exact: true });

    await panel.getByRole('button', { name: 'Close navigation' }).click();

    await expect(panel).toBeHidden();
    await expect(page.locator('[data-sidebar="trigger"]')).toHaveAttribute(
      'aria-expanded',
      'false',
    );
  });

  test('dismisses the panel with Escape', async ({ page }) => {
    await page.locator('[data-sidebar="trigger"]').click();
    const panel = page.getByRole('dialog', { name: 'Sidebar', exact: true });
    await expect(panel).toBeVisible();

    await panel.press('Escape');

    await expect(panel).toBeHidden();
    await expect(page.locator('[data-sidebar="trigger"]')).toHaveAttribute(
      'aria-expanded',
      'false',
    );
  });
});
