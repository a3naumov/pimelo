import { expect, test } from '../../../../shared/test/browser';

const attribute = {
  id: '0195f582-9762-7c2a-9228-4060489e06d8',
  name: 'Name-01',
  created_at: '2026-09-01T10:00:00+00:00',
  updated_at: '2026-09-30T10:00:00+00:00',
  deleted_at: null,
};

test.describe('Attribute list states', () => {
  test('searches names and IDs and recovers from no matching attributes', async ({ page }) => {
    const other = { ...attribute, id: '0195f582-9762-7c2a-9228-4060489e06d9', name: 'SECOND' };
    await page.route('**/pim/web/attributes/', (route) =>
      route.fulfill({ json: { attributes: [attribute, other] } }),
    );
    await page.goto('/attributes');
    const search = page.getByRole('searchbox', { name: 'Search by Name or ID' });
    const table = page.getByRole('table', { name: 'Attributes' });
    await expect(search).toBeEnabled();
    await search.fill(' name-01 ');
    await expect(table.getByRole('link', { name: attribute.name })).toBeVisible();
    await expect(table.getByRole('link', { name: other.name })).toHaveCount(0);
    await search.fill(other.id);
    await expect(table.getByRole('link', { name: other.name })).toBeVisible();
    await search.fill('missing');
    await expect(table.getByText('No matching attributes')).toBeVisible();
    await expect(page.getByRole('status')).toHaveText('0 of 2 attributes');
    await search.clear();
    await expect(table.getByRole('link')).toHaveCount(2);
  });

  test('shows loading until the response arrives, then renders attributes', async ({ page }) => {
    let release!: () => void;
    const gate = new Promise<void>((resolve) => {
      release = resolve;
    });
    await page.route('**/pim/web/attributes/', async (route) => {
      await gate;
      await route.fulfill({ json: { attributes: [attribute] } });
    });
    await page.goto('/attributes');
    await expect(page.getByRole('status', { name: 'Loading attributes' })).toBeVisible();
    release();
    const table = page.getByRole('table', { name: 'Attributes' });
    await expect(table.getByRole('link', { name: attribute.name })).toBeVisible();
    await expect(table.getByText(attribute.id)).toBeVisible();
  });

  test('offers creation when the attribute list is empty', async ({ page }) => {
    await page.route('**/pim/web/attributes/', (route) =>
      route.fulfill({ json: { attributes: [] } }),
    );
    await page.goto('/attributes');
    await expect(page.getByText('No attributes yet')).toBeVisible();
    await page.getByRole('link', { name: 'Create attribute' }).click();
    await expect(page).toHaveURL('/attributes/new');
  });

  test('retries a failed list request', async ({ page }) => {
    let failed = true;
    await page.route('**/pim/web/attributes/', (route) =>
      route.fulfill(
        failed
          ? { status: 503, json: { error: 'Internal failure' } }
          : { json: { attributes: [attribute] } },
      ),
    );
    await page.goto('/attributes');
    await expect(page.getByRole('alert')).toContainText('Could not load attributes');
    failed = false;
    await page.getByRole('button', { name: 'Try again' }).click();
    await expect(page.getByRole('table').getByText(attribute.name)).toBeVisible();
    await expect(page.getByRole('alert')).toHaveCount(0);
  });

  test('reports malformed successful responses', async ({ page }) => {
    await page.route('**/pim/web/attributes/', (route) =>
      route.fulfill({ json: { attributes: [{}] } }),
    );
    await page.goto('/attributes');
    await expect(page.getByRole('alert')).toContainText('invalid response');
    await expect(page.getByRole('table')).toHaveCount(0);
  });
});

test.describe('Deleting attributes from the list', () => {
  test('requires confirmation and preserves the dialog after failure', async ({ page }) => {
    let attributes = [attribute];
    let failures = 1;
    await page.route('**/pim/web/attributes/', (route) => route.fulfill({ json: { attributes } }));
    await page.route(`**/pim/web/attributes/${attribute.id}{,?*}`, async (route) => {
      if (route.request().method() !== 'DELETE') {
        return route.abort();
      }

      if (failures-- > 0) {
        return route.fulfill({ status: 500, json: { error: 'Failure' } });
      }

      attributes = [];
      await route.fulfill({ status: 204 });
    });
    await page.goto('/attributes');
    await page.getByRole('button', { name: `Actions for ${attribute.name}` }).click();
    await page.getByRole('menuitem', { name: 'Delete', exact: true }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Delete attribute?' });
    await expect(dialog).toContainText('You can restore it later');
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('table').getByText(attribute.name)).toBeVisible();
    await page.getByRole('button', { name: `Actions for ${attribute.name}` }).click();
    await page.getByRole('menuitem', { name: 'Delete', exact: true }).click();
    await dialog.getByRole('button', { name: 'Delete attribute', exact: true }).click();
    await expect(dialog.getByRole('alert')).toContainText('Could not delete attribute');
    await dialog.getByRole('button', { name: 'Delete attribute', exact: true }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('No attributes yet')).toBeVisible();
  });
});

test.describe('Deleted attribute list', () => {
  const archived = {
    id: '0195f582-9762-7c2a-9228-4060489e06d9',
    name: 'ARCHIVED',
    created_at: '2026-09-01T10:00:00+00:00',
    updated_at: '2026-09-30T10:00:00+00:00',
    deleted_at: '2026-09-25T10:00:00+00:00',
  };

  test('persists the filter across reload and browser Back', async ({ page }) => {
    await page.route('**/pim/web/attributes/**', (route) =>
      route.fulfill({
        json: {
          attributes:
            new URL(route.request().url()).searchParams.get('status') === 'deleted'
              ? [archived]
              : [attribute],
        },
      }),
    );
    await page.goto('/attributes');
    await expect(page.getByRole('table').getByText(attribute.name)).toBeVisible();
    await page.getByRole('button', { name: 'Deleted', exact: true }).click();
    await expect(page).toHaveURL('/attributes?status=deleted');
    await expect(page.getByRole('table').getByText(archived.name)).toBeVisible();
    await expect(page.getByRole('table').getByText(attribute.name)).toHaveCount(0);
    await page.reload();
    await expect(page.getByRole('button', { name: 'Deleted', exact: true })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    await page.getByRole('button', { name: 'Active', exact: true }).click();
    await expect(page.getByRole('table').getByText(attribute.name)).toBeVisible();
    await page.goBack();
    await expect(page).toHaveURL('/attributes?status=deleted');
    await expect(page.getByRole('table').getByText(archived.name)).toBeVisible();
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
    await page.route('**/pim/web/attributes/**', (route) =>
      route.fulfill({
        json: {
          attributes:
            new URL(route.request().url()).searchParams.get('status') === 'deleted'
              ? restored
                ? []
                : [archived]
              : restored
                ? [
                    {
                      ...archived,
                      created_at: '2026-09-01T10:00:00+00:00',
                      updated_at: '2026-09-30T10:00:00+00:00',
                      deleted_at: null,
                    },
                  ]
                : [],
        },
      }),
    );
    await page.route(`**/pim/web/attributes/${archived.id}/restore`, async (route) => {
      if (failures-- > 0) {
        return route.fulfill({ status: 503, json: { error: 'Failure' } });
      }

      await gate;
      restored = true;

      return route.fulfill({
        json: {
          attribute: {
            ...archived,
            created_at: '2026-09-01T10:00:00+00:00',
            updated_at: '2026-09-30T10:00:00+00:00',
            deleted_at: null,
          },
        },
      });
    });
    await page.goto('/attributes?status=deleted');
    await page.getByRole('button', { name: 'Actions for ARCHIVED' }).click();
    await expect(page.getByRole('menuitem', { name: 'Edit', exact: true })).toHaveCount(0);
    await expect(page.getByRole('menuitem', { name: 'Delete', exact: true })).toHaveCount(0);
    await page.getByRole('menuitem', { name: 'Restore', exact: true }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Restore attribute?' });
    await expect(dialog).toContainText(archived.name);
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeHidden();
    expect(failures).toBe(1);
    await page.getByRole('button', { name: 'Actions for ARCHIVED' }).click();
    await page.getByRole('menuitem', { name: 'Restore', exact: true }).click();
    await dialog.getByRole('button', { name: 'Restore attribute' }).click();
    await expect(dialog.getByRole('alert')).toContainText('Could not restore attribute');
    await dialog.getByRole('button', { name: 'Restore attribute' }).click();
    await expect(dialog.getByRole('button', { name: 'Restoring…' })).toBeDisabled();
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    await dialog.press('Escape');
    await expect(dialog).toBeVisible();
    release();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('No deleted attributes', { exact: true })).toBeVisible();
    await expect(page).toHaveURL('/attributes?status=deleted');
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
    await page.route('**/pim/web/attributes/**', (route) =>
      route.fulfill({ json: { attributes: removed ? [] : [archived] } }),
    );
    await page.route(`**/pim/web/attributes/${archived.id}/permanent`, async (route) => {
      attempts++;

      if (attempts === 1) {
        return route.fulfill({ status: 500, json: { error: 'Failure' } });
      }

      await gate;
      removed = true;
      await route.fulfill({ status: 204 });
    });
    await page.goto('/attributes?status=deleted');
    await page.getByRole('button', { name: 'Actions for ARCHIVED' }).click();
    await page.getByRole('menuitem', { name: 'Delete permanently' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Permanently delete attribute?' });
    await expect(dialog).toContainText('ARCHIVED');
    await expect(dialog).toContainText('cannot be undone');
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    expect(attempts).toBe(0);
    await page.getByRole('button', { name: 'Actions for ARCHIVED' }).click();
    await page.getByRole('menuitem', { name: 'Delete permanently' }).click();
    await dialog.getByRole('button', { name: 'Delete permanently' }).click();
    await expect(dialog.getByRole('alert')).toContainText('Could not delete attribute');
    await dialog.getByRole('button', { name: 'Delete permanently' }).click();
    await expect(dialog.getByRole('button', { name: 'Deleting…' })).toBeDisabled();
    await expect(dialog.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    release();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('No deleted attributes', { exact: true })).toBeVisible();
    expect(attempts).toBe(2);
  });
});
