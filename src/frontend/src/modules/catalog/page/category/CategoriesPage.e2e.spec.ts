import type { Page } from '@playwright/test';
import { expect, test } from '../../../../shared/test/browser';
import { categoryInputSchema } from '../../model/category/schemas';
const root = { id: '0195f582-9762-7c2a-9228-4060489e0601', parent_id: null as string | null };
const child = { id: '0195f582-9762-7c2a-9228-4060489e0602', parent_id: root.id };
const other = { id: '0195f582-9762-7c2a-9228-4060489e0603', parent_id: null as string | null };
const created = { id: '0195f582-9762-7c2a-9228-4060489e0604', parent_id: null as string | null };
const product = {
  id: '0195f582-9762-7c2a-9228-4060489e0611',
  sku: 'LINEN-SHIRT',
  deleted_at: null,
};
const second = { id: '0195f582-9762-7c2a-9228-4060489e0612', sku: 'COTTON-TEE', deleted_at: null };
const childrenPath = (id: string) => `/categories/?parent_id=${id}`;
const isChildrenPath = (path: string) => path.startsWith('/categories/?parent_id=');

type MockCategory = {
  id: string;
  parent_id: string | null;
  name?: string;
  slug?: string;
  deleted_at?: string | null;
};

async function mockCatalog(page: Page) {
  const state = {
    categories: [{ ...root }, { ...child }, { ...other }] as MockCategory[],
    products: [product, second],
    links: new Map([
      [product.id, [root.id, other.id]],
      [second.id, [child.id]],
    ]),
    relationReads: 0,
    reads: [] as string[],
    visibilityReads: [] as { path: string; includeDeleted: boolean }[],
    failChildren: false,
    failRoots: false,
    failBranch: false,
    branchDelays: new Map<string, Promise<void>>(),
    childrenDelays: new Map<string, Promise<void>>(),
    failLinks: false,
    failWrite: false,
    writes: [] as { method: string; path: string; body: string | null }[],
  };
  await page.route('**/pim/web/**', async (route) => {
    const url = new URL(route.request().url());
    const path = url.pathname.replace('/pim/web', '');
    const parentId = url.searchParams.get('parent_id');
    const readPath = path === '/categories/' && parentId ? childrenPath(parentId) : path;
    const includeDeleted = url.searchParams.get('include_deleted') === '1';
    const visible = (category: MockCategory) => includeDeleted || !category.deleted_at;
    const method = route.request().method();

    if (path === '/categories/slug-preview') {
      const name = url.searchParams.get('name') ?? '';
      const custom = url.searchParams.get('slug');
      const slug =
        (custom || name)
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-|-$/g, '') || 'category';
      const taken = (value: string) =>
        state.categories.some(
          (item) =>
            (item.slug ?? item.id) === value && item.id !== url.searchParams.get('exclude_id'),
        );
      let suggestion = slug;
      let suffix = 0;

      while (taken(suggestion)) {
        suggestion = `${slug}-${++suffix}`;
      }

      return route.fulfill({ json: { slug, available: !taken(slug), suggested_slug: suggestion } });
    }

    if (method === 'GET') {
      state.reads.push(readPath);
      state.visibilityReads.push({ path, includeDeleted });
    }

    if (method !== 'GET') {
      state.writes.push({ method, path, body: route.request().postData() });
    }

    if (method !== 'GET' && state.failWrite) {
      return route.fulfill({ status: 409, json: { error: 'Please retry this change.' } });
    }

    const resource = (category: MockCategory) => ({
      ...category,
      name: category.name ?? category.id,
      slug: category.slug ?? category.id,
      deleted_at: category.deleted_at ?? null,
      has_children: state.categories.some(
        (item) => item.parent_id === category.id && visible(item),
      ),
    });

    if (path === '/categories/') {
      if (method === 'GET' && parentId) {
        const parent = state.categories.find((item) => item.id === parentId);

        if (!parent || !visible(parent)) {
          return route.fulfill({ status: 404, json: { error: 'Not found' } });
        }

        await state.childrenDelays.get(parentId);

        if (state.failChildren) {
          return route.fulfill({ status: 503, json: { error: 'Failed' } });
        }

        return route.fulfill({
          json: {
            categories: state.categories
              .filter((item) => item.parent_id === parentId && visible(item))
              .map(resource),
          },
        });
      }

      if (method === 'GET' && state.failRoots) {
        return route.fulfill({ status: 503, json: { error: 'Unavailable' } });
      }

      if (method === 'POST') {
        const input = categoryInputSchema.parse(route.request().postDataJSON());
        const base = (input.slug || input.name).toLowerCase().replace(/[^a-z0-9]+/g, '-');
        let slug = base;
        let suffix = 0;

        while (state.categories.some((item) => (item.slug ?? item.id) === slug)) {
          if (input.slug && !input.allow_slug_suffix) {
            return route.fulfill({
              status: 409,
              json: {
                error:
                  'This category slug is already in use. Choose another slug or accept the suggested suffix.',
              },
            });
          }

          slug = `${base}-${++suffix}`;
        }

        const category = {
          ...created,
          name: input.name,
          slug,
          parent_id: input.parent_id,
        };
        state.categories.push(category);

        return route.fulfill({ status: 201, json: { category: resource(category) } });
      }

      return route.fulfill({
        json: {
          categories: state.categories
            .filter((item) => item.parent_id === null && visible(item))
            .map(resource),
        },
      });
    }

    if (path.startsWith('/categories/')) {
      const id = path.split('/')[2];
      const category = state.categories.find((item) => item.id === id);

      if (!category) {
        return route.fulfill({ status: 404, json: { error: 'Not found' } });
      }

      const parts = path.split('/');

      if (method !== 'GET' && ['restore', 'permanent'].includes(parts[3]!)) {
        if (!category.deleted_at) {
          return route.fulfill({
            status: 409,
            json: { error: 'Only deleted categories can be changed.' },
          });
        }

        const ids = new Set([category.id]);
        let previous = 0;

        while (previous !== ids.size) {
          previous = ids.size;

          for (const item of state.categories) {
            if (ids.has(item.parent_id ?? '')) {
              ids.add(item.id);
            }
          }
        }

        if (parts[3] === 'restore') {
          let parent = category.parent_id;

          while (parent) {
            ids.add(parent);
            parent = state.categories.find((item) => item.id === parent)!.parent_id;
          }

          for (const item of state.categories) {
            if (ids.has(item.id)) {
              item.deleted_at = null;
            }
          }

          return route.fulfill({ json: { category: resource(category) } });
        }

        state.categories = state.categories.filter((item) => !ids.has(item.id));

        for (const [productId, links] of state.links) {
          state.links.set(
            productId,
            links.filter((id) => !ids.has(id)),
          );
        }

        return route.fulfill({ status: 204 });
      }

      if (!visible(category) || (method !== 'GET' && category.deleted_at)) {
        return route.fulfill({ status: 404, json: { error: 'Not found' } });
      }

      if (parts[3] === 'branch') {
        await state.branchDelays.get(id!);

        if (state.failBranch) {
          return route.fulfill({ status: 503, json: { error: 'Branch unavailable' } });
        }

        const path = [category];
        let parent = category.parent_id;

        while (parent) {
          const ancestor = state.categories.find((item) => item.id === parent)!;
          path.unshift(ancestor);
          parent = ancestor.parent_id;
        }

        return route.fulfill({
          json: {
            path: path.map(resource),
            levels: path.map((item) => ({
              parent_id: item.parent_id,
              categories: state.categories
                .filter((sibling) => sibling.parent_id === item.parent_id && visible(sibling))
                .map(resource),
            })),
          },
        });
      }

      if (parts[3] === 'products') {
        if (method === 'GET') {
          state.relationReads++;

          if (state.failLinks) {
            return route.fulfill({ status: 503, json: { error: 'Failed' } });
          }

          return route.fulfill({
            json: {
              products: state.products.filter((item) =>
                state.links.get(item.id)?.includes(category.id),
              ),
            },
          });
        }

        const productId = parts[4]!;
        const links = state.links.get(productId) ?? [];
        state.links.set(
          productId,
          method === 'PUT'
            ? [...new Set([...links, category.id])]
            : links.filter((id) => id !== category.id),
        );

        return route.fulfill({ status: 204 });
      }

      if (method === 'PATCH') {
        const input = categoryInputSchema.parse(route.request().postDataJSON());
        category.parent_id = input.parent_id;
        category.name = input.name;
        category.slug = input.slug ?? category.slug ?? category.id;
      }

      if (method === 'DELETE') {
        const removed = new Set([id]);

        for (const item of state.categories) {
          if (removed.has(item.parent_id ?? '')) {
            removed.add(item.id);
          }
        }

        for (const item of state.categories) {
          if (removed.has(item.id)) {
            item.deleted_at = '2026-09-28T12:00:00+00:00';
          }
        }

        return route.fulfill({ status: 204 });
      }

      return route.fulfill({ json: { category: resource(category) } });
    }

    if (path === '/products/') {
      return route.fulfill({ json: { products: state.products } });
    }

    return route.fulfill({ status: 404, json: { error: 'Not found' } });
  });

  return state;
}

test.describe('Category name and slug editor', () => {
  test('previews automatic slugs, accepts custom slugs and displays readable category names', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    state.categories = [];
    await page.goto('/categories');
    await page.getByRole('button', { name: 'Create category', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeDisabled();
    await page.getByLabel('Name', { exact: true }).fill('Summer Shoes');
    await expect(page.getByLabel('Slug', { exact: false })).toHaveValue('summer-shoes');
    await expect(page.getByText('Automatic', { exact: true })).toBeVisible();
    await page.getByLabel('Slug', { exact: false }).fill('My Custom Slug');
    await expect(page.getByText('Automatic', { exact: true })).toHaveCount(0);
    await page.getByLabel('Name', { exact: true }).fill('Summer Collection');
    await expect(page.getByLabel('Slug', { exact: false })).toHaveValue('My Custom Slug');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page).toHaveURL(`/categories/${created.id}`);
    await expect(
      page.getByRole('treeitem', { name: 'Summer Collection', exact: true }),
    ).toBeVisible();
    expect(state.writes).toHaveLength(1);
    expect(JSON.parse(state.writes[0]!.body!)).toEqual({
      name: 'Summer Collection',
      slug: 'My Custom Slug',
      parent_id: null,
      allow_slug_suffix: false,
    });
  });

  test('warns about custom conflicts and saves only after explicit suffix consent', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    state.categories[0]!.slug = 'shoes';
    await page.goto('/categories');
    await page.getByRole('button', { name: 'Create category', exact: true }).click();
    await page.getByLabel('Name', { exact: true }).fill('New shoes');
    await page.getByLabel('Slug', { exact: false }).fill('SHOES');
    await expect(
      page.getByText('This slug is already in use. Available suggestion: shoes-1'),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeDisabled();
    expect(state.writes).toEqual([]);
    await page.getByRole('button', { name: 'Save with suggested slug', exact: true }).click();
    await expect(page).toHaveURL(`/categories/${created.id}`);
    await expect(page.getByLabel('Slug', { exact: false })).toHaveValue('shoes-1');
    expect(state.writes).toHaveLength(1);
    expect(JSON.parse(state.writes[0]!.body!)).toEqual({
      name: 'New shoes',
      slug: 'SHOES',
      parent_id: null,
      allow_slug_suffix: true,
    });
  });
});

test.describe('Category row activation', () => {
  test('selects and toggles children without duplicate reads', async ({ page }) => {
    const state = await mockCatalog(page);
    await page.goto('/categories');
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    const row = tree.getByRole('treeitem', { name: root.id, exact: true });
    await expect(row).toBeVisible();
    state.reads = [];
    await row.click();
    await expect(row).toHaveAttribute('aria-selected', 'true');
    await expect(row).toHaveAttribute('aria-expanded', 'true');
    await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toBeVisible();
    await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
    expect(state.reads.toSorted()).toEqual([
      `/categories/${root.id}/products/`,
      childrenPath(root.id),
    ]);
    state.reads = [];
    await row.click();
    await expect(row).toHaveAttribute('aria-expanded', 'false');
    await expect(row).toHaveAttribute('aria-selected', 'true');
    await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveCount(0);
    await row.click();
    await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toBeVisible();
    expect(state.reads).toEqual([]);
    await tree.getByRole('treeitem', { name: child.id, exact: true }).click();
    await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
    expect(state.reads).toEqual([`/categories/${child.id}/products/`]);
  });

  test('keeps expander activation separate from selection with mouse and keyboard', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    await page.goto('/categories');
    const row = page.getByRole('treeitem', { name: root.id, exact: true });
    await row.getByRole('button', { name: `Expand ${root.id}` }).click();
    await expect(page.getByRole('treeitem', { name: child.id, exact: true })).toBeVisible();
    await expect(row).toHaveAttribute('aria-selected', 'false');
    await row.getByRole('button', { name: `Collapse ${root.id}` }).press('Enter');
    await expect(row).toHaveAttribute('aria-expanded', 'false');
    await expect(page).toHaveURL('/categories');
    expect(state.reads.filter((path) => path.endsWith('/products/'))).toEqual([]);
    await row.press('Enter');
    await expect(row).toHaveAttribute('aria-expanded', 'true');
    await expect(row).toHaveAttribute('aria-selected', 'true');
    await row.press('Space');
    await expect(row).toHaveAttribute('aria-expanded', 'false');
    await expect(row).toHaveAttribute('aria-selected', 'true');
  });
});

test('loads only expanded levels and the selected category products', async ({ page }) => {
  const state = await mockCatalog(page);
  await page.goto(`/categories/${root.id}`);
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
  await expect(page.getByRole('table').getByText(second.sku)).toHaveCount(0);
  expect(state.relationReads).toBe(1);
  expect(state.reads).toEqual([
    `/categories/${root.id}/branch`,
    `/categories/${root.id}/products/`,
  ]);
  expect(state.reads).not.toContain(childrenPath(root.id));
  await tree.getByRole('button', { name: `Expand ${root.id}` }).click();
  await tree.getByRole('treeitem', { name: child.id, exact: true }).click();
  await expect(page).toHaveURL(`/categories/${child.id}`);
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  expect(state.relationReads).toBe(2);
  expect(state.reads).not.toContain(`/categories/${child.id}`);
  expect(state.reads).not.toContain(`/categories/${root.id}`);
  await page.goBack();
  await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
  expect(
    state.reads.filter((path) => path.includes('/products/') && !path.startsWith('/categories/')),
  ).toEqual([]);
  await expect(tree.getByRole('treeitem', { name: root.id, exact: true })).toHaveAttribute(
    'aria-expanded',
    'true',
  );
  await tree.getByRole('treeitem', { name: child.id, exact: true }).click();
  state.reads = [];
  await page.reload();
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  expect(state.reads.filter((path) => path === `/categories/${child.id}/branch`)).toHaveLength(1);
  expect(state.reads).not.toContain(`/categories/${root.id}`);
});

test('creates a root, edits its parent and deletes a subtree while preserving products', async ({
  page,
}) => {
  const state = await mockCatalog(page);
  await page.goto('/categories');
  await page.getByRole('button', { name: 'Create category', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toContainText(
    'Root level',
  );
  expect(state.writes).toEqual([]);
  await page.getByLabel('Name', { exact: true }).fill(created.id);
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page).toHaveURL(`/categories/${created.id}`);
  expect(state.writes[0]).toEqual({
    method: 'POST',
    path: '/categories/',
    body: JSON.stringify({
      name: created.id,
      slug: null,
      parent_id: null,
      allow_slug_suffix: false,
    }),
  });
  await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
  await page.getByRole('button', { name: 'Parent category', exact: true }).click();
  const picker = page.getByRole('dialog', { name: 'Choose parent' });
  await expect(picker.getByRole('treeitem', { name: created.id, exact: true })).toHaveCount(0);
  await picker.getByRole('treeitem', { name: root.id, exact: true }).click();
  state.failWrite = true;
  await page.getByRole('button', { name: 'Save changes' }).click();
  await expect(page.getByRole('alert')).toContainText('Please retry this change.');
  state.failWrite = false;
  await page.getByRole('button', { name: 'Save changes' }).click();
  await expect(page.getByText('Category updated', { exact: true })).toBeVisible();
  expect(state.categories.find((item) => item.id === created.id)?.parent_id).toBe(root.id);
  await page
    .getByRole('tree', { name: 'Category tree', exact: true })
    .getByRole('treeitem', { name: root.id, exact: true })
    .click();
  await page.getByRole('button', { name: 'Parent category', exact: true }).click();
  await expect(picker.getByRole('treeitem')).toHaveCount(1);
  await expect(picker.getByRole('treeitem', { name: other.id })).toBeVisible();
  await picker.getByRole('button', { name: 'Close', exact: true }).click();
  await page.getByRole('button', { name: 'Delete category', exact: true }).click();
  const confirmation = page.getByRole('alertdialog');
  await expect(confirmation).toContainText('Products will be preserved.');
  await confirmation.getByRole('button', { name: 'Cancel' }).click();
  await expect(confirmation).toHaveCount(0);
  expect(state.writes.some((write) => write.method === 'DELETE')).toBe(false);
  await page.getByRole('button', { name: 'Delete category', exact: true }).click();
  await confirmation.getByRole('button', { name: 'Delete category', exact: true }).click();
  await expect(page).toHaveURL('/categories');
  await expect(page.getByRole('treeitem')).toHaveCount(1);
  expect(state.products).toEqual([product, second]);
  expect(state.categories.filter((category) => !category.deleted_at)).toEqual([other]);
});

test('opens a sibling draft and creates it only after Save', async ({ page }) => {
  const state = await mockCatalog(page);
  const grandchild = { id: '0195f582-9762-7c2a-9228-4060489e0605', parent_id: child.id };
  state.categories.push(grandchild);
  await page.goto(`/categories/${child.id}`);
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  await page.getByRole('button', { name: `Expand ${child.id}` }).click();
  await expect(page.getByRole('treeitem', { name: grandchild.id, exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Create category', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toContainText(
    root.id,
  );
  await expect(page).toHaveURL(`/categories/${child.id}`);
  const placeholder = page.getByRole('treeitem', { name: 'New category', exact: true });
  await expect(placeholder).toBeVisible();
  await expect(placeholder).toHaveAttribute('aria-level', '2');
  await expect(placeholder).toHaveAttribute('title', `${root.id} / New category`);
  await expect(placeholder).toContainText('Unsaved');
  expect(state.writes).toEqual([]);
  expect(state.categories.some((category) => category.id === created.id)).toBe(false);
  state.reads = [];
  await page.getByLabel('Name', { exact: true }).fill(created.id);
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page).toHaveURL(`/categories/${created.id}`);
  await expect(placeholder).toHaveCount(0);
  expect(state.writes).toEqual([
    {
      method: 'POST',
      path: '/categories/',
      body: JSON.stringify({
        name: created.id,
        slug: null,
        parent_id: root.id,
        allow_slug_suffix: false,
      }),
    },
  ]);
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  await expect(tree.getByRole('treeitem', { name: grandchild.id, exact: true })).toHaveCount(0);
  await expect(tree.getByRole('treeitem', { name: created.id, exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(tree.getByRole('treeitem', { name: created.id, exact: true })).toHaveAttribute(
    'title',
    `${root.id} / ${created.id}`,
  );
  await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toContainText(
    root.id,
  );
  await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
  await expect(page.getByText('No products in this category', { exact: true })).toBeVisible();
  expect(state.reads).toEqual([
    `/categories/${created.id}/branch`,
    `/categories/${created.id}/products/`,
  ]);
});

test('cancels a root sibling draft without writing to the backend', async ({ page }) => {
  const state = await mockCatalog(page);
  await page.goto(`/categories/${root.id}`);
  await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
  await page.getByRole('button', { name: 'Create category', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toContainText(
    'Root level',
  );
  await expect(page.getByRole('button', { name: 'Save', exact: true })).toBeDisabled();
  const placeholder = page.getByRole('treeitem', { name: 'New category', exact: true });
  await expect(placeholder).toHaveAttribute('aria-level', '1');
  await page.getByRole('button', { name: 'Cancel', exact: true }).click();
  await expect(placeholder).toHaveCount(0);
  await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
  await expect(page).toHaveURL(`/categories/${root.id}`);
  expect(state.writes).toEqual([]);
  expect(state.categories.some((category) => category.id === created.id)).toBe(false);
});

test('preserves a changed creation parent on failure and saves it on retry', async ({ page }) => {
  const state = await mockCatalog(page);
  await page.goto('/categories');
  await page.getByRole('button', { name: 'Create category', exact: true }).click();
  await page.getByRole('button', { name: 'Parent category', exact: true }).click();
  await page
    .getByRole('dialog', { name: 'Choose parent' })
    .getByRole('treeitem', { name: other.id, exact: true })
    .click();
  expect(state.writes).toEqual([]);
  state.failWrite = true;
  await page.getByLabel('Name', { exact: true }).fill(created.id);
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('Please retry this change.');
  await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toContainText(
    other.id,
  );
  await expect(page).toHaveURL('/categories');
  const placeholder = page.getByRole('treeitem', { name: 'New category', exact: true });
  await expect(placeholder).toHaveAttribute('title', `${other.id} / New category`);
  await expect(placeholder).toHaveAttribute('aria-level', '2');
  expect(state.categories.some((category) => category.id === created.id)).toBe(false);
  state.failWrite = false;
  await page.getByLabel('Name', { exact: true }).fill(created.id);
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page).toHaveURL(`/categories/${created.id}`);
  expect(state.writes).toEqual([
    {
      method: 'POST',
      path: '/categories/',
      body: JSON.stringify({
        name: created.id,
        slug: null,
        parent_id: other.id,
        allow_slug_suffix: false,
      }),
    },
    {
      method: 'POST',
      path: '/categories/',
      body: JSON.stringify({
        name: created.id,
        slug: null,
        parent_id: other.id,
        allow_slug_suffix: false,
      }),
    },
  ]);
});

test.describe('Category drag and drop', () => {
  test('cancels pending hover expansion when dragging is cancelled after opening another branch', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    const nested = { ...created, parent_id: other.id };
    const leaf = { id: '0195f582-9762-7c2a-9228-4060489e0605', parent_id: nested.id };
    state.categories.push(nested, leaf);
    await page.clock.install();
    await page.goto(`/categories/${child.id}`);
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
    await page.clock.pauseAt(Date.now());
    await tree.getByRole('treeitem', { name: child.id, exact: true }).hover();
    await page.mouse.down();
    const destination = tree.getByRole('treeitem', { name: other.id, exact: true });
    await destination.hover();
    await destination.hover();
    await page.clock.runFor(650);
    const nestedRow = tree.getByRole('treeitem', { name: nested.id, exact: true });
    await expect(nestedRow).toBeVisible();
    await nestedRow.hover();
    await nestedRow.hover();
    await page.keyboard.press('Escape');
    await page.mouse.up();
    await page.clock.fastForward(1000);
    await expect(nestedRow).toHaveAttribute('aria-expanded', 'false');
    expect(state.reads).not.toContain(childrenPath(nested.id));
    expect(state.writes).toEqual([]);
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
  });

  test('opens successive closed levels while holding a dragged category and reads each level once', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    const nested = { ...created, parent_id: other.id };
    const leaf = { id: '0195f582-9762-7c2a-9228-4060489e0605', parent_id: nested.id };
    state.categories.push(nested, leaf);
    await page.goto(`/categories/${child.id}`);
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    const row = tree.getByRole('treeitem', { name: child.id, exact: true });
    await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
    state.reads = [];
    await row.hover();
    await page.mouse.down();
    const destination = tree.getByRole('treeitem', { name: other.id, exact: true });
    await destination.hover();
    await destination.hover();
    const nestedRow = tree.getByRole('treeitem', { name: nested.id, exact: true });
    await expect(nestedRow).toBeVisible();
    await nestedRow.hover();
    await nestedRow.hover();
    const leafRow = tree.getByRole('treeitem', { name: leaf.id, exact: true });
    await expect(leafRow).toBeVisible();
    await leafRow.hover();
    await leafRow.hover();
    await page.mouse.up();
    await expect(row).toHaveAttribute(
      'title',
      `${other.id} / ${nested.id} / ${leaf.id} / ${child.id}`,
    );
    expect(state.reads.filter(isChildrenPath)).toEqual([
      childrenPath(other.id),
      childrenPath(nested.id),
    ]);
    expect(state.reads.filter((path) => path.endsWith('/products/'))).toEqual([]);
    expect(state.writes).toEqual([]);
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeEnabled();
  });

  test('previews an unselected category under its new parent and writes only on Save', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    await page.goto(`/categories/${root.id}`);
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    await tree.getByRole('button', { name: `Expand ${root.id}` }).click();
    const row = tree.getByRole('treeitem', { name: child.id, exact: true });
    await row.dragTo(tree.getByRole('treeitem', { name: other.id, exact: true }));
    await expect(page).toHaveURL(`/categories/${child.id}`);
    await expect(row).toHaveAttribute('title', `${other.id} / ${child.id}`);
    await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toContainText(
      other.id,
    );
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeEnabled();
    expect(state.writes).toEqual([]);
    expect(state.categories.find((category) => category.id === child.id)?.parent_id).toBe(root.id);
    state.failWrite = true;
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByRole('alert')).toContainText('Please retry this change.');
    await expect(row).toHaveAttribute('title', `${other.id} / ${child.id}`);
    state.failWrite = false;
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    expect(state.categories.find((category) => category.id === child.id)?.parent_id).toBe(other.id);
    expect(state.writes).toEqual([
      {
        method: 'PATCH',
        path: `/categories/${child.id}`,
        body: JSON.stringify({
          name: child.id,
          slug: child.id,
          parent_id: other.id,
          allow_slug_suffix: false,
        }),
      },
      {
        method: 'PATCH',
        path: `/categories/${child.id}`,
        body: JSON.stringify({
          name: child.id,
          slug: child.id,
          parent_id: other.id,
          allow_slug_suffix: false,
        }),
      },
    ]);
  });

  test('previews moving to roots, reverting, and discarding on navigation', async ({ page }) => {
    const state = await mockCatalog(page);
    await page.goto(`/categories/${child.id}`);
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    const row = tree.getByRole('treeitem', { name: child.id, exact: true });
    await row.dragTo(page.getByRole('group', { name: 'Drop here to move to root' }));
    await expect(row).toHaveAttribute('aria-level', '1');
    await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toContainText(
      'Root level',
    );
    await row.dragTo(tree.getByRole('treeitem', { name: root.id, exact: true }));
    await expect(row).toHaveAttribute('aria-level', '2');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await row.dragTo(tree.getByRole('treeitem', { name: other.id, exact: true }));
    await expect(row).toHaveAttribute('title', `${other.id} / ${child.id}`);
    await tree.getByRole('treeitem', { name: root.id, exact: true }).click();
    await page.goBack();
    await expect(row).toHaveAttribute('title', `${root.id} / ${child.id}`);
    expect(state.writes).toEqual([]);
  });

  test('rejects descendants and deleted targets and disables dragging during creation', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    state.categories.find((category) => category.id === other.id)!.deleted_at =
      '2026-09-28T12:00:00Z';
    await page.goto(`/categories/${child.id}?include_deleted=1`);
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    const row = tree.getByRole('treeitem', { name: child.id, exact: true });
    const parent = tree.getByRole('treeitem', { name: root.id, exact: true });
    const deleted = tree.getByRole('treeitem', { name: other.id, exact: true });
    await parent.dragTo(row);
    await expect(page).toHaveURL(`/categories/${child.id}?include_deleted=1`);
    await row.dragTo(deleted);
    await expect(deleted).toHaveAttribute('draggable', 'false');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    await page.getByRole('button', { name: 'Create category', exact: true }).click();
    await expect(row).toHaveAttribute('draggable', 'false');
    expect(state.writes).toEqual([]);
  });
});

test.describe('New category tree preview', () => {
  test('scrolls a root draft into view in a long tree', async ({ page }) => {
    const state = await mockCatalog(page);
    state.categories = Array.from({ length: 40 }, (_, index) => ({
      id: `0195f582-9762-7c2a-9228-${String(index).padStart(12, '0')}`,
      parent_id: null,
    }));
    await page.goto('/categories');
    await expect(page.getByRole('treeitem')).toHaveCount(40);
    await page.getByRole('button', { name: 'Create category', exact: true }).click();
    const placeholder = page.getByRole('treeitem', { name: 'New category', exact: true });
    await expect(placeholder).toBeInViewport();
    expect(state.writes).toEqual([]);
  });

  test('shows the first root draft in an empty catalog and removes it on cancel', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    state.categories = [];
    await page.goto('/categories');
    await page.getByRole('button', { name: 'Create category', exact: true }).click();
    const placeholder = page.getByRole('treeitem', { name: 'New category', exact: true });
    await expect(placeholder).toBeVisible();
    await expect(placeholder).toHaveAttribute('aria-level', '1');
    await expect(placeholder).toHaveAttribute('aria-disabled', 'true');
    await page.getByRole('button', { name: 'Cancel', exact: true }).click();
    await expect(placeholder).toHaveCount(0);
    await expect(page.getByText('No categories yet')).toBeVisible();
    expect(state.writes).toEqual([]);
  });

  test('moves the draft between nested and root levels and discards it on selection', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    const grandchild = { ...created, parent_id: child.id };
    state.categories.push(grandchild);
    await page.goto(`/categories/${root.id}`);
    await page.getByRole('button', { name: 'Create category', exact: true }).click();
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    const placeholder = tree.getByRole('treeitem', { name: 'New category', exact: true });
    await expect(placeholder).toHaveAttribute('aria-level', '1');
    await page.getByRole('button', { name: 'Parent category', exact: true }).click();
    const picker = page.getByRole('dialog', { name: 'Choose parent' });
    await picker.getByRole('button', { name: `Expand ${root.id}` }).click();
    await picker.getByRole('treeitem', { name: child.id, exact: true }).click();
    await expect(placeholder).toHaveAttribute('aria-level', '3');
    await expect(placeholder).toHaveAttribute('title', `${root.id} / ${child.id} / New category`);
    await expect(tree.getByRole('treeitem', { name: grandchild.id, exact: true })).toBeVisible();
    await expect(tree.getByRole('treeitem')).toHaveCount(5);
    await expect(tree.getByRole('treeitem').nth(3)).toHaveAttribute('aria-label', 'New category');
    await tree.getByRole('treeitem', { name: child.id, exact: true }).focus();
    await page.keyboard.press('End');
    await expect(tree.getByRole('treeitem', { name: other.id, exact: true })).toBeFocused();
    await page.getByRole('button', { name: 'Parent category', exact: true }).click();
    await picker.getByRole('button', { name: 'Root level', exact: true }).click();
    await expect(placeholder).toHaveAttribute('aria-level', '1');
    await expect(tree.getByRole('treeitem').last()).toHaveAttribute('aria-label', 'New category');
    await tree.getByRole('treeitem', { name: other.id, exact: true }).click();
    await expect(placeholder).toHaveCount(0);
    await expect(page).toHaveURL(`/categories/${other.id}`);
    expect(state.writes).toEqual([]);
  });
});

test('adds and removes one product link without modifying other links or products', async ({
  page,
}) => {
  const state = await mockCatalog(page);
  await page.goto(`/categories/${root.id}`);
  await page.getByRole('button', { name: 'Add existing product' }).click();
  const picker = page.getByRole('dialog', { name: 'Add existing product' });
  await expect(picker.getByText('Already added')).toBeVisible();
  await picker.getByRole('searchbox').fill('cotton');
  state.failWrite = true;
  await picker.getByRole('button', { name: `Add: ${second.sku}` }).click();
  await expect(picker.getByRole('alert')).toContainText('Please retry this change.');
  state.failWrite = false;
  await picker.getByRole('button', { name: `Add: ${second.sku}` }).click();
  await expect(picker).toBeHidden();
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  expect(state.links.get(second.id)).toEqual([child.id, root.id]);
  await page.getByRole('button', { name: `Remove from category: ${product.sku}` }).click();
  const confirmation = page.getByRole('alertdialog');
  await expect(confirmation).toContainText('other category links will be preserved');
  await confirmation.getByRole('button', { name: 'Remove from category', exact: true }).click();
  await expect(confirmation).toBeHidden();
  await expect(page.getByRole('table').getByText(product.sku)).toHaveCount(0);
  expect(state.links.get(product.id)).toEqual([other.id]);
  expect(state.products).toEqual([product, second]);
  await expect(page.getByRole('link', { name: second.sku })).toHaveAttribute(
    'href',
    `/products/${second.id}/edit`,
  );
});

test('does not display partial results after failure and preserves the last complete snapshot', async ({
  page,
}) => {
  const state = await mockCatalog(page);
  state.failLinks = true;
  await page.goto(`/categories/${root.id}`);
  await expect(page.getByRole('alert')).toContainText('Could not load category products');
  await expect(page.getByRole('table')).toHaveCount(0);
  await expect(page.getByText('No products in this category', { exact: true })).toHaveCount(0);
  state.failLinks = false;
  await page.getByRole('button', { name: 'Try again' }).click();
  await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
  state.failLinks = true;
  await page.getByRole('button', { name: 'Refresh categories' }).click();
  await expect(page.getByRole('alert')).toContainText('Showing the last complete result');
  await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
});

test('handles missing categories and an empty catalog', async ({ page }) => {
  const state = await mockCatalog(page);
  state.categories = [];
  await page.goto(`/categories/${root.id}`);
  await expect(page.getByRole('alert').filter({ hasText: 'Category not found' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Delete category', exact: true })).toHaveCount(0);
  await expect(page.getByRole('treeitem')).toHaveCount(0);
  await page.goto('/categories');
  await expect(page.getByText('No categories yet')).toBeVisible();
});

test('supports keyboard tree navigation and desktop/mobile layouts', async ({ page }) => {
  await mockCatalog(page);
  await page.setViewportSize({ width: 1440, height: 1100 });
  await page.goto(`/categories/${root.id}`);
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
  await tree.getByRole('treeitem', { name: root.id, exact: true }).focus();
  await page.keyboard.press('ArrowRight');
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toBeVisible();
  await page.keyboard.press('ArrowDown');
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(`/categories/${child.id}`);
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Toggle Sidebar' })).toBeHidden();
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.getByRole('button', { name: 'Toggle Sidebar' })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  );
  await page.getByRole('button', { name: 'Add existing product' }).click();
  await expect(page.getByRole('dialog', { name: 'Add existing product' })).toBeVisible();
});

test('retries one failed branch and reuses its cached children on reopening', async ({ page }) => {
  const state = await mockCatalog(page);
  await page.goto('/categories');
  state.failChildren = true;
  await page.getByRole('button', { name: `Expand ${root.id}` }).click();
  const retry = page.getByRole('button', { name: `Retry loading children of ${root.id}` });
  await expect(retry).toBeVisible();
  state.failChildren = false;
  await retry.click();
  await expect(page.getByRole('treeitem', { name: child.id, exact: true })).toBeVisible();
  const count = state.reads.filter(isChildrenPath).length;
  await page.getByRole('button', { name: `Collapse ${root.id}` }).click();
  await page.getByRole('button', { name: `Expand ${root.id}` }).click();
  await expect(page.getByRole('treeitem', { name: child.id, exact: true })).toBeVisible();
  expect(state.reads.filter(isChildrenPath)).toHaveLength(count);
});

test('restores a deep branch atomically and preserves indentation beyond six levels', async ({
  page,
}) => {
  const state = await mockCatalog(page);
  const chain = [root];

  for (let depth = 1; depth <= 9; depth++) {
    const category = {
      id: `0195f582-9762-7c2a-9228-4060489e07${String(depth).padStart(2, '0')}`,
      parent_id: chain.at(-1)!.id,
    };
    const sibling = {
      id: `0195f582-9762-7c2a-9228-4060489e08${String(depth).padStart(2, '0')}`,
      parent_id: category.parent_id,
    };
    chain.push(category);
    state.categories.push(category, sibling);
  }

  const selected = chain.at(-1)!;
  let release!: () => void;
  state.branchDelays.set(
    selected.id,
    new Promise<void>((resolve) => {
      release = resolve;
    }),
  );
  await page.goto(`/categories/${selected.id}`);
  await expect.poll(() => state.reads).toEqual([`/categories/${selected.id}/branch`]);
  await expect(page.getByRole('treeitem')).toHaveCount(0);
  release();
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });

  for (const [depth, category] of chain.entries()) {
    await expect(tree.getByRole('treeitem', { name: category.id, exact: true })).toHaveAttribute(
      'aria-level',
      String(depth + 1),
    );
  }

  const leaf = tree.getByRole('treeitem', { name: selected.id, exact: true });
  await expect(leaf).toHaveAttribute('aria-selected', 'true');
  await expect(leaf).toHaveCSS('padding-left', '152px');
  await expect
    .poll(() => state.reads)
    .toEqual([`/categories/${selected.id}/branch`, `/categories/${selected.id}/products/`]);
  await expect(tree).toHaveAttribute('aria-busy', 'false');
  await expect
    .poll(() =>
      tree.evaluate((element) => element.scrollWidth > element.parentElement!.clientWidth),
    )
    .toBe(true);
  await page.getByRole('button', { name: 'Parent category', exact: true }).click();
  const picker = page.getByRole('dialog', { name: 'Choose parent' });
  await expect(picker.getByRole('treeitem', { name: selected.id, exact: true })).toHaveCount(0);
  await expect(picker.getByRole('treeitem', { name: chain[8]!.id, exact: true })).toBeVisible();
  expect(state.reads).toHaveLength(2);
});

test('retries a failed cold branch without showing a partial tree', async ({ page }) => {
  const state = await mockCatalog(page);
  state.failBranch = true;
  await page.goto(`/categories/${child.id}`);
  await expect(page.getByRole('treeitem')).toHaveCount(0);
  const sidebar = page.getByRole('complementary', { name: 'Category tree' });
  await expect(sidebar.getByRole('alert')).toContainText('Could not load categories');
  state.failBranch = false;
  await sidebar.getByRole('button', { name: 'Try again' }).click();
  await expect(page.getByRole('treeitem', { name: child.id, exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  expect(state.reads.filter((path) => path.endsWith('/branch'))).toHaveLength(2);
  expect(state.reads).not.toContain(`/categories/${child.id}`);
});

test('keeps the current tree when an older branch finishes after navigation', async ({ page }) => {
  const state = await mockCatalog(page);
  await page.goto(`/categories/${child.id}`);
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  await tree.getByRole('treeitem', { name: other.id, exact: true }).click();
  await expect(page).toHaveURL(`/categories/${other.id}`);
  await page.reload();
  await expect(tree.getByRole('treeitem', { name: other.id, exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  let release!: () => void;
  state.branchDelays.set(
    child.id,
    new Promise<void>((resolve) => {
      release = resolve;
    }),
  );
  state.reads = [];
  await page.goBack();
  await expect.poll(() => state.reads).toContain(`/categories/${child.id}/branch`);
  await tree.getByRole('treeitem', { name: other.id, exact: true }).click();
  await expect(page).toHaveURL(`/categories/${other.id}`);
  release();
  await expect(tree.getByRole('treeitem', { name: other.id, exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveCount(0);
  expect(state.reads).not.toContain(`/categories/${other.id}/branch`);
});

test('retains the tree after a failed branch refresh and retries only the selected branch', async ({
  page,
}) => {
  const state = await mockCatalog(page);
  await page.goto(`/categories/${root.id}`);
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  await expect(tree.getByRole('treeitem', { name: root.id, exact: true })).toBeVisible();
  state.failBranch = true;
  await page.getByRole('button', { name: 'Refresh categories' }).click();
  const sidebar = page.getByRole('complementary', { name: 'Category tree' });
  await expect(sidebar.getByRole('alert')).toHaveCount(1);
  await expect(tree.getByRole('treeitem', { name: root.id, exact: true })).toBeVisible();
  state.failBranch = false;
  await sidebar.getByRole('alert').getByRole('button', { name: 'Try again' }).click();
  await expect(sidebar.getByRole('alert')).toHaveCount(0);
  expect(state.reads.filter((path) => path.endsWith('/branch'))).toHaveLength(3);
  expect(state.reads.filter((path) => path === '/categories/')).toHaveLength(0);
});

test('refreshes a nested category once and labels the refresh button', async ({ page }) => {
  const state = await mockCatalog(page);
  await page.goto(`/categories/${child.id}`);
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  const refresh = page.getByRole('button', { name: 'Refresh categories' });
  await expect(refresh).toHaveAttribute('title', 'Refresh categories');
  const parent = page.getByRole('button', { name: 'Parent category', exact: true });
  await expect(parent.locator('[title]')).toHaveAttribute('title', root.id);
  state.reads = [];
  await refresh.click();
  await expect(refresh).toBeEnabled();
  await expect
    .poll(() => state.reads)
    .toEqual([`/categories/${child.id}/branch`, `/categories/${child.id}/products/`]);
  await expect(parent.locator('[title]')).toHaveAttribute('title', root.id);
});

test('returns from a deep reload to roots and refreshes without aborted reads', async ({
  page,
}) => {
  const state = await mockCatalog(page);
  const grandchild = { ...created, parent_id: child.id };
  const leaf = { id: '0195f582-9762-7c2a-9228-4060489e0605', parent_id: grandchild.id };
  const newRoot = { id: '0195f582-9762-7c2a-9228-4060489e0606', parent_id: null };
  const newChild = { id: '0195f582-9762-7c2a-9228-4060489e0607', parent_id: root.id };
  state.categories.push(grandchild, leaf);
  await page.goto(`/categories/${leaf.id}`);
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  await expect(tree.getByRole('treeitem', { name: leaf.id, exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await page.reload();
  await expect(tree.getByRole('treeitem', { name: leaf.id, exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await tree.getByRole('treeitem', { name: child.id, exact: true }).click();
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  await expect(tree.getByRole('treeitem', { name: leaf.id, exact: true })).toHaveCount(0);
  await tree.getByRole('treeitem', { name: root.id, exact: true }).click();
  await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
  await expect(tree.getByRole('treeitem', { name: leaf.id, exact: true })).toHaveCount(0);
  state.categories.push(newRoot, newChild);
  state.reads = [];
  const aborted: string[] = [];
  page.on('requestfailed', (request) => {
    if (request.url().includes('/pim/web/')) {
      aborted.push(request.url());
    }
  });
  const refresh = page.getByRole('button', { name: 'Refresh categories' });
  await refresh.click();
  await expect(refresh).toBeEnabled();
  await expect(tree.getByRole('treeitem', { name: newRoot.id, exact: true })).toBeVisible();
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveCount(0);
  expect(state.reads).toEqual([
    `/categories/${root.id}/branch`,
    `/categories/${root.id}/products/`,
  ]);
  expect(aborted).toEqual([]);
  state.reads = [];
  await tree.getByRole('button', { name: `Expand ${root.id}` }).click();
  await expect(tree.getByRole('treeitem', { name: newChild.id, exact: true })).toBeVisible();
  expect(state.reads).toEqual([childrenPath(root.id)]);
  expect(aborted).toEqual([]);
});

test('keeps delayed children open during browser history navigation', async ({ page }) => {
  const state = await mockCatalog(page);
  const grandchild = { ...created, parent_id: child.id };
  state.categories.push(grandchild);
  let release!: () => void;
  state.childrenDelays.set(
    child.id,
    new Promise<void>((resolve) => {
      release = resolve;
    }),
  );
  await page.goto(`/categories/${root.id}`);
  await page.getByRole('treeitem', { name: root.id, exact: true }).click();
  await page.getByRole('treeitem', { name: child.id, exact: true }).click();
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  const aborted: string[] = [];
  page.on('requestfailed', (request) => {
    if (new URL(request.url()).searchParams.has('parent_id')) {
      aborted.push(request.url());
    }
  });
  await expect.poll(() => state.reads).toContain(childrenPath(child.id));
  await page.goBack();
  await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
  release();
  await expect(tree.getByRole('treeitem', { name: grandchild.id, exact: true })).toBeVisible();
  await page.goForward();
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  await expect(tree.getByRole('treeitem', { name: grandchild.id, exact: true })).toBeVisible();
  await tree.getByRole('treeitem', { name: grandchild.id, exact: true }).click();
  await expect(page).toHaveURL(`/categories/${grandchild.id}`);
  await page.goBack();
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(tree.getByRole('treeitem', { name: grandchild.id, exact: true })).toBeVisible();
  expect(state.reads.filter(isChildrenPath)).toEqual([
    childrenPath(root.id),
    childrenPath(child.id),
  ]);
  expect(aborted).toEqual([]);
});

test('preserves an expansion made before the selected branch response arrives', async ({
  page,
}) => {
  const state = await mockCatalog(page);
  const grandchild = { ...created, parent_id: child.id };
  state.categories.push(grandchild);
  await page.clock.install();
  await page.goto(`/categories/${root.id}`);
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  await tree.getByRole('button', { name: `Expand ${root.id}` }).click();
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toBeVisible();
  await page.clock.fastForward(31_000);
  let release!: () => void;
  state.branchDelays.set(
    child.id,
    new Promise<void>((resolve) => {
      release = resolve;
    }),
  );
  await tree.getByRole('treeitem', { name: child.id, exact: true }).click();
  await expect.poll(() => state.reads).toContain(`/categories/${child.id}/branch`);
  release();
  await expect(tree.getByRole('treeitem', { name: grandchild.id, exact: true })).toBeVisible();
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveAttribute(
    'aria-expanded',
    'true',
  );
  expect(state.reads.filter((path) => path === childrenPath(child.id))).toHaveLength(1);
});

async function prepareDescendantProductCache(page: Page, id: string, cached: boolean) {
  if (!cached) {
    return;
  }

  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  await page.clock.fastForward(20_000);
  await tree.getByRole('button', { name: `Expand ${child.id}` }).click();
  await tree.getByRole('treeitem', { name: id, exact: true }).click();
  await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
  await tree.getByRole('treeitem', { name: child.id, exact: true }).click();
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
}

test('reveals a moved category under its new parent and updates both parent expanders', async ({
  page,
}) => {
  await mockCatalog(page);
  await page.goto(`/categories/${child.id}`);
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  await page.getByRole('button', { name: 'Parent category', exact: true }).click();
  await page
    .getByRole('dialog', { name: 'Choose parent' })
    .getByRole('treeitem', { name: other.id, exact: true })
    .click();
  await page.getByRole('button', { name: 'Save changes' }).click();
  await expect(page.getByText('Category updated', { exact: true })).toBeVisible();
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveAttribute(
    'title',
    `${other.id} / ${child.id}`,
  );
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(tree.getByRole('button', { name: `Collapse ${other.id}` })).toBeVisible();
  await expect(
    tree.getByRole('treeitem', { name: root.id, exact: true }).getByRole('button'),
  ).toHaveCount(0);
});

test('refreshes a category moved externally and synchronizes its tree and parent form', async ({
  page,
}) => {
  const state = await mockCatalog(page);
  await page.goto(`/categories/${child.id}`);
  await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  state.categories.find((category) => category.id === child.id)!.parent_id = other.id;
  state.reads = [];
  const refresh = page.getByRole('button', { name: 'Refresh categories' });
  await refresh.click();
  await expect(refresh).toBeEnabled();
  const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveAttribute(
    'title',
    `${other.id} / ${child.id}`,
  );
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(
    tree.getByRole('treeitem', { name: root.id, exact: true }).getByRole('button'),
  ).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toContainText(
    other.id,
  );
  await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
  expect(state.reads).toEqual([
    `/categories/${child.id}/branch`,
    `/categories/${child.id}/products/`,
  ]);
  state.categories.find((category) => category.id === child.id)!.parent_id = null;
  state.reads = [];
  await refresh.click();
  await expect(refresh).toBeEnabled();
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveAttribute(
    'aria-level',
    '1',
  );
  await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveAttribute(
    'title',
    child.id,
  );
  await expect(
    tree.getByRole('treeitem', { name: other.id, exact: true }).getByRole('button'),
  ).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toContainText(
    'Root',
  );
  expect(state.reads).toEqual([
    `/categories/${child.id}/branch`,
    `/categories/${child.id}/products/`,
  ]);
});

for (const productCache of ['missing', 'fresh', 'expired'] as const) {
  test(`navigates down after tree refresh with expired ancestors and ${productCache} products`, async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    const grandchild = { ...created, parent_id: child.id };
    state.categories.push(grandchild);
    state.links.set(product.id, [root.id, grandchild.id]);
    await page.clock.install();
    await page.goto(`/categories/${child.id}`);
    await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });

    await prepareDescendantProductCache(page, grandchild.id, productCache !== 'missing');
    await page.clock.fastForward(productCache === 'fresh' ? 15_000 : 35_000);
    state.links.set(product.id, [root.id]);
    state.links.set(second.id, [child.id, grandchild.id]);
    state.reads = [];
    const refresh = page.getByRole('button', { name: 'Refresh categories' });
    await refresh.click();
    await expect(refresh).toBeEnabled();
    expect(state.reads).toEqual([
      `/categories/${child.id}/branch`,
      `/categories/${child.id}/products/`,
    ]);
    state.reads = [];
    await tree.getByRole('button', { name: `Expand ${child.id}` }).click();
    await tree.getByRole('treeitem', { name: grandchild.id, exact: true }).click();
    await expect(page).toHaveURL(`/categories/${grandchild.id}`);
    await expect(tree.getByRole('treeitem', { name: grandchild.id, exact: true })).toHaveAttribute(
      'aria-selected',
      'true',
    );
    await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
    await expect(page.getByRole('table').getByText(product.sku)).toHaveCount(0);
    await expect(tree).toHaveAttribute('aria-busy', 'false');
    await expect(refresh).toBeEnabled();
    expect(state.reads).toEqual([childrenPath(child.id), `/categories/${grandchild.id}/products/`]);
  });
}

test.describe('Refreshing external hierarchy changes', () => {
  test('updates siblings at every visible level and child flags from one fresh branch', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    const grandchild = { ...created, parent_id: child.id };
    const leaf = { id: '0195f582-9762-7c2a-9228-4060489e0605', parent_id: grandchild.id };
    state.categories.push(grandchild, leaf);
    await page.goto(`/categories/${leaf.id}`);
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    const row = (id: string) => tree.getByRole('treeitem', { name: id, exact: true });
    const refresh = page.getByRole('button', { name: 'Refresh categories' });
    await expect(row(leaf.id)).toHaveAttribute('aria-selected', 'true');
    await expect(refresh).toBeEnabled();
    const siblings = [null, root.id, child.id, grandchild.id].map((parent_id, index) => ({
      id: `0195f582-9762-7c2a-9228-4060489e062${index}`,
      parent_id,
    }));
    const newChild = { id: '0195f582-9762-7c2a-9228-4060489e0630', parent_id: other.id };
    state.categories.push(...siblings, newChild);
    state.reads = [];
    await refresh.click();
    await expect(refresh).toBeEnabled();

    for (const sibling of siblings) {
      await expect(row(sibling.id)).toBeVisible();
    }

    await expect(row(other.id)).toHaveAttribute('aria-expanded', 'false');
    expect(state.reads).toEqual([
      `/categories/${leaf.id}/branch`,
      `/categories/${leaf.id}/products/`,
    ]);
    await tree.getByRole('button', { name: `Expand ${other.id}` }).click();
    await expect(row(newChild.id)).toBeVisible();
    state.categories = state.categories.filter(
      (category) => category.id !== newChild.id && category.id !== siblings[1]!.id,
    );
    state.categories.find((category) => category.id === siblings[2]!.id)!.parent_id = root.id;
    state.reads = [];
    await refresh.click();
    await expect(refresh).toBeEnabled();
    await expect(row(other.id).getByRole('button')).toHaveCount(0);
    await expect(row(newChild.id)).toHaveCount(0);
    await expect(row(siblings[1]!.id)).toHaveCount(0);
    await expect(row(siblings[2]!.id)).toHaveAttribute('title', `${root.id} / ${siblings[2]!.id}`);
    await expect(row(siblings[2]!.id)).toHaveAttribute('aria-level', '2');
    await expect(row(leaf.id)).toHaveAttribute('aria-selected', 'true');
    expect(state.reads).toEqual([
      `/categories/${leaf.id}/branch`,
      `/categories/${leaf.id}/products/`,
    ]);
  });

  test('follows an externally moved ancestor and removes its old placement', async ({ page }) => {
    const state = await mockCatalog(page);
    const leaf = { ...created, parent_id: child.id };
    state.categories.push(leaf);
    await page.goto(`/categories/${leaf.id}`);
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    const refresh = page.getByRole('button', { name: 'Refresh categories' });
    await expect(tree.getByRole('treeitem', { name: leaf.id, exact: true })).toBeVisible();
    await expect(refresh).toBeEnabled();
    state.categories.find((category) => category.id === child.id)!.parent_id = other.id;
    state.reads = [];
    await refresh.click();
    await expect(refresh).toBeEnabled();
    await expect(tree.getByRole('treeitem', { name: leaf.id, exact: true })).toHaveAttribute(
      'title',
      `${other.id} / ${child.id} / ${leaf.id}`,
    );
    await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveCount(1);
    await expect(
      tree.getByRole('treeitem', { name: root.id, exact: true }).getByRole('button'),
    ).toHaveCount(0);
    expect(state.reads).toEqual([
      `/categories/${leaf.id}/branch`,
      `/categories/${leaf.id}/products/`,
    ]);
  });

  for (const missing of [1, 2, 3]) {
    test(`recovers selection after ${missing} categories on its path are deleted`, async ({
      page,
    }) => {
      const state = await mockCatalog(page);
      const leaf = { ...created, parent_id: child.id };
      const path = [root, child, leaf];
      state.categories.push(leaf);
      await page.goto(`/categories/${leaf.id}`);
      const refresh = page.getByRole('button', { name: 'Refresh categories' });
      const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
      await expect(tree.getByRole('treeitem', { name: leaf.id, exact: true })).toBeVisible();
      await expect(refresh).toBeEnabled();
      const deleted = path.slice(-missing).map((category) => category.id);
      state.categories = state.categories.filter((category) => !deleted.includes(category.id));
      state.reads = [];
      await refresh.click();
      const survivor = path[path.length - missing - 1];
      await expect(page).toHaveURL(survivor ? `/categories/${survivor.id}` : '/categories');
      await expect(refresh).toBeEnabled();
      await expect(
        page.getByText(
          'The selected category was deleted. The nearest available parent or the root level is now shown.',
        ),
      ).toBeVisible();

      for (const id of deleted) {
        await expect(tree.getByRole('treeitem', { name: id, exact: true })).toHaveCount(0);
      }

      const survivingRow = tree.getByRole('treeitem', {
        name: survivor?.id ?? other.id,
        exact: true,
      });
      await expect(survivingRow).toBeVisible();
      await expect(survivingRow).toHaveAttribute('aria-selected', String(!!survivor));
      await expect(page.getByRole('table')).toHaveCount(survivor ? 1 : 0);
      await expect(
        page.getByRole('table').getByText(missing === 1 ? second.sku : product.sku),
      ).toHaveCount(survivor ? 1 : 0);

      expect(state.reads).toEqual([
        ...[...path]
          .reverse()
          .slice(0, missing + 1)
          .map((category) => `/categories/${category.id}/branch`),
        survivor ? `/categories/${survivor.id}/products/` : '/categories/',
      ]);
    });
  }

  test('refreshes only roots when nothing is selected', async ({ page }) => {
    const state = await mockCatalog(page);
    await page.goto('/categories');
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    await expect(tree.getByRole('treeitem', { name: root.id, exact: true })).toBeVisible();
    state.categories.push(created);
    state.reads = [];
    const refresh = page.getByRole('button', { name: 'Refresh categories' });
    await refresh.click();
    await expect(refresh).toBeEnabled();
    await expect(tree.getByRole('treeitem', { name: created.id, exact: true })).toBeVisible();
    expect(state.reads).toEqual(['/categories/']);
  });
});

test.describe('Parent move preview', () => {
  test('previews a deeper parent with its children and retains the draft after a failed save', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    const parent = { ...created, parent_id: other.id };
    const sibling = { id: '0195f582-9762-7c2a-9228-4060489e0605', parent_id: parent.id };
    state.categories.push(parent, sibling);
    await page.goto(`/categories/${child.id}`);
    await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
    state.reads = [];
    await page.getByRole('button', { name: 'Parent category', exact: true }).click();
    const picker = page.getByRole('dialog', { name: 'Choose parent' });
    await picker.getByRole('button', { name: `Expand ${other.id}` }).click();
    await picker.getByRole('treeitem', { name: parent.id, exact: true }).click();
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    const selected = tree.getByRole('treeitem', { name: child.id, exact: true });
    const path = `Root level / ${other.id} / ${parent.id} / ${child.id}`;
    await expect(page.getByText(path, { exact: true })).toBeVisible();
    await expect(selected).toHaveAttribute('aria-level', '3');
    await expect(selected).toHaveAttribute('aria-selected', 'true');
    await expect(selected).toHaveAttribute('title', `${other.id} / ${parent.id} / ${child.id}`);
    await expect(tree.getByRole('treeitem', { name: sibling.id, exact: true })).toBeVisible();
    await expect(
      tree.getByRole('treeitem', { name: root.id, exact: true }).getByRole('button'),
    ).toHaveCount(0);
    expect(state.writes).toEqual([]);
    expect(state.reads.filter((url) => url.endsWith('/products/'))).toEqual([]);
    expect(state.categories.find((category) => category.id === child.id)?.parent_id).toBe(root.id);
    await expect(page).toHaveURL(`/categories/${child.id}`);
    state.failWrite = true;
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByRole('alert')).toContainText('Please retry this change.');
    await expect(selected).toHaveAttribute('aria-level', '3');
    await expect(page.getByText(path, { exact: true })).toBeVisible();
    state.failWrite = false;
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Category updated', { exact: true })).toBeVisible();
    await expect(selected).toHaveAttribute('aria-level', '3');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    expect(state.categories.find((category) => category.id === child.id)?.parent_id).toBe(
      parent.id,
    );
  });

  test('reverts the preview when the original parent is selected again', async ({ page }) => {
    const state = await mockCatalog(page);
    await page.goto(`/categories/${child.id}`);
    await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
    const choose = page.getByRole('button', { name: 'Parent category', exact: true });
    const picker = page.getByRole('dialog', { name: 'Choose parent' });
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    const row = tree.getByRole('treeitem', { name: child.id, exact: true });
    await choose.click();
    await picker.getByRole('treeitem', { name: other.id, exact: true }).click();
    await expect(row).toHaveAttribute('title', `${other.id} / ${child.id}`);
    await expect(tree.getByRole('status')).toHaveCount(0);
    await choose.click();
    await picker.getByRole('treeitem', { name: root.id, exact: true }).click();
    await expect(row).toHaveAttribute('title', `${root.id} / ${child.id}`);
    await expect(
      page.getByText(`Root level / ${root.id} / ${child.id}`, { exact: true }),
    ).toBeVisible();
    await expect(
      tree.getByRole('treeitem', { name: other.id, exact: true }).getByRole('button'),
    ).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    expect(state.writes).toEqual([]);
  });

  test('previews a move to roots and discards it on navigation without polluting the cache', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    await page.goto(`/categories/${child.id}`);
    await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
    await page.getByRole('button', { name: 'Parent category', exact: true }).click();
    await page
      .getByRole('dialog', { name: 'Choose parent' })
      .getByRole('button', { name: 'Root level', exact: true })
      .click();
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    const row = tree.getByRole('treeitem', { name: child.id, exact: true });
    await expect(row).toHaveAttribute('aria-level', '1');
    await expect(page.getByText(`Root level / ${child.id}`, { exact: true })).toBeVisible();
    await tree.getByRole('treeitem', { name: other.id, exact: true }).click();
    await expect(page).toHaveURL(`/categories/${other.id}`);
    await page.goBack();
    await expect(row).toHaveAttribute('title', `${root.id} / ${child.id}`);
    await expect(row).toHaveAttribute('aria-level', '2');
    await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    expect(state.writes).toEqual([]);
  });
});

test.describe('Deleted categories', () => {
  const deletedAt = '2026-09-28T12:00:00+00:00';

  test('shows deleted nodes in place, preserves mode on navigation and refresh, and keeps parent choices active', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    state.categories.find((category) => category.id === child.id)!.deleted_at = deletedAt;
    await page.goto(`/categories/${root.id}`);
    await expect(page.getByRole('table').getByText(product.sku)).toBeVisible();
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    await expect(tree.getByRole('button', { name: `Expand ${root.id}` })).toHaveCount(0);
    const showDeleted = page.getByRole('switch', { name: 'Show deleted', exact: true });
    await expect(showDeleted).not.toBeChecked();
    await showDeleted.press('Space');
    await expect(showDeleted).toBeChecked();
    await expect(page).toHaveURL(`/categories/${root.id}?include_deleted=1`);
    await tree.getByRole('button', { name: `Expand ${root.id}` }).click();
    const row = tree.getByRole('treeitem', { name: child.id, exact: true });
    await expect(row.getByText('Deleted', { exact: true })).toBeVisible();
    await row.click();
    await expect(page).toHaveURL(`/categories/${child.id}?include_deleted=1`);
    await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toHaveCount(0);
    await expect(
      page.getByRole('button', { name: 'Add existing product', exact: true }),
    ).toBeDisabled();
    await expect(
      page.getByRole('button', { name: `Remove from category: ${second.sku}` }),
    ).toHaveCount(0);
    await page.reload();
    await expect(row).toHaveAttribute('aria-selected', 'true');
    const refresh = page.getByRole('button', { name: 'Refresh categories' });
    await expect(refresh).toBeEnabled();
    state.reads = [];
    state.visibilityReads = [];
    await refresh.click();
    await expect(refresh).toBeEnabled();
    expect(state.reads).toEqual([
      `/categories/${child.id}/branch`,
      `/categories/${child.id}/products/`,
    ]);
    expect(state.visibilityReads.every((read) => read.includeDeleted)).toBe(true);
    await tree.getByRole('treeitem', { name: other.id, exact: true }).click();
    await page.getByRole('button', { name: 'Parent category', exact: true }).click();
    const picker = page.getByRole('dialog', { name: 'Choose parent' });
    await expect(picker.getByRole('treeitem', { name: child.id, exact: true })).toHaveCount(0);
    await expect(picker.getByRole('button', { name: `Expand ${root.id}` })).toHaveCount(0);
    await picker.getByRole('button', { name: 'Close', exact: true }).click();
    await row.click();
    await page.getByRole('switch', { name: 'Show deleted', exact: true }).click();
    await expect(page).toHaveURL(`/categories/${root.id}`);
    await expect(showDeleted).not.toBeChecked();
    await expect(row).toHaveCount(0);
  });

  test('restores a subtree and deleted ancestors only after confirmation and preserves retry', async ({
    page,
  }) => {
    const state = await mockCatalog(page);
    const leaf = { ...created, parent_id: child.id, deleted_at: deletedAt };
    const sibling = {
      id: '0195f582-9762-7c2a-9228-4060489e0605',
      parent_id: root.id,
      deleted_at: deletedAt,
    };
    state.categories.push(leaf, sibling);
    state.categories.find((category) => category.id === root.id)!.deleted_at = deletedAt;
    state.categories.find((category) => category.id === child.id)!.deleted_at = deletedAt;
    await page.goto(`/categories/${child.id}?include_deleted=1`);
    await page.getByRole('button', { name: 'Restore category', exact: true }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('all its descendants');
    expect(state.writes).toEqual([]);
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click();
    expect(state.writes).toEqual([]);
    await page.getByRole('button', { name: 'Restore category', exact: true }).click();
    state.failWrite = true;
    await dialog.getByRole('button', { name: 'Restore category', exact: true }).click();
    await expect(dialog.getByRole('alert')).toContainText('Please retry this change.');
    state.failWrite = false;
    await dialog.getByRole('button', { name: 'Restore category', exact: true }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Parent category', exact: true })).toBeVisible();
    expect(state.categories.find((category) => category.id === root.id)?.deleted_at).toBeNull();
    expect(state.categories.find((category) => category.id === leaf.id)?.deleted_at).toBeNull();
    expect(state.categories.find((category) => category.id === sibling.id)?.deleted_at).toBe(
      deletedAt,
    );
    await page.getByRole('switch', { name: 'Show deleted', exact: true }).click();
    await expect(page).toHaveURL(`/categories/${child.id}`);
    await expect(page.getByRole('table').getByText(second.sku)).toBeVisible();
  });

  test('permanently deletes a subtree and links but preserves products', async ({ page }) => {
    const state = await mockCatalog(page);
    state.categories.find((category) => category.id === child.id)!.deleted_at = deletedAt;
    state.categories.push({ ...created, parent_id: child.id, deleted_at: deletedAt });
    await page.goto(`/categories/${child.id}?include_deleted=1`);
    await page.getByRole('button', { name: 'Delete permanently', exact: true }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toContainText('Products will be preserved.');
    expect(state.writes).toEqual([]);
    await dialog.getByRole('button', { name: 'Delete permanently', exact: true }).click();
    await expect(page).toHaveURL(`/categories/${root.id}?include_deleted=1`);
    const tree = page.getByRole('tree', { name: 'Category tree', exact: true });
    await expect(tree.getByRole('treeitem', { name: child.id, exact: true })).toHaveCount(0);
    expect(state.categories.some((category) => category.id === created.id)).toBe(false);
    expect(state.links.get(second.id)).toEqual([]);
    expect(state.products).toEqual([product, second]);
    expect(state.writes[0]?.path).toBe(`/categories/${child.id}/permanent`);
  });

  test('keeps a newly soft-deleted selection visible in inclusive mode', async ({ page }) => {
    const state = await mockCatalog(page);
    await page.goto(`/categories/${child.id}?include_deleted=1`);
    await page.getByRole('button', { name: 'Delete category', exact: true }).click();
    await page
      .getByRole('alertdialog')
      .getByRole('button', { name: 'Delete category', exact: true })
      .click();
    await expect(page.getByRole('button', { name: 'Restore category', exact: true })).toBeVisible();
    await expect(page).toHaveURL(`/categories/${child.id}?include_deleted=1`);
    expect(state.categories.find((category) => category.id === child.id)?.deleted_at).toBe(
      deletedAt,
    );
  });
});
