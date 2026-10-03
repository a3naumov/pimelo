import { defineComponent, h, nextTick, ref } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as api from '../../api/category/categories';
import { useCategoryTree } from './useCategoryTree';
import { refreshCategoryHierarchy, refreshCategoryTree } from '../../model/category/queries';
import { categoryKeys } from '../../model/category/keys';
import type {
  Category,
  CategoriesResponse,
  CategoryBranchResponse,
} from '../../model/category/schemas';

const root = {
  name: 'root',
  slug: 'category',
  id: 'root',
  parent_id: null,
  has_children: true,
  deleted_at: null,
};
const child = {
  name: 'child',
  slug: 'category',
  id: 'child',
  parent_id: root.id,
  has_children: true,
  deleted_at: null,
};
const other = {
  name: 'other',
  slug: 'category',
  id: 'other',
  parent_id: null,
  has_children: true,
  deleted_at: null,
};
const clients: QueryClient[] = [];
enableAutoUnmount(afterEach);
afterEach(() => {
  clients.splice(0).forEach((client) => client.clear());
  vi.restoreAllMocks();
});

function setup(selected = ref(''), excluded = '', draftParentPath = ref<Category[]>()) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, staleTime: 30_000 } },
  });
  clients.push(client);
  let result!: ReturnType<typeof useCategoryTree>;
  mount(
    defineComponent({
      setup() {
        result = useCategoryTree(selected, excluded, undefined, false, draftParentPath);

        return () => h('div');
      },
    }),
    { global: { plugins: [[VueQueryPlugin, { queryClient: client }]] } },
  );

  return { ...result, client };
}

describe('Category tree loading', () => {
  it('projects a draft under a leaf without changing cached flags or loading nonexistent children', async () => {
    const leaf = { ...root, has_children: false };
    vi.spyOn(api, 'getCategories').mockResolvedValue({ categories: [leaf] });
    const children = vi.spyOn(api, 'getCategoryChildren');
    const draft = ref<Category[]>();
    const tree = setup(ref(''), '', draft);
    await vi.waitFor(() => expect(tree.rows.value).toHaveLength(1));
    draft.value = [leaf];
    await vi.waitFor(() => expect(tree.entries.value).toContainEqual({ kind: 'draft', depth: 1 }));
    expect(tree.rows.value[0]?.hasChildren).toBe(true);
    expect(children).not.toHaveBeenCalled();
    expect(tree.client.getQueryData<CategoriesResponse>(categoryKeys.roots)?.categories).toEqual([
      leaf,
    ]);
    draft.value = [];
    await vi.waitFor(() => expect(tree.entries.value).toContainEqual({ kind: 'draft', depth: 0 }));
    expect(tree.rows.value[0]?.hasChildren).toBe(false);
    draft.value = undefined;
    await vi.waitFor(() => expect(tree.entries.value).toHaveLength(1));
    expect(tree.rows.value[0]?.hasChildren).toBe(false);
    expect(children).not.toHaveBeenCalled();
  });

  it('loads roots first and cancels a branch request when it is collapsed', async () => {
    vi.spyOn(api, 'getCategories').mockResolvedValue({ categories: [root, other] });
    let requestSignal: AbortSignal | undefined;
    const read = vi.spyOn(api, 'getCategoryChildren').mockImplementation((_id, signal) => {
      requestSignal = signal;

      return new Promise(() => {});
    });
    const tree = setup();
    await vi.waitFor(() => expect(tree.rows.value).toHaveLength(2));
    expect(read).not.toHaveBeenCalled();
    tree.toggle(root.id);
    await vi.waitFor(() => expect(read).toHaveBeenCalledTimes(1));
    expect(read).toHaveBeenCalledWith(root.id, expect.any(AbortSignal), false);
    tree.toggle(root.id);
    await vi.waitFor(() => expect(requestSignal?.aborted).toBe(true));
  });

  it('restores only the selected ancestor path without fetching unrelated branches', async () => {
    vi.spyOn(api, 'getCategories').mockResolvedValue({ categories: [root, other] });
    const branch = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue({
      path: [root, child],
      levels: [
        { parent_id: null, categories: [root, other] },
        { parent_id: root.id, categories: [child] },
      ],
    });
    const read = vi.spyOn(api, 'getCategoryChildren').mockResolvedValue({ categories: [child] });
    const tree = setup(ref(child.id));
    await vi.waitFor(() =>
      expect(tree.rows.value.map((row) => row.category.id)).toEqual(['root', 'child', 'other']),
    );
    expect(tree.expanded.value).toEqual(new Set([root.id]));
    expect(read).not.toHaveBeenCalled();
    expect(branch).toHaveBeenCalledExactlyOnceWith(child.id, expect.any(AbortSignal), false);
  });

  it('hides an excluded parent candidate and does not traverse its subtree', async () => {
    vi.spyOn(api, 'getCategories').mockResolvedValue({ categories: [root, other] });
    const read = vi.spyOn(api, 'getCategoryChildren').mockResolvedValue({ categories: [child] });
    const tree = setup(ref(''), root.id);
    await vi.waitFor(() =>
      expect(tree.rows.value.map((row) => row.category.id)).toEqual(['other']),
    );
    tree.toggle(root.id);
    await vi.waitFor(() => expect(tree.expanded.value.size).toBe(0));
    expect(read).not.toHaveBeenCalled();
  });
});

describe('Single expanded path', () => {
  it('follows a moved selection even when its old cached row is still visible', async () => {
    const read = vi.spyOn(api, 'getCategoryBranch').mockResolvedValue({
      path: [root, child],
      levels: [
        { parent_id: null, categories: [root, other] },
        { parent_id: root.id, categories: [child] },
      ],
    });
    const tree = setup(ref(child.id));
    await vi.waitFor(() => expect(tree.expanded.value).toEqual(new Set([root.id])));
    const moved = { ...child, parent_id: other.id };
    read.mockResolvedValue({
      path: [other, moved],
      levels: [
        {
          parent_id: null,
          categories: [{ ...root, has_children: false, deleted_at: null }, other],
        },
        { parent_id: other.id, categories: [moved] },
      ],
    });
    await refreshCategoryHierarchy(tree.client);
    await vi.waitFor(() => expect(tree.expanded.value).toEqual(new Set([other.id])));
    expect(tree.rows.value.map((row) => row.category.id)).toEqual([root.id, other.id, child.id]);
    expect(tree.rows.value.find((row) => row.category.id === child.id)?.category.parent_id).toBe(
      other.id,
    );
    expect(tree.rows.value.find((row) => row.category.id === root.id)?.hasChildren).toBe(false);
  });

  it('closes the previous sibling branch when another one is expanded', async () => {
    const otherChild = {
      ...child,
      name: 'other-child',
      slug: 'category',
      id: 'other-child',
      parent_id: other.id,
    };
    vi.spyOn(api, 'getCategories').mockResolvedValue({ categories: [root, other] });
    vi.spyOn(api, 'getCategoryChildren').mockImplementation(async (id) => ({
      categories: id === root.id ? [child] : [otherChild],
    }));
    const tree = setup();
    await vi.waitFor(() => expect(tree.rows.value).toHaveLength(2));
    tree.toggle(root.id);
    await vi.waitFor(() =>
      expect(tree.rows.value.map((row) => row.category.id)).toContain(child.id),
    );
    tree.toggle(other.id);
    await vi.waitFor(() =>
      expect(tree.rows.value.map((row) => row.category.id)).toContain(otherChild.id),
    );
    expect(tree.expanded.value).toEqual(new Set([other.id]));
    expect(tree.rows.value.map((row) => row.category.id)).not.toContain(child.id);
  });

  it('preserves descendants on selection and collapses them only for refresh', async () => {
    const leaf = { ...child, name: 'leaf', slug: 'category', id: 'leaf', parent_id: child.id };
    vi.spyOn(api, 'getCategoryBranch').mockResolvedValue({
      path: [root, child, leaf],
      levels: [
        { parent_id: null, categories: [root, other] },
        { parent_id: root.id, categories: [child] },
        { parent_id: child.id, categories: [leaf] },
      ],
    });
    const roots = vi.spyOn(api, 'getCategories').mockResolvedValue({ categories: [root, other] });
    const children = vi
      .spyOn(api, 'getCategoryChildren')
      .mockResolvedValue({ categories: [child] });
    const selected = ref(leaf.id);
    const tree = setup(selected);
    await vi.waitFor(() => expect(tree.expanded.value).toEqual(new Set([root.id, child.id])));
    selected.value = root.id;
    await vi.waitFor(() =>
      expect(tree.path.data.value?.map((category) => category.id)).toEqual([root.id]),
    );
    expect(tree.expanded.value).toEqual(new Set([root.id, child.id]));
    expect(tree.rows.value.map((row) => row.category.id)).toContain(leaf.id);
    tree.collapseToSelected();
    await nextTick();
    vi.spyOn(api, 'getCategoryBranch').mockResolvedValue({
      path: [root],
      levels: [{ parent_id: null, categories: [root, other] }],
    });
    vi.spyOn(api, 'getCategoryProducts').mockResolvedValue({ products: [] });
    await refreshCategoryTree(tree.client, {
      id: root.id,
      path: [root],
      signal: new AbortController().signal,
    });
    expect(roots).not.toHaveBeenCalled();
    expect(children).not.toHaveBeenCalled();
    expect(tree.rows.value.map((row) => row.category.id)).toEqual([root.id, other.id]);
    tree.toggle(root.id);
    await vi.waitFor(() =>
      expect(children).toHaveBeenCalledExactlyOnceWith(root.id, expect.any(AbortSignal), false),
    );
  });

  it('keeps an expansion requested while the selected path is loading', async () => {
    vi.spyOn(api, 'getCategories').mockResolvedValue({ categories: [root, other] });
    let resolveBranch!: (value: CategoryBranchResponse) => void;
    vi.spyOn(api, 'getCategoryBranch').mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveBranch = resolve;
        }),
    );
    const children = vi
      .spyOn(api, 'getCategoryChildren')
      .mockResolvedValue({ categories: [child] });
    const selected = ref('');
    const tree = setup(selected);
    await vi.waitFor(() => expect(tree.rows.value).toHaveLength(2));
    await tree.client.invalidateQueries({ queryKey: categoryKeys.roots, refetchType: 'none' });
    selected.value = root.id;
    await vi.waitFor(() => expect(api.getCategoryBranch).toHaveBeenCalledTimes(1));
    tree.toggle(root.id);
    resolveBranch({ path: [root], levels: [{ parent_id: null, categories: [root, other] }] });
    await vi.waitFor(() =>
      expect(tree.rows.value.map((row) => row.category.id)).toContain(child.id),
    );
    expect(tree.expanded.value).toEqual(new Set([root.id]));
    expect(children).toHaveBeenCalledTimes(1);
  });

  it('keeps loading descendants when an ancestor is selected before the response arrives', async () => {
    const leaf = {
      ...child,
      name: 'leaf',
      slug: 'category',
      id: 'leaf',
      parent_id: child.id,
      has_children: false,
      deleted_at: null,
    };
    vi.spyOn(api, 'getCategoryBranch').mockResolvedValue({
      path: [root, child],
      levels: [
        { parent_id: null, categories: [root, other] },
        { parent_id: root.id, categories: [child] },
      ],
    });
    let resolveChildren!: (value: CategoriesResponse) => void;
    let requestSignal: AbortSignal | undefined;
    const children = vi.spyOn(api, 'getCategoryChildren').mockImplementation((_id, signal) => {
      requestSignal = signal;

      return new Promise((resolve) => {
        resolveChildren = resolve;
      });
    });
    const selected = ref(child.id);
    const tree = setup(selected);
    await vi.waitFor(() =>
      expect(tree.rows.value.map((row) => row.category.id)).toContain(child.id),
    );
    tree.toggle(child.id);
    await vi.waitFor(() => expect(children).toHaveBeenCalledTimes(1));
    selected.value = root.id;
    await vi.waitFor(() => expect(tree.path.data.value).toEqual([root]));
    expect(requestSignal?.aborted).toBe(false);
    resolveChildren({ categories: [leaf] });
    await vi.waitFor(() =>
      expect(tree.rows.value.map((row) => row.category.id)).toContain(leaf.id),
    );
    expect(tree.expanded.value).toEqual(new Set([root.id, child.id]));
    expect(children).toHaveBeenCalledTimes(1);
  });
});
