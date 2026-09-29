import { computed, ref, watch, type ComputedRef } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { Category, CategoryInput } from '../../model/category/schemas';
import { useCategory, useCategoryMutations } from '../../model/category/queries';

type Mutations = ReturnType<typeof useCategoryMutations>;

export function useCategoryPageEditor(options: {
  id: ComputedRef<string>;
  includeDeleted: ComputedRef<boolean>;
  path: ComputedRef<Category[]>;
  detail: ReturnType<typeof useCategory>;
  pending: ComputedRef<boolean>;
  create: Mutations['create'];
  update: Mutations['update'];
  remove: Mutations['remove'];
  restore: Mutations['restore'];
  purge: Mutations['purge'];
  collapseTree: () => void;
  refresh: () => Promise<void>;
}) {
  const route = useRoute();
  const router = useRouter();
  const draft = ref<{ parentId: string | null } | null>(null);
  const draftParentPath = ref<Category[]>();
  const previewPath = ref<Category[]>();
  const draggedParent = ref<{ categoryId: string; parent_id: string | null }>();
  const deleting = ref(false);
  const restoring = ref(false);
  const isDeleted = computed(() => !!options.detail.data.value?.deleted_at);
  const deletion = computed(() => (isDeleted.value ? options.purge : options.remove));
  const displayedPath = computed(() => previewPath.value ?? options.path.value);
  const canCreate = computed(
    () =>
      !options.pending.value &&
      !draft.value &&
      !isDeleted.value &&
      (!options.id.value || (!!options.detail.data.value && !options.detail.error.value)),
  );

  watch(options.id, () => {
    if (draggedParent.value?.categoryId !== options.id.value) {
      draggedParent.value = undefined;
    }

    previewPath.value = undefined;
    draft.value = null;
    deleting.value = false;
    restoring.value = false;
  });

  function select(categoryId: string) {
    if (options.pending.value) {
      return;
    }

    draft.value = null;
    void router.push({
      name: 'catalog.categories.detail',
      query: route.query,
      params: { id: categoryId },
    });
  }

  function startCreating() {
    if (!canCreate.value) {
      return;
    }

    options.create.reset();
    draggedParent.value = undefined;
    previewPath.value = undefined;
    draft.value = { parentId: options.detail.data.value?.parent_id ?? null };
    draftParentPath.value = options.path.value.slice(0, -1);
  }

  async function moveCategory(categoryId: string, parentId: string | null) {
    if (options.pending.value || draft.value) {
      return;
    }

    draggedParent.value = { categoryId, parent_id: parentId };
    await router.push({
      name: 'catalog.categories.detail',
      query: route.query,
      params: { id: categoryId },
    });
  }

  async function saveNewCategory(input: CategoryInput): Promise<Category> {
    const category = await options.create.mutateAsync(input);
    options.collapseTree();
    await router.push({
      name: 'catalog.categories.detail',
      query: route.query,
      params: { id: category.id },
    });

    return category;
  }

  async function save(input: CategoryInput): Promise<Category> {
    return options.update.mutateAsync({ id: options.id.value, input });
  }

  async function restoreSelected() {
    if (options.pending.value || !isDeleted.value) {
      return;
    }

    try {
      await options.restore.mutateAsync(options.id.value);
      restoring.value = false;
      await options.refresh();
    } catch {
      // Keep the confirmation open for retry.
    }
  }

  async function deleteSelected() {
    if (!options.detail.data.value || options.pending.value) {
      return;
    }

    const parent = options.detail.data.value.parent_id;
    const wasDeleted = isDeleted.value;

    try {
      await deletion.value.mutateAsync(options.id.value);
      deleting.value = false;

      if (options.includeDeleted.value && !wasDeleted) {
        await options.refresh();

        return;
      }

      await router.push(
        parent
          ? { name: 'catalog.categories.detail', query: route.query, params: { id: parent } }
          : { name: 'catalog.categories', query: route.query },
      );
    } catch {
      // Keep the confirmation open for retry.
    }
  }

  return {
    draft,
    draftParentPath,
    previewPath,
    displayedPath,
    draggedParent,
    deleting,
    restoring,
    isDeleted,
    deletion,
    canCreate,
    select,
    startCreating,
    moveCategory,
    saveNewCategory,
    save,
    restoreSelected,
    deleteSelected,
  };
}
