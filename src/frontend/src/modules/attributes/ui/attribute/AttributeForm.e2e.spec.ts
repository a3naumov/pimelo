import { expect, test } from '@playwright/test';

test.describe('Attribute form changes', () => {
  const id = '0195f582-9762-7c2a-9228-4060489e06d8';

  test('requires a changed value and blocks unchanged submissions, including Enter', async ({
    page,
  }) => {
    const writes: string[] = [];
    await page.route(`**/pim/web/attributes/${id}{,?*}`, (route) => {
      if (route.request().method() === 'PATCH') {
        writes.push(route.request().method());
      }

      return route.fulfill({
        json: {
          attribute: {
            id,
            name: 'ORIGINAL',
            created_at: '2026-09-01T10:00:00+00:00',
            updated_at: '2026-09-30T10:00:00+00:00',
            deleted_at: null,
          },
        },
      });
    });
    await page.goto(`/attributes/${id}/edit`);
    const input = page.getByRole('textbox', { name: 'Name', exact: true });
    const save = page.getByRole('button', { name: 'Save changes' });
    await expect(input).toHaveValue('ORIGINAL');
    await expect(save).toBeDisabled();
    await input.press('Enter');
    await input.fill('CHANGED');
    await expect(save).toBeEnabled();
    await input.fill('ORIGINAL');
    await expect(save).toBeDisabled();
    await input.fill('  ORIGINAL  ');
    await expect(save).toBeDisabled();
    await page.locator('form').dispatchEvent('submit');
    expect(writes).toEqual([]);
  });

  test('blocks repeat saves and compares against the last saved value', async ({ page }) => {
    let attribute = {
      id,
      name: 'ORIGINAL',
      created_at: '2026-09-01T10:00:00+00:00',
      updated_at: '2026-09-30T10:00:00+00:00',
      deleted_at: null,
    };
    let writes = 0;
    let release!: () => void;
    const gate = new Promise<void>((resolve) => {
      release = resolve;
    });
    await page.route(`**/pim/web/attributes/${id}{,?*}`, async (route) => {
      if (route.request().method() === 'PATCH') {
        writes++;
        await gate;
        attribute = { ...attribute, name: route.request().postDataJSON().name as string };
      }

      return route.fulfill({ json: { attribute } });
    });
    await page.goto(`/attributes/${id}/edit`);
    const input = page.getByRole('textbox', { name: 'Name', exact: true });
    await expect(input).toHaveValue('ORIGINAL');
    await input.fill('  UPDATED  ');
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    await expect(input).toBeDisabled();
    await page.locator('form').dispatchEvent('submit');
    release();
    await expect(input).toHaveValue('UPDATED');
    const save = page.getByRole('button', { name: 'Save changes' });
    await expect(save).toBeDisabled();
    expect(writes).toBe(1);
    await input.fill('ORIGINAL');
    await expect(save).toBeEnabled();
    await input.fill('UPDATED');
    await expect(save).toBeDisabled();
  });
});

test.describe('Attribute form validation', () => {
  test('rejects blank and oversized names before submitting', async ({ page }) => {
    const requests: string[] = [];
    await page.route('**/pim/web/attributes/', (route) => {
      requests.push(route.request().method());

      return route.abort();
    });
    await page.goto('/attributes/new');
    await page.getByRole('button', { name: 'Create attribute' }).click();
    await expect(page.getByText('Name must be a non-empty string.')).toBeVisible();
    await page.getByRole('textbox', { name: 'Name', exact: true }).fill('a'.repeat(256));
    await page.getByRole('button', { name: 'Create attribute' }).click();
    await expect(page.getByText('Name must not exceed 255 characters.')).toBeVisible();
    expect(requests).toEqual([]);
  });

  const failures = [
    { status: 422, message: 'This Name is invalid.' },
    { status: 500, message: 'The server could not complete the request. Please try again.' },
    { status: 0, message: 'Could not reach the server. Check your connection and try again.' },
  ];

  for (const { status, message } of failures) {
    test(`preserves input after a ${status || 'network'} failure`, async ({ page }) => {
      await page.route('**/pim/web/attributes/', (route) =>
        status === 0
          ? route.abort()
          : route.fulfill({
              status,
              json:
                status === 422
                  ? { violations: [{ propertyPath: 'name', title: 'This Name is invalid.' }] }
                  : { error: 'A attribute with this Name already exists.' },
            }),
      );
      await page.goto('/attributes/new');
      const input = page.getByRole('textbox', { name: 'Name', exact: true });
      await input.fill('MY-Name');
      await page.getByRole('button', { name: 'Create attribute' }).click();
      await expect(page.getByText(message, { exact: true })).toBeVisible();
      await expect(input).toHaveValue('MY-Name');
      await expect(page.getByRole('button', { name: 'Create attribute' })).toBeEnabled();
    });
  }
});
