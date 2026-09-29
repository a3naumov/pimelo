import { effectScope, nextTick, ref } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { useCategoryDrag } from './useCategoryDrag';

const root = { id: 'root', parent_id: null, has_children: true, deleted_at: null };
const child = { ...root, id: 'child', parent_id: root.id };
const leaf = { ...child, id: 'leaf', parent_id: child.id, has_children: false };
const other = { ...root, id: 'other', has_children: false };
const deleted = { ...other, id: 'deleted', deleted_at: '2026-09-28T12:00:00Z' };
const categories = [root, child, leaf, other, deleted];

describe('Drag hover expansion', () => {
  afterEach(() => {
    vi.clearAllTimers();
    vi.useRealTimers();
  });

  function setup() {
    vi.useFakeTimers();
    const expanded = new Set<string>();
    const expand = vi.fn<(id: string) => void>((id) => {
      expanded.add(id);
    });
    const enabled = ref(true);
    const move = vi.fn<(id: string, parentId: string | null) => void>();
    const scope = effectScope();
    const drag = scope.run(() =>
      useCategoryDrag(categories, enabled, move, categories, {
        isExpanded: (id) => expanded.has(id),
        expand,
      }),
    )!;
    drag.dragged.value = other.id;
    const event = { preventDefault: vi.fn<() => void>(), dataTransfer: null };

    return { drag, expand, enabled, move, scope, event, expanded };
  }

  it('opens once after sustained hover without restarting on repeated dragover events', () => {
    const { drag, expand, event, scope } = setup();
    drag.over(event, root.id);
    vi.advanceTimersByTime(300);
    drag.over(event, root.id);
    vi.advanceTimersByTime(299);
    expect(expand).not.toHaveBeenCalled();
    vi.advanceTimersByTime(1);
    expect(expand).toHaveBeenCalledExactlyOnceWith(root.id);
    drag.over(event, root.id);
    vi.advanceTimersByTime(1000);
    expect(expand).toHaveBeenCalledTimes(1);
    scope.stop();
  });

  it('cancels the old target on leaving or moving to another row', () => {
    const { drag, expand, event, scope } = setup();
    drag.over(event, root.id);
    vi.advanceTimersByTime(300);
    drag.leave({ currentTarget: null, relatedTarget: null });
    vi.advanceTimersByTime(600);
    expect(expand).not.toHaveBeenCalled();
    drag.over(event, root.id);
    vi.advanceTimersByTime(300);
    drag.over(event, child.id);
    vi.advanceTimersByTime(600);
    expect(expand).toHaveBeenCalledExactlyOnceWith(child.id);
    scope.stop();
  });

  it.each(['end', 'drop', 'disable', 'dispose'] as const)(
    'cancels delayed expansion on %s',
    async (action) => {
      const { drag, expand, event, scope, enabled } = setup();
      drag.over(event, root.id);

      if (action === 'end') {
        drag.end();
      } else if (action === 'drop') {
        drag.drop(event, root.id);
      } else if (action === 'disable') {
        enabled.value = false;
        await nextTick();
      } else {
        scope.stop();
      }

      vi.advanceTimersByTime(600);
      expect(expand).not.toHaveBeenCalled();
      scope.stop();
    },
  );

  it('opens an unchanged parent for navigation but skips leaves and invalid targets', () => {
    const { drag, expand, event, scope } = setup();
    drag.dragged.value = child.id;
    expect(drag.canDrop(root.id)).toBe(false);
    drag.over(event, root.id);
    vi.advanceTimersByTime(600);
    expect(expand).toHaveBeenCalledExactlyOnceWith(root.id);

    for (const id of [child.id, leaf.id, deleted.id, other.id, 'missing']) {
      drag.over(event, id);
      vi.advanceTimersByTime(600);
    }

    expect(expand).toHaveBeenCalledTimes(1);
    scope.stop();
  });
});

describe('Category drag targets', () => {
  it('does not treat an unsaved subtree move as a persisted change when checking cycles', () => {
    const preview = categories.map((category) =>
      category.id === child.id ? { ...category, parent_id: other.id } : category,
    );
    const drag = useCategoryDrag(preview, true, vi.fn(), categories);
    drag.dragged.value = root.id;
    expect(drag.canDrop(child.id)).toBe(false);
    expect(drag.canDrop(leaf.id)).toBe(false);
    expect(drag.canDrop(other.id)).toBe(true);
  });

  it('allows reparenting and moving to roots without allowing cycles or unchanged parents', () => {
    const drag = useCategoryDrag(categories, true, vi.fn());
    drag.dragged.value = child.id;
    expect(drag.canDrop(other.id)).toBe(true);
    expect(drag.canDrop(null)).toBe(true);
    expect(drag.canDrop(root.id)).toBe(false);
    expect(drag.canDrop(child.id)).toBe(false);
    expect(drag.canDrop(leaf.id)).toBe(false);
    drag.dragged.value = root.id;
    expect(drag.canDrop(null)).toBe(false);
    expect(drag.canDrop(leaf.id)).toBe(false);
  });

  it('rejects deleted, missing, external and disabled sources or targets', () => {
    const drag = useCategoryDrag(categories, true, vi.fn());
    expect(drag.canDrop(other.id)).toBe(false);
    drag.dragged.value = child.id;
    expect(drag.canDrop(deleted.id)).toBe(false);
    expect(drag.canDrop('missing')).toBe(false);
    expect(drag.canDrag(deleted)).toBe(false);
    drag.dragged.value = deleted.id;
    expect(drag.canDrop(other.id)).toBe(false);
    const disabled = useCategoryDrag(categories, false, vi.fn());
    disabled.dragged.value = child.id;
    expect(disabled.canDrag(child)).toBe(false);
    expect(disabled.canDrop(other.id)).toBe(false);
  });
});
