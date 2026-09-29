import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import axios from 'axios';
import {
  queryOptions,
  useMutation,
  useQuery,
  useQueryClient,
  type QueryClient,
} from '@tanstack/vue-query';
import * as api from '../../api/category/categories';
import type {
  CategoriesResponse,
  Category,
  CategoryInput,
  CategoryBranchResponse,
} from './schemas';
import { categoryKeys } from './keys';
export { categoryKeys } from './keys';

const categoryStaleTime = 30_000;

export function categoryChildrenOptions(id: string, includeDeleted = false) {
  return queryOptions({
    queryKey: categoryKeys.children(id, includeDeleted),
    queryFn: ({ signal }) => api.getCategoryChildren(id, signal, includeDeleted),
    staleTime: categoryStaleTime,
  });
}

function cachedBranch(
  client: QueryClient,
  id: string,
  includeDeleted = false,
): CategoryBranchResponse | undefined {
  const levels = client
    .getQueryCache()
    .findAll({
      queryKey: categoryKeys.all,
      predicate: (query) =>
        (query.queryKey[2] === 'roots' || query.queryKey[2] === 'children') &&
        (query.queryKey[query.queryKey.length - 1] === true) === includeDeleted &&
        !query.state.isInvalidated,
    })
    .sort((left, right) => right.state.dataUpdatedAt - left.state.dataUpdatedAt)
    .map((query) => ({
      fresh: !query.isStaleByTime(categoryStaleTime),
      level: {
        parent_id: query.queryKey[2] === 'roots' ? null : String(query.queryKey[3]),
        categories: client.getQueryData<CategoriesResponse>(query.queryKey)?.categories ?? [],
      },
    }));
  const path: Category[] = [];
  const branchLevels: CategoryBranchResponse['levels'] = [];
  const seen = new Set<string>();
  let current: string | null = id;

  while (current) {
    // A fresh selected level can reuse known ancestors without reloading the whole branch.
    const level = levels.find(
      (item) =>
        (current !== id || item.fresh) &&
        item.level.categories.some((category) => category.id === current),
    )?.level;
    const category = level?.categories.find((item) => item.id === current);

    if (!level || !category || seen.has(current)) {
      return;
    }

    seen.add(current);
    path.unshift(category);
    branchLevels.unshift(level);
    current = category.parent_id;
  }

  return { path, levels: branchLevels };
}

function branchOptions(
  client: QueryClient,
  id: string,
  refreshSignal?: AbortSignal,
  includeDeleted = false,
) {
  return queryOptions({
    queryKey: categoryKeys.branch(id, includeDeleted),
    staleTime: categoryStaleTime,
    queryFn: async ({ signal }): Promise<CategoryBranchResponse> => {
      // Explicit refresh must read the server even when all cached levels are fresh.
      if (refreshSignal) {
        signal = AbortSignal.any([signal, refreshSignal]);
        signal.throwIfAborted();
      } else {
        const cached = cachedBranch(client, id, includeDeleted);

        if (cached) {
          return cached;
        }

        const pendingLevels = client.getQueryCache().findAll({
          queryKey: categoryKeys.all,
          predicate: (query) =>
            (query.queryKey[2] === 'roots' || query.queryKey[2] === 'children') &&
            (query.queryKey[query.queryKey.length - 1] === true) === includeDeleted &&
            query.state.fetchStatus === 'fetching',
        });
        await Promise.allSettled(pendingLevels.map((query) => query.promise));
        signal.throwIfAborted();
        const loaded = cachedBranch(client, id, includeDeleted);

        if (loaded) {
          return loaded;
        }
      }

      const branch = await api.getCategoryBranch(id, signal, includeDeleted);
      signal.throwIfAborted();
      await client.cancelQueries({
        queryKey: categoryKeys.all,
        predicate: (query) =>
          (query.queryKey[query.queryKey.length - 1] === true) === includeDeleted &&
          branch.levels.some((level) =>
            level.parent_id === null
              ? query.queryKey[2] === 'roots'
              : query.queryKey[2] === 'children' && query.queryKey[3] === level.parent_id,
          ),
      });
      signal.throwIfAborted();

      // Publish complete levels before observers expand the selected path.
      for (const level of branch.levels) {
        client.setQueryData(
          level.parent_id === null
            ? categoryKeys.rootList(includeDeleted)
            : categoryKeys.children(level.parent_id, includeDeleted),
          {
            categories: level.categories,
          },
        );

        for (const category of level.categories) {
          client.setQueryData(categoryKeys.detail(category.id, includeDeleted), category);
        }
      }

      return branch;
    },
  });
}

export function useCategories(
  enabled: MaybeRefOrGetter<boolean> = true,
  includeDeleted: MaybeRefOrGetter<boolean> = false,
) {
  return useQuery({
    queryKey: computed(() => categoryKeys.rootList(toValue(includeDeleted))),
    queryFn: ({ signal }) => api.getCategories(null, signal, toValue(includeDeleted)),
    staleTime: categoryStaleTime,
    enabled: computed(() => toValue(enabled)),
  });
}

export function useCategoryBranch(
  id: MaybeRefOrGetter<string>,
  includeDeleted: MaybeRefOrGetter<boolean> = false,
) {
  const client = useQueryClient();

  return useQuery(
    computed(() => ({
      ...branchOptions(client, toValue(id), undefined, toValue(includeDeleted)),
      enabled: !!toValue(id),
    })),
  );
}

export function useCategory(
  id: MaybeRefOrGetter<string>,
  includeDeleted: MaybeRefOrGetter<boolean> = false,
) {
  const client = useQueryClient();

  return useQuery(
    computed(() => ({
      ...branchOptions(client, toValue(id), undefined, toValue(includeDeleted)),
      enabled: !!toValue(id),
      select: (branch: CategoryBranchResponse) => branch.path[branch.path.length - 1],
    })),
  );
}

export function useCategoryProducts(
  id: MaybeRefOrGetter<string>,
  enabled: MaybeRefOrGetter<boolean> = true,
  includeDeleted: MaybeRefOrGetter<boolean> = false,
) {
  return useQuery({
    queryKey: computed(() => categoryKeys.products(toValue(id), toValue(includeDeleted))),
    queryFn: ({ signal }) => api.getCategoryProducts(toValue(id), signal, toValue(includeDeleted)),
    enabled: computed(() => !!toValue(id) && toValue(enabled)),
  });
}

export function useCategoryPath(
  id: MaybeRefOrGetter<string>,
  includeDeleted: MaybeRefOrGetter<boolean> = false,
) {
  const client = useQueryClient();

  return useQuery(
    computed(() => ({
      ...branchOptions(client, toValue(id), undefined, toValue(includeDeleted)),
      enabled: !!toValue(id),
      select: (branch: CategoryBranchResponse) => branch.path,
    })),
  );
}

export function useCategoryParentPath(
  categoryId: MaybeRefOrGetter<string>,
  parentId: MaybeRefOrGetter<string | null>,
  includeDeleted: MaybeRefOrGetter<boolean> = false,
) {
  const categoryPath = useCategoryPath(categoryId, includeDeleted);
  const ancestorPath = computed(() => {
    const path = categoryPath.data.value;
    const index = path?.findIndex((category) => category.id === toValue(parentId)) ?? -1;

    return index < 0 ? undefined : path?.slice(0, index + 1);
  });
  const draftParentPath = useCategoryPath(() => {
    if ((toValue(categoryId) && !categoryPath.data.value) || ancestorPath.value) {
      return '';
    }

    return toValue(parentId) ?? '';
  });

  return computed(() => ancestorPath.value ?? draftParentPath.data.value);
}

export async function refreshCategoryHierarchy(client: QueryClient) {
  await client.invalidateQueries({ queryKey: categoryKeys.all, refetchType: 'none' });
  await client.refetchQueries({ queryKey: categoryKeys.branches, type: 'active' });
  await client.refetchQueries({
    queryKey: categoryKeys.all,
    type: 'active',
    stale: true,
    predicate: (query) => query.queryKey[2] !== 'branch' && query.queryKey[2] !== 'detail',
  });
}

export interface CategoryTreeRefreshInput {
  id: string;
  path: Category[];
  signal: AbortSignal;
  includeDeleted?: boolean;
}

export async function refreshCategoryTree(
  client: QueryClient,
  { id, path, signal, includeDeleted = false }: CategoryTreeRefreshInput,
): Promise<string | null> {
  signal.throwIfAborted();
  await client.cancelQueries({ queryKey: categoryKeys.all });
  signal.throwIfAborted();
  // Keep snapshots for error recovery, but never rebuild a branch from pre-refresh levels.
  await client.invalidateQueries({ queryKey: categoryKeys.all, refetchType: 'none' });
  signal.throwIfAborted();

  const candidates = id
    ? [
        id,
        ...path
          .filter((category) => category.id !== id)
          .map((category) => category.id)
          .reverse(),
      ]
    : [];

  for (const candidate of candidates) {
    try {
      await client.fetchQuery({
        ...branchOptions(client, candidate, signal, includeDeleted),
        staleTime: 0,
      });
    } catch (error) {
      signal.throwIfAborted();

      if (!axios.isAxiosError(error) || error.response?.status !== 404) {
        throw error;
      }

      // Only a confirmed missing category can be skipped while recovering the old path.
      client.removeQueries({
        queryKey: categoryKeys.detail(candidate, includeDeleted),
        exact: true,
      });
      client.removeQueries({
        queryKey: categoryKeys.products(candidate, includeDeleted),
        exact: true,
      });
      continue;
    }

    signal.throwIfAborted();

    // Share an automatic product read if publishing the branch has already started it.
    // Product errors stay on their query and do not undo a successful hierarchy refresh.
    try {
      await client.fetchQuery({
        queryKey: categoryKeys.products(candidate, includeDeleted),
        queryFn: async ({ signal: querySignal }) => {
          const combined = AbortSignal.any([signal, querySignal]);
          const products = await api.getCategoryProducts(candidate, combined, includeDeleted);
          combined.throwIfAborted();

          return products;
        },
        staleTime: categoryStaleTime,
      });
    } catch {
      signal.throwIfAborted();
    }

    signal.throwIfAborted();

    return candidate;
  }

  await client.fetchQuery({
    queryKey: categoryKeys.rootList(includeDeleted),
    queryFn: async ({ signal: querySignal }) => {
      const combined = AbortSignal.any([signal, querySignal]);
      const roots = await api.getCategories(null, combined, includeDeleted);
      combined.throwIfAborted();

      return roots;
    },
    staleTime: 0,
  });
  signal.throwIfAborted();

  return null;
}

export function useCategoryTreeRefresh() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: (input: CategoryTreeRefreshInput) => refreshCategoryTree(client, input),
  });
}

export function useCategoryMutations() {
  const client = useQueryClient();

  async function saved(category: Category) {
    await client.cancelQueries({ queryKey: categoryKeys.all });
    client.setQueryData(categoryKeys.detail(category.id), category);
    await refreshCategoryHierarchy(client);
  }

  const create = useMutation({
    mutationFn: api.createCategory,
    onSuccess: async (category) => {
      await client.cancelQueries({
        queryKey: categoryKeys.all,
        predicate: (query) => query.queryKey[2] !== 'products',
      });
      // Navigation will load the new branch; the previous selection must not refetch.
      await client.invalidateQueries({
        queryKey: categoryKeys.all,
        predicate: (query) => query.queryKey[2] !== 'products',
        refetchType: 'none',
      });
      client.setQueryData(categoryKeys.detail(category.id), category);
    },
  });
  const update = useMutation({
    mutationFn: ({ id, input }: { id: string; input: CategoryInput }) =>
      api.updateCategory(id, input),
    onSuccess: saved,
  });

  async function invalidateHierarchy() {
    await client.cancelQueries({ queryKey: categoryKeys.all });
    await client.invalidateQueries({ queryKey: categoryKeys.all, refetchType: 'none' });
  }

  const remove = useMutation({ mutationFn: api.deleteCategory, onSuccess: invalidateHierarchy });
  const restore = useMutation({ mutationFn: api.restoreCategory, onSuccess: invalidateHierarchy });
  const purge = useMutation({
    mutationFn: api.deleteCategoryPermanently,
    onSuccess: invalidateHierarchy,
  });

  async function linked(input: api.ProductCategoryInput) {
    await client.cancelQueries({ queryKey: categoryKeys.products(input.categoryId) });
    await client.invalidateQueries({ queryKey: categoryKeys.products(input.categoryId) });
  }

  const attach = useMutation({
    mutationFn: api.attachProduct,
    onSuccess: (_, input) => linked(input),
  });
  const detach = useMutation({
    mutationFn: api.detachProduct,
    onSuccess: (_, input) => linked(input),
  });

  return { create, update, remove, restore, purge, attach, detach };
}
