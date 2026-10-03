import { expect, test } from '@playwright/test';

const id = '0195f582-9762-7c2a-9228-4060489e06d8';
const attribute = {
  id,
  name: 'Name-01',
  created_at: '2026-09-01T10:00:00+00:00',
  updated_at: '2026-09-30T10:00:00+00:00',
  deleted_at: null,
};

test.describe('Editing an attribute', () => {
  test('loads the name, saves in place, and refreshes the list', async ({ page }) => {
    let attribute = {
      id,
      name: 'ORIGINAL',
      created_at: '2026-09-01T10:00:00+00:00',
      updated_at: '2026-09-30T10:00:00+00:00',
      deleted_at: null,
    };
    await page.route('**/pim/web/attributes/', (route) =>
      route.fulfill({ json: { attributes: [attribute] } }),
    );
    await page.route(`**/pim/web/attributes/${id}{,?*}`, async (route) => {
      if (route.request().method() === 'PATCH') {
        attribute = {
          id,
          name: route.request().postDataJSON().name as string,
          created_at: '2026-09-01T10:00:00+00:00',
          updated_at: '2026-09-30T10:00:00+00:00',
          deleted_at: null,
        };
      }

      await route.fulfill({ json: { attribute } });
    });
    await page.goto('/attributes');
    await page.getByRole('button', { name: 'Actions for ORIGINAL' }).click();
    await expect(page.getByRole('menuitem', { name: 'View', exact: true })).toHaveCount(0);
    await page.getByRole('menuitem', { name: 'Edit', exact: true }).click();
    const input = page.getByRole('textbox', { name: 'Name', exact: true });
    await expect(input).toHaveValue('ORIGINAL');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await expect(
      page
        .getByRole('navigation', { name: 'Main navigation' })
        .getByRole('link', { name: 'Attributes', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
    await expect(
      page
        .getByRole('navigation', { name: 'breadcrumb' })
        .getByRole('link', { name: 'Attributes' }),
    ).toBeVisible();
    await input.fill('UPDATED');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page).toHaveURL(`/attributes/${id}/edit`);
    await expect(input).toHaveValue('UPDATED');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await page.getByRole('link', { name: 'Back to attributes' }).click();
    await expect(page.getByRole('table').getByRole('link', { name: 'UPDATED' })).toBeVisible();
    await expect(page.getByRole('table').getByText('ORIGINAL')).toHaveCount(0);
  });

  test('preserves the draft when saving fails', async ({ page }) => {
    await page.route(`**/pim/web/attributes/${id}{,?*}`, (route) =>
      route.request().method() === 'PATCH'
        ? route.fulfill({ status: 500, json: { error: 'Failure' } })
        : route.fulfill({
            json: {
              attribute: {
                id,
                name: 'ORIGINAL',
                created_at: '2026-09-01T10:00:00+00:00',
                updated_at: '2026-09-30T10:00:00+00:00',
                deleted_at: null,
              },
            },
          }),
    );
    await page.goto(`/attributes/${id}/edit`);
    const input = page.getByRole('textbox', { name: 'Name', exact: true });
    await expect(input).toHaveValue('ORIGINAL');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await input.fill('RESERVED');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(
      page.getByText('The server could not complete the request. Please try again.'),
    ).toBeVisible();
    await expect(input).toHaveValue('RESERVED');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeEnabled();
    await expect(page).toHaveURL(`/attributes/${id}/edit`);
  });
});

test.describe('Attribute editor navigation and deletion', () => {
  test('opens the editor through the name link and survives reloading', async ({ page }) => {
    await page.route('**/pim/web/attributes/', (route) =>
      route.fulfill({ json: { attributes: [attribute] } }),
    );
    await page.route(`**/pim/web/attributes/${attribute.id}{,?*}`, (route) =>
      route.fulfill({ json: { attribute } }),
    );
    await page.goto('/attributes');
    await page.getByRole('table').getByRole('link', { name: attribute.name }).click();
    await expect(page).toHaveURL(`/attributes/${attribute.id}/edit`);
    await expect(page.getByRole('textbox', { name: 'Name', exact: true })).toHaveValue(
      attribute.name,
    );
    await page.reload();
    await expect(page.getByRole('textbox', { name: 'Name', exact: true })).toHaveValue(
      attribute.name,
    );
    await expect(page).toHaveTitle('Edit attribute · Pimelo');
    await expect(page.locator('dl')).toContainText(attribute.id);
    await expect(page.locator('dl time')).toHaveCount(2);
    await expect(page.locator('dl time').first()).toHaveAttribute('datetime', attribute.created_at);
    await expect(page.locator('dl time').last()).toHaveAttribute('datetime', attribute.updated_at);
  });

  test('shows not found for a missing attribute', async ({ page }) => {
    await page.route(`**/pim/web/attributes/${attribute.id}{,?*}`, (route) =>
      route.fulfill({
        status: 404,
        json: { error: 'Attribute not found.' },
      }),
    );
    await page.goto(`/attributes/${attribute.id}/edit`);
    await expect(page.getByRole('alert')).toContainText('Attribute not found');
    await expect(page.getByRole('link', { name: 'Back to attributes' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Delete attribute' })).toHaveCount(0);
  });

  test('deletes from the editor and returns to the refreshed list', async ({ page }) => {
    await page.route(`**/pim/web/attributes/${attribute.id}{,?*}`, (route) =>
      route.request().method() === 'DELETE'
        ? route.fulfill({ status: 204 })
        : route.fulfill({ json: { attribute } }),
    );
    await page.route('**/pim/web/attributes/', (route) =>
      route.fulfill({ json: { attributes: [] } }),
    );
    await page.goto(`/attributes/${attribute.id}/edit`);
    await page.getByRole('button', { name: 'Delete attribute' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete attribute' }).click();
    await expect(page).toHaveURL('/attributes');
    await expect(page.getByText('No attributes yet')).toBeVisible();
  });
});

test.describe('Editing deleted attributes', () => {
  const archived = {
    ...attribute,
    created_at: '2026-09-01T10:00:00+00:00',
    updated_at: '2026-09-30T10:00:00+00:00',
    deleted_at: '2026-09-25T10:00:00+00:00',
  };

  test('shows saved values in disabled fields and restores in place', async ({ page }) => {
    await page.route(`**/pim/web/attributes/${attribute.id}?include_deleted=1`, (route) =>
      route.fulfill({ json: { attribute: archived } }),
    );
    await page.route(`**/pim/web/attributes/${attribute.id}/restore`, (route) =>
      route.fulfill({ json: { attribute } }),
    );
    await page.goto(`/attributes/${attribute.id}/edit`);
    await expect(page.getByText('Deleted', { exact: true })).toBeVisible();
    await expect(page.getByRole('textbox', { name: 'Name', exact: true })).toHaveValue(
      attribute.name,
    );
    await expect(page.getByRole('textbox', { name: 'Name', exact: true })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await expect(page.getByRole('link', { name: 'Back to attributes' })).toHaveAttribute(
      'href',
      '/attributes?status=deleted',
    );
    await page.getByRole('button', { name: 'Restore attribute' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Restore attribute?' });
    await expect(dialog).toContainText(attribute.name);
    await dialog.getByRole('button', { name: 'Restore attribute' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('textbox', { name: 'Name', exact: true })).toBeEnabled();
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await expect(page.getByText('Deleted', { exact: true })).toHaveCount(0);
    await expect(page).toHaveURL(`/attributes/${attribute.id}/edit`);
  });

  test('returns to the deleted list after permanent deletion', async ({ page }) => {
    await page.route(`**/pim/web/attributes/${attribute.id}?include_deleted=1`, (route) =>
      route.fulfill({ json: { attribute: archived } }),
    );
    await page.route(`**/pim/web/attributes/${attribute.id}/permanent`, (route) =>
      route.fulfill({ status: 204 }),
    );
    await page.route('**/pim/web/attributes/?status=deleted', (route) =>
      route.fulfill({ json: { attributes: [] } }),
    );
    await page.goto(`/attributes/${attribute.id}/edit`);
    await page.getByRole('button', { name: 'Delete permanently' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete permanently' }).click();
    await expect(page).toHaveURL('/attributes?status=deleted');
    await expect(page.getByText('No deleted attributes', { exact: true })).toBeVisible();
  });
});
