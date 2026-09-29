import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import { useQueries } from '@tanstack/vue-query';
import {
  categoryChildrenOptions,
  useCategories,
  useCategoryBranch,
} from '../../model/category/queries';
import {
  categoryPath,
  categoryRows,
  previewCategoryMove,
  subtreeIds,
  type CategoryRow,
} from '../../model/category/hierarchy';
import type { Category } from '../../model/category/schemas';

type TreeEntry =
  { kind: 'category'; row: CategoryRow; index: number } | { kind: 'draft'; depth: number };

export function useCategoryTree(
  selected: MaybeRefOrGetter<string>,
  excluded: MaybeRefOrGetter<string> = '',
  preview: MaybeRefOrGetter<Category[] | undefined> = undefined,
  includeDeleted: MaybeRefOrGetter<boolean> = false,
  draftParentPath: MaybeRefOrGetter<Category[] | undefined> = undefined,
) {
  const roots = useCategories(() => !toValue(selected), includeDeleted);
  const branch = useCategoryBranch(selected, includeDeleted);
  const path = { ...branch, data: computed(() => branch.data.value?.path) };
  const displayedPath = computed(() => toValue(preview) ?? path.data.value);
  const expanded = ref(new Set<string>());
  const requestedParent = ref<string>();
  const parentIds = computed(() => [...expanded.value].filter((id) => id !== toValue(excluded)));
  const branches = useQueries({
    queries: computed(() =>
      parentIds.value.map((id) => ({
        ...categoryChildrenOptions(id, toValue(includeDeleted)),
        enabled:
          id === requestedParent.value &&
          (!toValue(selected) || (!path.isPending.value && !path.isFetching.value)),
      })),
    ),
  });
  const serverCategories = computed(() => [
    ...(roots.data.value?.categories ?? []),
    ...branches.value.flatMap((branch) => branch.data?.categories ?? []),
    ...(branch.data.value?.path ?? []),
  ]);
  const categories = computed(() => {
    const loaded = [
      ...(roots.data.value?.categories ?? []),
      ...branches.value.flatMap((branch) => branch.data?.categories ?? []),
    ];
    const draft = toValue(preview);
    const projected =
      draft && branch.data.value ? previewCategoryMove(loaded, draft, branch.data.value) : loaded;
    const creationPath = toValue(draftParentPath);

    if (!creationPath) {
      return projected;
    }

    const parent = creationPath[creationPath.length - 1];
    const byId = new Map(
      [...projected, ...creationPath].map((category) => [category.id, category]),
    );

    if (parent) {
      byId.set(parent.id, { ...parent, has_children: true });
    }

    return [...byId.values()];
  });
  const rows = computed(() =>
    categoryRows(
      categories.value.filter((category) => category.id !== toValue(excluded)),
      expanded.value,
    ),
  );
  const states = computed(
    () => new Map(parentIds.value.map((id, index) => [id, branches.value[index]])),
  );
  const entries = computed<TreeEntry[]>(() => {
    const result: TreeEntry[] = rows.value.map((row, index) => ({ kind: 'category', row, index }));
    const creationPath = toValue(draftParentPath);

    if (!creationPath) {
      return result;
    }

    const parent = creationPath[creationPath.length - 1];
    let index = rows.value.length;

    if (parent) {
      const parentIndex = rows.value.findIndex((row) => row.category.id === parent.id);

      if (parentIndex < 0 || !expanded.value.has(parent.id)) {
        return result;
      }

      index = parentIndex + 1;

      while (
        index < rows.value.length &&
        rows.value[index]!.depth > rows.value[parentIndex]!.depth
      ) {
        index++;
      }
    }

    result.splice(index, 0, { kind: 'draft', depth: creationPath.length });

    return result;
  });

  function collapseToSelected() {
    requestedParent.value = undefined;
    const currentPath = displayedPath.value;
    const creationPath = toValue(draftParentPath);
    expanded.value = new Set(
      creationPath
        ? creationPath.map((category) => category.id)
        : currentPath?.[currentPath.length - 1]?.id === toValue(selected)
          ? currentPath
              .slice(0, -1)
              .map((category) => category.id)
              .filter((id) => id !== toValue(excluded))
          : [],
    );
  }

  watch(
    displayedPath,
    (value, previous) => {
      if (toValue(draftParentPath) || value?.[value.length - 1]?.id !== toValue(selected)) {
        return;
      }

      const moved =
        previous?.[previous.length - 1]?.id === toValue(selected) &&
        previous?.map((category) => category.id).join('/') !==
          value?.map((category) => category.id).join('/');

      // A moved selection must follow its new path even while old cached rows remain visible.
      if (moved || !rows.value.some((row) => row.category.id === toValue(selected))) {
        collapseToSelected();

        const parent = toValue(preview)?.slice(-2, -1)[0];

        if (parent?.has_children) {
          requestedParent.value = parent.id;
        }
      }
    },
    { immediate: true, flush: 'post' },
  );

  watch(
    () => toValue(draftParentPath),
    (value, previous) => {
      if (!value && !previous) {
        return;
      }

      collapseToSelected();
      const parent = value?.[value.length - 1];

      if (parent?.has_children) {
        requestedParent.value = parent.id;
      }
    },
    { immediate: true, flush: 'post' },
  );

  watch([roots.data, branches], () => {
    if (
      path.isFetching.value ||
      roots.isFetching.value ||
      branches.value.some((branch) => branch.isFetching)
    ) {
      return;
    }

    const visible = new Set(rows.value.map((row) => row.category.id));

    for (const id of expanded.value) {
      if (!visible.has(id)) {
        expanded.value.delete(id);
      }
    }
  });

  function toggle(id: string) {
    if (expanded.value.has(id)) {
      requestedParent.value = undefined;

      for (const descendant of subtreeIds(categories.value, id)) {
        expanded.value.delete(descendant);
      }
    } else {
      requestedParent.value = id;
      expanded.value = new Set(
        categoryPath(categories.value, id)
          .map((category) => category.id)
          .filter((item) => item !== toValue(excluded)),
      );
    }
  }

  return {
    roots,
    path,
    rows,
    entries,
    states,
    expanded,
    categories,
    serverCategories,
    toggle,
    collapseToSelected,
  };
}
