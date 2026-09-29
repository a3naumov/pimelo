import { getCurrentScope, onScopeDispose, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import { subtreeIds } from '../../model/category/hierarchy';
import type { Category } from '../../model/category/schemas';

export function useCategoryDrag(
  categories: MaybeRefOrGetter<Category[]>,
  enabled: MaybeRefOrGetter<boolean>,
  move: (id: string, parentId: string | null) => void,
  serverCategories: MaybeRefOrGetter<Category[]> = categories,
  expansion?: { isExpanded: (id: string) => boolean; expand: (id: string) => void },
) {
  const dragged = ref<string>();
  const target = ref<string | null>();
  let hoverTimer: ReturnType<typeof setTimeout> | undefined;
  let hovered: string | undefined;
  let sourceSnapshot: Category | undefined;
  let excludedTargets = new Set<string>();

  function canDrag(category: Category) {
    return toValue(enabled) && !category.deleted_at;
  }

  function canEnter(parentId: string | null) {
    const loaded = toValue(categories);
    const source = loaded.find((category) => category.id === dragged.value) ?? sourceSnapshot;

    if (!dragged.value || !source || !canDrag(source)) {
      return false;
    }

    if (parentId === null) {
      return true;
    }

    const parent = loaded.find((category) => category.id === parentId);

    return (
      !!parent &&
      !parent.deleted_at &&
      !excludedTargets.has(parentId) &&
      !subtreeIds(loaded, source.id).has(parentId) &&
      !subtreeIds(toValue(serverCategories), source.id).has(parentId)
    );
  }

  function canDrop(parentId: string | null) {
    const source =
      toValue(categories).find((category) => category.id === dragged.value) ?? sourceSnapshot;

    return canEnter(parentId) && source?.parent_id !== parentId;
  }

  function cancelHover() {
    clearTimeout(hoverTimer);
    hoverTimer = undefined;
    hovered = undefined;
  }

  function hover(parentId: string | null) {
    if (parentId === hovered) {
      return;
    }

    cancelHover();
    const parent = toValue(categories).find((category) => category.id === parentId);

    if (!parent || !parent.has_children || !expansion || expansion.isExpanded(parent.id)) {
      return;
    }

    hovered = parent.id;
    hoverTimer = setTimeout(() => {
      hoverTimer = undefined;

      if (canEnter(parent.id) && !expansion.isExpanded(parent.id)) {
        expansion.expand(parent.id);
      }
    }, 600);
  }

  function end() {
    cancelHover();
    dragged.value = undefined;
    target.value = undefined;
    sourceSnapshot = undefined;
    excludedTargets.clear();
    window.removeEventListener('dragend', end);
    window.removeEventListener('drop', end);
    window.removeEventListener('keydown', cancelOnEscape);
  }

  function cancelOnEscape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
      end();
    }
  }

  function start(event: DragEvent, category: Category) {
    end();

    if (!canDrag(category) || !event.dataTransfer) {
      event.preventDefault();

      return;
    }

    dragged.value = category.id;
    sourceSnapshot = { ...category };
    excludedTargets = new Set([
      ...subtreeIds(toValue(categories), category.id),
      ...subtreeIds(toValue(serverCategories), category.id),
    ]);
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', category.id);
    window.addEventListener('dragend', end);
    window.addEventListener('drop', end);
    window.addEventListener('keydown', cancelOnEscape);
  }

  function over(
    event: Pick<DragEvent, 'preventDefault' | 'dataTransfer'>,
    parentId: string | null,
  ) {
    if (!canEnter(parentId)) {
      cancelHover();
      target.value = undefined;

      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'none';
      }

      return;
    }

    event.preventDefault();
    hover(parentId);
    target.value = canDrop(parentId) ? parentId : undefined;

    if (event.dataTransfer) {
      event.dataTransfer.dropEffect = canDrop(parentId) ? 'move' : 'none';
    }
  }

  function leave(event: Pick<DragEvent, 'currentTarget' | 'relatedTarget'>) {
    if (
      event.currentTarget instanceof Node &&
      event.relatedTarget instanceof Node &&
      event.currentTarget.contains(event.relatedTarget)
    ) {
      return;
    }

    target.value = undefined;
    cancelHover();
  }

  function drop(event: Pick<DragEvent, 'preventDefault'>, parentId: string | null) {
    event.preventDefault();

    if (dragged.value && canDrop(parentId)) {
      move(dragged.value, parentId);
    }

    end();
  }

  watch(() => toValue(enabled), end);

  if (getCurrentScope()) {
    onScopeDispose(end);
  }

  return { dragged, target, canDrag, canDrop, start, over, leave, drop, end };
}
