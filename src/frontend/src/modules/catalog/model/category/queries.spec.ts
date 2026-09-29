import { AxiosError, AxiosHeaders } from 'axios';
import { defineComponent, h, ref } from 'vue';
import { mount, enableAutoUnmount } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as api from '../../api/category/categories';
import * as productApi from '../../api/product/products';
import {
  categoryKeys,
  useCategories,
  useCategory,
  useCategoryPath,
  useCategoryParentPath,
  refreshCategoryHierarchy,
  refreshCategoryTree,
  useCategoryBranch,
  useCategoryMutations,
  useCategoryProducts,
} from './queries';
import { useProductMutations } from '../product/queries';
const root = { id: 'root', parent_id: null, has_children: false, deleted_at: null };
const child = { id: 'child', parent_id: 'root', has_children: false, deleted_at: null };
const other = { id: 'other', parent_id: null, has_children: false, deleted_at: null };
const branch = (path: (typeof child | typeof root)[]) => ({
  path,
  levels: path.map((category) => ({ parent_id: category.parent_id, categories: [category] })),
});
const product = { id: 'product', sku: 'SKU', deleted_at: null };
const clients: QueryClient[] = [];
enableAutoUnmount(afterEach);
afterEach(() => {
  clients.splice(0).forEach((client) => client.clear());
  vi.restoreAllMocks();
});

function setup<T>(use: () => T, prepare?: (client: QueryClient) => void) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, staleTime: 30_000 }, mutations: { retry: false } },
  });
  clients.push(client);
  prepare?.(client);
  let result!: T;
  mount(
    defineComponent({
      setup() {
        result = use();

        return () => h('div');
      },
    }),
    { global: { plugins: [[VueQueryPlugin, { queryClient: client }]] } },
  );

  return { client, result };
}

describe('Category cache consistency', () => {
  it('loads only the created category after creation without refetching the previous selection', async () => {
    const created = { ...child, id: 'created' };
    const read = vi
      .spyOn(api, 'getCategoryBranch')
      .mockImplementation(async (id) => branch([root, id === created.id ? created : child]));
    const products = vi.spyOn(api, 'getCategoryProducts').mockResolvedValue({ products: [] });
    vi.spyOn(api, 'createCategory').mockResolvedValue(created);
    const selected = ref(child.id);
    const { client, result } = setup(() => {
      const detail = useCategory(selected);

      return {
        detail,
        products: useCategoryProducts(selected, () => !!detail.data.value),
        ...useCategoryMutations(),
      };
    });
    await vi.waitFor(() => expect(result.products.isSuccess.value).toBe(true));
    read.mockClear();
    products.mockClear();
    await result.create.mutateAsync({ parent_id: root.id });
    expect(read).not.toHaveBeenCalled();
    expect(products).not.toHaveBeenCalled();
    expect(client.getQueryState(categoryKeys.products(child.id))?.isInvalidated).toBe(false);
    selected.value = created.id;
    await vi.waitFor(() => expect(result.detail.data.value?.id).toBe(created.id));
    await vi.waitFor(() => expect(result.products.isSuccess.value).toBe(true));
    expect(read).toHaveBeenCalledExactlyOnceWith(created.id, expect.any(AbortSignal), false);
    expect(products).toHaveBeenCalledExactlyOnceWith(created.id, expect.any(AbortSignal), false);
  });

  it('invalidates only the affected category after changing a relation', async () => {
    vi.spyOn(api, 'attachProduct').mockResolvedValue();
    vi.spyOn(api, 'detachProduct').mockResolvedValue();
    const { client, result } = setup(useCategoryMutations);
    client.setQueryData(categoryKeys.products('root'), { products: [] });
    client.setQueryData(categoryKeys.products('other'), { products: [product] });
    await result.attach.mutateAsync({ categoryId: 'root', productId: product.id });
    expect(client.getQueryState(categoryKeys.products('root'))?.isInvalidated).toBe(true);
    expect(client.getQueryState(categoryKeys.products('other'))?.isInvalidated).toBe(false);
    client.setQueryData(categoryKeys.products('root'), { products: [product] });
    await result.detach.mutateAsync({ categoryId: 'root', productId: product.id });
    expect(client.getQueryState(categoryKeys.products('root'))?.isInvalidated).toBe(true);
    expect(client.getQueryData(categoryKeys.products('other'))).toEqual({ products: [product] });
  });

  it('invalidates cached descendants after deletion without eager requests', async () => {
    vi.spyOn(api, 'deleteCategory').mockResolvedValue();
    const { client, result } = setup(useCategoryMutations);
    client.setQueryData(categoryKeys.roots, { categories: [root, other] });
    client.setQueryData(categoryKeys.children('root'), { categories: [child] });
    client.setQueryData(categoryKeys.detail('child'), child);
    client.setQueryData(categoryKeys.products('child'), { products: [product] });
    await result.remove.mutateAsync('root');
    expect(client.getQueryState(categoryKeys.roots)?.isInvalidated).toBe(true);
    expect(client.getQueryState(categoryKeys.children('root'))?.isInvalidated).toBe(true);
    expect(client.getQueryState(categoryKeys.detail('child'))?.isInvalidated).toBe(true);
    expect(client.getQueryState(categoryKeys.products('child'))?.isInvalidated).toBe(true);
  });

  it('preserves the last category product list after a failed refresh', async () => {
    const read = vi.spyOn(api, 'getCategoryProducts').mockResolvedValue({ products: [product] });
    const { result } = setup(() => useCategoryProducts('root'));
    await vi.waitFor(() => expect(result.isSuccess.value).toBe(true));
    read.mockRejectedValue(new Error('Unavailable'));
    await result.refetch();
    expect(result.isError.value).toBe(true);
    expect(result.data.value).toEqual({ products: [product] });
  });
  it('does not retry failed writes or change existing cache', async () => {
    const write = vi.spyOn(api, 'updateCategory').mockRejectedValue(new Error('Conflict'));
    const { client, result } = setup(useCategoryMutations);
    client.setQueryData(categoryKeys.detail('child'), child);
    await expect(
      result.update.mutateAsync({ id: 'child', input: { parent_id: null } }),
    ).rejects.toThrow('Conflict');
    expect(write).toHaveBeenCalledTimes(1);
    expect(client.getQueryData(categoryKeys.detail('child'))).toEqual(child);
  });
  it('invalidates category product lists after every product lifecycle operation', async () => {
    vi.spyOn(productApi, 'createProduct').mockResolvedValue(product);
    vi.spyOn(productApi, 'updateProduct').mockResolvedValue(product);
    vi.spyOn(productApi, 'restoreProduct').mockResolvedValue(product);
    vi.spyOn(productApi, 'deleteProduct').mockResolvedValue();
    vi.spyOn(productApi, 'deleteProductPermanently').mockResolvedValue();
    const { client, result } = setup(useProductMutations);
    const operations = [
      () => result.create.mutateAsync({ sku: 'SKU' }),
      () => result.update.mutateAsync({ id: product.id, input: { sku: 'NEW' } }),
      () => result.restore.mutateAsync(product.id),
      () => result.remove.mutateAsync(product.id),
      () => result.purge.mutateAsync(product.id),
    ];

    for (const operation of operations) {
      client.setQueryData(categoryKeys.products('root'), { products: [product] });
      await operation();
      expect(client.getQueryState(categoryKeys.products('root'))?.isInvalidated).toBe(true);
    }
  });
});

describe('Category resource reuse', () => {
  it('reuses expired ancestors when the selected category level was freshly loaded', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    const { result, client } = setup(
      () => useCategoryPath(child.id),
      (client) => {
        client.setQueryData(
          categoryKeys.roots,
          { categories: [root] },
          { updatedAt: Date.now() - 60_000 },
        );
        client.setQueryData(categoryKeys.children(root.id), { categories: [child] });
      },
    );
    await vi.waitFor(() => expect(result.data.value).toEqual([root, child]));
    expect(read).not.toHaveBeenCalled();
    expect(client.getQueryState(categoryKeys.roots)!.dataUpdatedAt).toBeLessThan(
      Date.now() - 30_000,
    );
  });

  it('fetches a branch when an ancestor was explicitly invalidated', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    const { result } = setup(
      () => useCategoryPath(child.id),
      (client) => {
        client.setQueryData(categoryKeys.roots, { categories: [root] });
        client.setQueryData(categoryKeys.children(root.id), { categories: [child] });
        void client.invalidateQueries({ queryKey: categoryKeys.roots, refetchType: 'none' });
      },
    );
    await vi.waitFor(() => expect(result.data.value).toEqual([root, child]));
    expect(read).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
  });

  it('prefers a newer ancestor location over an older cached level', async () => {
    const movedRoot = { ...root, parent_id: other.id };
    const read = vi.spyOn(api, 'getCategoryBranch');
    const { result } = setup(
      () => useCategoryPath(child.id),
      (client) => {
        client.setQueryData(
          categoryKeys.roots,
          { categories: [root, other] },
          { updatedAt: Date.now() - 60_000 },
        );
        client.setQueryData(categoryKeys.children(other.id), { categories: [movedRoot] });
        client.setQueryData(categoryKeys.children(root.id), { categories: [child] });
      },
    );
    await vi.waitFor(() => expect(result.data.value).toEqual([other, movedRoot, child]));
    expect(read).not.toHaveBeenCalled();
  });

  it('waits for pending roots and reuses their resource for the editor and path', async () => {
    let resolveRoots!: (value: { categories: (typeof root)[] }) => void;
    vi.spyOn(api, 'getCategories').mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveRoots = resolve;
        }),
    );
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root]));
    const { result } = setup(() => ({
      roots: useCategories(),
      detail: useCategory(root.id),
      path: useCategoryPath(root.id),
    }));
    expect(read).not.toHaveBeenCalled();
    resolveRoots({ categories: [root] });
    await vi.waitFor(() => expect(result.path.data.value).toEqual([root]));
    expect(result.detail.data.value).toEqual(root);
    expect(read).not.toHaveBeenCalled();
  });

  it('reuses loaded children and ancestors without requesting either resource again', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch');
    const { result } = setup(
      () => ({
        detail: useCategory(child.id),
        path: useCategoryPath(child.id),
      }),
      (client) => {
        client.setQueryData(categoryKeys.roots, { categories: [root] });
        client.setQueryData(categoryKeys.children(root.id), { categories: [child] });
      },
    );
    await vi.waitFor(() => expect(result.path.data.value).toEqual([root, child]));
    expect(result.detail.data.value).toEqual(child);
    expect(read).not.toHaveBeenCalled();
  });

  it('shares one request for a missing child between the editor and ancestor path', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    const { result } = setup(
      () => ({
        detail: useCategory(child.id),
        path: useCategoryPath(child.id),
      }),
      (client) => {
        client.setQueryData(categoryKeys.roots, { categories: [root] });
      },
    );
    await vi.waitFor(() => expect(result.path.data.value).toEqual([root, child]));
    expect(result.detail.data.value).toEqual(child);
    expect(read).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
  });

  it.each(['expired', 'invalidated'] as const)('does not reuse an %s level', async (state) => {
    const moved = { ...child, parent_id: null };
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([moved]));
    const { result } = setup(
      () => ({
        detail: useCategory(child.id),
        path: useCategoryPath(child.id),
      }),
      (client) => {
        client.setQueryData(
          categoryKeys.children(root.id),
          { categories: [child] },
          {
            updatedAt: state === 'expired' ? Date.now() - 30_001 : Date.now(),
          },
        );

        if (state === 'invalidated') {
          void client.invalidateQueries({ queryKey: categoryKeys.children(root.id) });
        }
      },
    );
    await vi.waitFor(() => expect(result.path.data.value).toEqual([moved]));
    expect(result.detail.data.value).toEqual(moved);
    expect(read).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
  });

  it('can load a category when the pending roots request fails', async () => {
    vi.spyOn(api, 'getCategories').mockRejectedValue(new Error('Unavailable'));
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root]));
    const { result } = setup(() => ({
      roots: useCategories(),
      detail: useCategory(root.id),
      path: useCategoryPath(root.id),
    }));
    await vi.waitFor(() => expect(result.path.data.value).toEqual([root]));
    expect(result.roots.data.value).toEqual({ categories: [root] });
    expect(result.detail.data.value).toEqual(root);
    expect(read).toHaveBeenCalledExactlyOnceWith(root.id, expect.any(AbortSignal), false);
  });
});

describe('Branch publication', () => {
  it('publishes complete levels for a cold deep link with one request', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    const individual = vi.spyOn(api, 'getCategory');
    const levels = vi.spyOn(api, 'getCategoryChildren');
    const { client, result } = setup(() => ({
      branch: useCategoryBranch(child.id),
      detail: useCategory(child.id),
      path: useCategoryPath(child.id),
    }));
    await vi.waitFor(() => expect(result.branch.isSuccess.value).toBe(true));
    expect(result.path.data.value).toEqual([root, child]);
    expect(result.detail.data.value).toEqual(child);
    expect(client.getQueryData(categoryKeys.roots)).toEqual({ categories: [root] });
    expect(client.getQueryData(categoryKeys.children(root.id))).toEqual({ categories: [child] });
    expect(read).toHaveBeenCalledTimes(1);
    expect(individual).not.toHaveBeenCalled();
    expect(levels).not.toHaveBeenCalled();
  });

  it('does not publish a cancelled branch after navigation even if the transport resolves', async () => {
    let resolveOld!: (value: ReturnType<typeof branch>) => void;
    const read = vi.spyOn(api, 'getCategoryBranch').mockImplementation(async (id) => {
      if (id === child.id) {
        return new Promise((resolve) => {
          resolveOld = resolve;
        });
      }

      return branch([other]);
    });
    const selected = ref(child.id);
    const { client, result } = setup(() => useCategoryBranch(selected));
    await vi.waitFor(() => expect(read).toHaveBeenCalledTimes(1));
    selected.value = other.id;
    await vi.waitFor(() => expect(result.data.value?.path).toEqual([other]));
    resolveOld(branch([root, child]));
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(client.getQueryData(categoryKeys.roots)).toEqual({ categories: [other] });
    expect(client.getQueryData(categoryKeys.children(root.id))).toBeUndefined();
    expect(result.data.value?.path).toEqual([other]);
  });
});

describe('Parent path reuse', () => {
  it('resolves a draft parent before the new category has an ID', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root]));
    const { result } = setup(() => useCategoryParentPath('', root.id));
    await vi.waitFor(() => expect(result.value).toEqual([root]));
    expect(read).toHaveBeenCalledExactlyOnceWith(root.id, expect.any(AbortSignal), false);
  });

  it('refreshes only the selected branch while its parent path is displayed', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    const { client, result } = setup(() => ({
      selected: useCategory(child.id),
      parentPath: useCategoryParentPath(child.id, root.id),
    }));
    await vi.waitFor(() => expect(result.parentPath.value).toEqual([root]));
    expect(read).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
    read.mockClear();
    await refreshCategoryHierarchy(client);
    expect(read).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
    expect(result.parentPath.value).toEqual([root]);
    expect(client.getQueryState(categoryKeys.branch(root.id))).toBeUndefined();
  });

  it('keeps the parent path without starting a parent request after refresh failure', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    const { client, result } = setup(() => useCategoryParentPath(child.id, root.id));
    await vi.waitFor(() => expect(result.value).toEqual([root]));
    read.mockClear().mockRejectedValue(new Error('Unavailable'));
    await refreshCategoryHierarchy(client);
    expect(read).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
    expect(result.value).toEqual([root]);
  });

  it('resolves a draft parent from another branch and stops observing it when reverted', async () => {
    const parentId = ref<string | null>(root.id);
    const read = vi
      .spyOn(api, 'getCategoryBranch')
      .mockImplementation(async (id) =>
        id === child.id ? branch([root, child]) : branch([other]),
      );
    const { client, result } = setup(() => useCategoryParentPath(child.id, parentId));
    await vi.waitFor(() => expect(result.value).toEqual([root]));
    parentId.value = other.id;
    await vi.waitFor(() => expect(result.value).toEqual([other]));
    parentId.value = root.id;
    await vi.waitFor(() => expect(result.value).toEqual([root]));
    read.mockClear();
    await refreshCategoryHierarchy(client);
    expect(read).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
    parentId.value = null;
    await vi.waitFor(() => expect(result.value).toBeUndefined());
  });
});

describe('Category tree refresh', () => {
  function refresh(
    client: QueryClient,
    path = [root, child],
    signal = new AbortController().signal,
  ) {
    return refreshCategoryTree(client, { id: path[path.length - 1]?.id ?? '', path, signal });
  }

  it('refreshes cached descendant products on their next selection without fetching them eagerly', async () => {
    const grandchild = {
      id: 'grandchild',
      parent_id: child.id,
      has_children: false,
      deleted_at: null,
    };
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    vi.spyOn(api, 'getCategoryChildren').mockResolvedValue({ categories: [child] });
    const updatedProduct = { ...product, sku: 'UPDATED' };
    const products = vi
      .spyOn(api, 'getCategoryProducts')
      .mockResolvedValue({ products: [updatedProduct] });
    const selected = ref(child.id);
    const { client, result } = setup(() => {
      const detail = useCategory(selected);

      return { detail, products: useCategoryProducts(selected, () => !!detail.data.value) };
    });
    await vi.waitFor(() => expect(result.products.isSuccess.value).toBe(true));
    client.setQueryData(categoryKeys.products(grandchild.id), { products: [product] });
    products.mockClear();
    read.mockClear();
    await refresh(client);
    expect(products).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
    expect(client.getQueryState(categoryKeys.products(grandchild.id))?.isInvalidated).toBe(true);
    expect(client.getQueryData(categoryKeys.products(grandchild.id))).toEqual({
      products: [product],
    });
    client.setQueryData(categoryKeys.children(child.id), { categories: [grandchild] });
    selected.value = grandchild.id;
    await vi.waitFor(() => expect(products).toHaveBeenCalledTimes(2));
    await vi.waitFor(() =>
      expect(result.products.data.value).toEqual({ products: [updatedProduct] }),
    );
    expect(products.mock.calls.map(([id]) => id)).toEqual([child.id, grandchild.id]);
    expect(read).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
  });

  it('reuses fresh product lists during ordinary navigation without Refresh', async () => {
    const read = vi.spyOn(api, 'getCategoryProducts').mockResolvedValue({ products: [product] });
    const selected = ref(child.id);
    const { result } = setup(() => useCategoryProducts(selected));
    await vi.waitFor(() => expect(result.isSuccess.value).toBe(true));
    selected.value = root.id;
    await vi.waitFor(() => expect(read).toHaveBeenCalledTimes(2));
    await vi.waitFor(() => expect(result.isFetching.value).toBe(false));
    selected.value = child.id;
    await vi.waitFor(() => expect(result.data.value).toEqual({ products: [product] }));
    expect(read.mock.calls.map(([id]) => id)).toEqual([child.id, root.id]);
  });

  it('replaces every path level and child flag even while cached data is fresh', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    const roots = vi.spyOn(api, 'getCategories');
    const children = vi.spyOn(api, 'getCategoryChildren');
    const products = vi
      .spyOn(api, 'getCategoryProducts')
      .mockResolvedValue({ products: [product] });
    const { client, result } = setup(() => useCategory(child.id));
    await vi.waitFor(() => expect(result.isSuccess.value).toBe(true));
    const updatedRoot = { ...root, has_children: true, deleted_at: null };
    const updatedChild = { ...child, has_children: true, deleted_at: null };
    const sibling = { ...child, id: 'sibling' };
    read.mockClear().mockResolvedValue({
      path: [updatedRoot, updatedChild],
      levels: [
        { parent_id: null, categories: [updatedRoot, other] },
        { parent_id: root.id, categories: [updatedChild, sibling] },
      ],
    });
    client.setQueryData(categoryKeys.children(child.id), { categories: [] });
    await refresh(client);
    expect(read).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
    expect(products).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
    expect(roots).not.toHaveBeenCalled();
    expect(children).not.toHaveBeenCalled();
    expect(client.getQueryData(categoryKeys.roots)).toEqual({ categories: [updatedRoot, other] });
    expect(client.getQueryData(categoryKeys.children(root.id))).toEqual({
      categories: [updatedChild, sibling],
    });
    expect(client.getQueryState(categoryKeys.children(child.id))?.isInvalidated).toBe(true);
    await vi.waitFor(() => expect(result.data.value?.has_children).toBe(true));
  });

  it('loads only roots when no category is selected', async () => {
    const roots = vi.spyOn(api, 'getCategories').mockResolvedValue({ categories: [other] });
    const read = vi.spyOn(api, 'getCategoryBranch');
    const products = vi.spyOn(api, 'getCategoryProducts');
    const { client } = setup(() => undefined);
    await expect(refresh(client, [])).resolves.toBeNull();
    expect(roots).toHaveBeenCalledTimes(1);
    expect(read).not.toHaveBeenCalled();
    expect(products).not.toHaveBeenCalled();
  });

  it('keeps cached levels on a failed branch refresh and retries the server', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    const products = vi.spyOn(api, 'getCategoryProducts').mockResolvedValue({ products: [] });
    const { client, result } = setup(() => useCategory(child.id));
    await vi.waitFor(() => expect(result.isSuccess.value).toBe(true));
    read.mockClear().mockRejectedValue(new Error('Unavailable'));
    await expect(refresh(client)).rejects.toThrow('Unavailable');
    expect(client.getQueryData(categoryKeys.children(root.id))).toEqual({ categories: [child] });
    expect(result.data.value).toEqual(child);
    expect(products).not.toHaveBeenCalled();
    read.mockResolvedValue(branch([root, child]));
    await expect(refresh(client)).resolves.toBe(child.id);
    expect(read).toHaveBeenCalledTimes(2);
    expect(products).toHaveBeenCalledTimes(1);
  });

  it('follows the new path when an ancestor moves', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    vi.spyOn(api, 'getCategoryProducts').mockResolvedValue({ products: [] });
    const { client, result } = setup(() => useCategoryPath(child.id));
    await vi.waitFor(() => expect(result.isSuccess.value).toBe(true));
    const movedRoot = { ...root, parent_id: other.id };
    read.mockClear().mockResolvedValue(branch([other, movedRoot, child]));
    await refresh(client);
    expect(read).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
    await vi.waitFor(() => expect(result.data.value).toEqual([other, movedRoot, child]));
    expect(client.getQueryData(categoryKeys.roots)).toEqual({ categories: [other] });
  });

  it.each([1, 2, 3])(
    'recovers after %i deleted categories without loading their products',
    async (missing) => {
      const leaf = { ...child, id: 'leaf', parent_id: child.id };
      const path = [root, child, leaf];
      const deleted = path.slice(-missing).map((category) => category.id);
      const read = vi.spyOn(api, 'getCategoryBranch').mockImplementation(async (id) => {
        if (deleted.includes(id)) {
          throw new AxiosError('Not found', undefined, undefined, undefined, {
            status: 404,
            statusText: 'Not found',
            data: {},
            headers: {},
            config: { headers: new AxiosHeaders() },
          });
        }

        return branch(path.slice(0, path.findIndex((category) => category.id === id) + 1));
      });
      const roots = vi.spyOn(api, 'getCategories').mockResolvedValue({ categories: [other] });
      const products = vi.spyOn(api, 'getCategoryProducts').mockResolvedValue({ products: [] });
      const { client } = setup(() => undefined);
      const survivor = path[path.length - missing - 1]?.id ?? null;
      await expect(refresh(client, path)).resolves.toBe(survivor);
      expect(read.mock.calls.map(([id]) => id)).toEqual(
        [...path]
          .reverse()
          .slice(0, missing + 1)
          .map((category) => category.id),
      );
      expect(products.mock.calls.map(([id]) => id)).toEqual(survivor ? [survivor] : []);
      expect(roots).toHaveBeenCalledTimes(survivor ? 0 : 1);
    },
  );

  it('retains a successful hierarchy refresh when products fail', async () => {
    vi.spyOn(api, 'getCategoryBranch').mockResolvedValue(branch([root, child]));
    vi.spyOn(api, 'getCategoryProducts').mockRejectedValue(new Error('Unavailable'));
    const { client } = setup(() => undefined);
    client.setQueryData(categoryKeys.products(child.id), { products: [product] });
    await expect(refresh(client)).resolves.toBe(child.id);
    expect(client.getQueryData(categoryKeys.roots)).toEqual({ categories: [root] });
    expect(client.getQueryData(categoryKeys.products(child.id))).toEqual({ products: [product] });
    expect(client.getQueryState(categoryKeys.products(child.id))?.status).toBe('error');
  });

  it('never publishes an aborted refresh even if the transport resolves later', async () => {
    let resolve!: (value: ReturnType<typeof branch>) => void;
    const read = vi.spyOn(api, 'getCategoryBranch').mockImplementation(
      () =>
        new Promise((done) => {
          resolve = done;
        }),
    );
    const products = vi.spyOn(api, 'getCategoryProducts');
    const { client } = setup(() => undefined);
    const controller = new AbortController();
    const pending = refresh(client, [root, child], controller.signal);
    await vi.waitFor(() => expect(read).toHaveBeenCalledTimes(1));
    controller.abort();
    client.setQueryData(categoryKeys.roots, { categories: [other] });
    resolve(branch([root, child]));
    await expect(pending).rejects.toMatchObject({ name: 'AbortError' });
    expect(client.getQueryData(categoryKeys.roots)).toEqual({ categories: [other] });
    expect(client.getQueryData(categoryKeys.children(root.id))).toBeUndefined();
    expect(products).not.toHaveBeenCalled();
  });
});

describe('Deleted category cache', () => {
  it('isolates active and inclusive branches and reuses each mode independently', async () => {
    const deleted = { ...other, deleted_at: '2026-09-28T12:00:00+00:00' };
    const read = vi
      .spyOn(api, 'getCategoryBranch')
      .mockImplementation(async (_id, _signal, includeDeleted) => ({
        path: [root],
        levels: [{ parent_id: null, categories: includeDeleted ? [root, deleted] : [root] }],
      }));
    const includeDeleted = ref(false);
    const { client, result } = setup(() => useCategoryBranch(root.id, includeDeleted));
    await vi.waitFor(() => expect(result.isSuccess.value).toBe(true));
    includeDeleted.value = true;
    await vi.waitFor(() => expect(result.data.value?.levels[0]?.categories).toHaveLength(2));
    expect(client.getQueryData(categoryKeys.rootList())).toEqual({ categories: [root] });
    expect(client.getQueryData(categoryKeys.rootList(true))).toEqual({
      categories: [root, deleted],
    });
    includeDeleted.value = false;
    await vi.waitFor(() => expect(result.data.value?.levels[0]?.categories).toEqual([root]));
    expect(read.mock.calls.map((call) => call[2])).toEqual([false, true]);
  });

  it('invalidates both visibility modes after restore and purge without eager reads', async () => {
    vi.spyOn(api, 'restoreCategory').mockResolvedValue(child);
    vi.spyOn(api, 'deleteCategoryPermanently').mockResolvedValue();
    const read = vi.spyOn(api, 'getCategoryBranch');
    const { client, result } = setup(useCategoryMutations);

    for (const mutation of [result.restore, result.purge]) {
      client.setQueryData(categoryKeys.branch(child.id), branch([root, child]));
      client.setQueryData(categoryKeys.branch(child.id, true), branch([root, child]));
      await mutation.mutateAsync(child.id);
      expect(client.getQueryState(categoryKeys.branch(child.id))?.isInvalidated).toBe(true);
      expect(client.getQueryState(categoryKeys.branch(child.id, true))?.isInvalidated).toBe(true);
    }

    expect(read).not.toHaveBeenCalled();
  });
});
