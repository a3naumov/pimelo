import { nextTick, onBeforeUnmount, ref, watch, type ComputedRef } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCategoryTreeRefresh } from '../../model/category/queries';
import type { Category } from '../../model/category/schemas';

export function useCategoryPageRefresh(options: {
  id: ComputedRef<string>;
  includeDeleted: ComputedRef<boolean>;
  path: ComputedRef<Category[]>;
  pending: ComputedRef<boolean>;
  collapseTree: () => void;
  treeRefresh: ReturnType<typeof useCategoryTreeRefresh>;
}) {
  const route = useRoute();
  const router = useRouter();
  const treeRefresh = options.treeRefresh;
  const recoveredSelection = ref(false);
  let refreshController: AbortController | undefined;

  function cancel() {
    refreshController?.abort();
    refreshController = undefined;
    treeRefresh.reset();
    recoveredSelection.value = false;
  }

  watch(options.id, cancel);
  watch(options.includeDeleted, () => {
    cancel();
    options.collapseTree();
  });
  onBeforeUnmount(cancel);

  async function refresh() {
    if (options.pending.value) {
      return;
    }

    const selectedId = options.id.value;
    const previousPath = [...options.path.value];
    const controller = new AbortController();
    refreshController = controller;
    recoveredSelection.value = false;
    options.collapseTree();
    await nextTick();

    try {
      const selected = await treeRefresh.mutateAsync({
        id: selectedId,
        path: previousPath,
        signal: controller.signal,
        includeDeleted: options.includeDeleted.value,
      });

      if (controller.signal.aborted || options.id.value !== selectedId) {
        return;
      }

      if (selected !== (selectedId || null)) {
        await router.replace(
          selected
            ? { name: 'catalog.categories.detail', query: route.query, params: { id: selected } }
            : { name: 'catalog.categories', query: route.query },
        );
        await nextTick();
        options.collapseTree();
        recoveredSelection.value = options.id.value === (selected ?? '');
      }
    } catch {
      // Preserve cached data and drafts so the refresh error can be retried.
    } finally {
      if (refreshController === controller) {
        refreshController = undefined;
      }
    }
  }

  return { treeRefresh, recoveredSelection, refresh };
}
