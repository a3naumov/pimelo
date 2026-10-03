import { expect, test } from '@playwright/test';

test.describe('Creating an attribute', () => {
  test('normalizes the name, blocks duplicate submission, and refreshes the list', async ({
    page,
  }) => {
    const id = '0195f582-9762-7c2a-9228-4060489e06d8';
    const attributes: { id: string; name: string }[] = [];
    let release!: () => void;
    const gate = new Promise<void>((resolve) => {
      release = resolve;
    });
    const writes: unknown[] = [];
    await page.route('**/pim/web/attributes/', async (route) => {
      if (route.request().method() === 'GET') {
        return route.fulfill({ json: { attributes } });
      }

      writes.push(route.request().postDataJSON());
      await gate;
      const attribute = {
        id,
        name: route.request().postDataJSON().name as string,
        created_at: '2026-09-01T10:00:00+00:00',
        updated_at: '2026-09-30T10:00:00+00:00',
        deleted_at: null,
      };
      attributes.push(attribute);
      await route.fulfill({ status: 201, json: { attribute } });
    });
    await page.goto('/attributes');
    await expect(page.getByText('No attributes yet')).toBeVisible();
    await page.getByRole('link', { name: 'Create attribute' }).click();
    await page.getByRole('textbox', { name: 'Name', exact: true }).fill('  Name-NEW  ');
    await page.getByRole('button', { name: 'Create attribute' }).click();
    await expect(page.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    await expect(page.getByRole('textbox', { name: 'Name', exact: true })).toBeDisabled();
    release();
    await expect(page).toHaveURL(`/attributes/${id}/edit`);
    await expect(page.getByRole('textbox', { name: 'Name', exact: true })).toHaveValue('Name-NEW');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    expect(writes).toEqual([{ name: 'Name-NEW' }]);
    await page.getByRole('link', { name: 'Back to attributes' }).click();
    await expect(page.getByRole('table').getByRole('link', { name: 'Name-NEW' })).toBeVisible();
  });
});
