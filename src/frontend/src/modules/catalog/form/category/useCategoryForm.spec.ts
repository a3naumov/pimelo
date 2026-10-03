import { defineComponent, h, nextTick, ref, type Ref } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import * as api from '../../api/category/categories';
import { createAppI18n } from '@/shared/i18n';
import { useCategoryForm } from './useCategoryForm';
import type { CategoryInput } from '../../model/category/schemas';
const parent = '0195f582-9762-7c2a-9228-4060489e06d8';
enableAutoUnmount(afterEach);
const clients: QueryClient[] = [];
beforeEach(() => {
  vi.spyOn(api, 'previewCategorySlug').mockResolvedValue({
    slug: 'category',
    available: true,
    suggested_slug: 'category',
  });
});
afterEach(() => {
  clients.splice(0).forEach((client) => client.clear());
  vi.restoreAllMocks();
});

function setup(
  onSave: (input: CategoryInput) => Promise<CategoryInput>,
  initialParent: Ref<string | null> = ref(null),
  mode: 'create' | 'edit' = 'edit',
) {
  let result!: ReturnType<typeof useCategoryForm>;
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  clients.push(client);
  mount(
    defineComponent({
      setup() {
        result = useCategoryForm({
          initialParent,
          initialName: 'Category',
          initialSlug: mode === 'create' ? '' : 'category',
          mode,
          disabled: () => false,
          onSave,
        });

        return () => h('div');
      },
    }),
    { global: { plugins: [createAppI18n(), [VueQueryPlugin, { queryClient: client }]] } },
  );

  return result;
}

describe('Category parent form', () => {
  it('restores the automatic preview after clearing an identical custom slug', async () => {
    const result = setup(vi.fn(), ref(null), 'create');
    await vi.waitFor(() => expect(result.values.value.slug).toBe('category'));
    result.change('slug', 'category');
    await vi.waitFor(() => expect(result.previewCurrent.value).toBe(true));
    result.change('slug', '');
    await vi.waitFor(() => expect(result.values.value.slug).toBe('category'));
    expect(result.automatic.value).toBe(true);
  });
  it('preserves the saved slug when renaming and submits all fields together', async () => {
    const save = vi
      .fn<(input: CategoryInput) => Promise<CategoryInput>>()
      .mockImplementation(async (input) => input);
    const result = setup(save);
    result.change('name', 'Renamed category');
    result.select(parent);
    await nextTick();
    await result.submit();
    expect(save).toHaveBeenCalledExactlyOnceWith({
      name: 'Renamed category',
      slug: 'category',
      parent_id: parent,
      allow_slug_suffix: false,
    });
    expect(result.submitDisabled.value).toBe(true);
  });

  it('shows an automatic preview without submitting it as a custom slug', async () => {
    vi.mocked(api.previewCategorySlug).mockResolvedValue({
      slug: 'category',
      available: false,
      suggested_slug: 'category-1',
    });
    const save = vi
      .fn<(input: CategoryInput) => Promise<CategoryInput>>()
      .mockResolvedValue({ name: 'Category', slug: 'category-1', parent_id: null });
    const result = setup(save, ref(null), 'create');
    await vi.waitFor(() => expect(result.values.value.slug).toBe('category-1'));
    expect(result.automatic.value).toBe(true);
    expect(result.slugConflict.value).toBe(false);
    await result.submit();
    expect(save).toHaveBeenCalledExactlyOnceWith({
      name: 'Category',
      slug: null,
      parent_id: null,
      allow_slug_suffix: false,
    });
  });

  it('requires explicit consent for a custom slug conflict and preserves the draft', async () => {
    vi.mocked(api.previewCategorySlug).mockResolvedValue({
      slug: 'taken',
      available: false,
      suggested_slug: 'taken-1',
    });
    const save = vi
      .fn<(input: CategoryInput) => Promise<CategoryInput>>()
      .mockResolvedValue({ name: 'Category', slug: 'taken-1', parent_id: parent });
    const result = setup(save);
    result.change('slug', 'TAKEN');
    result.select(parent);
    await vi.waitFor(() => expect(result.slugConflict.value).toBe(true));
    await result.submit();
    expect(save).not.toHaveBeenCalled();
    expect(result.values.value.slug).toBe('TAKEN');
    await result.saveWithSuffix();
    expect(save).toHaveBeenCalledExactlyOnceWith({
      name: 'Category',
      slug: 'TAKEN',
      parent_id: parent,
      allow_slug_suffix: true,
    });
    expect(result.values.value.slug).toBe('taken-1');
  });

  it('never overwrites a manual slug with a late automatic preview', async () => {
    let resolve!: (value: Awaited<ReturnType<typeof api.previewCategorySlug>>) => void;
    vi.mocked(api.previewCategorySlug).mockImplementation(
      () =>
        new Promise((done) => {
          resolve = done;
        }),
    );
    const result = setup(vi.fn(), ref(null), 'create');
    await vi.waitFor(() => expect(api.previewCategorySlug).toHaveBeenCalled());
    result.change('slug', 'my-custom-slug');
    resolve({ slug: 'category', available: true, suggested_slug: 'category' });
    await nextTick();
    expect(result.values.value.slug).toBe('my-custom-slug');
    expect(result.automatic.value).toBe(false);
    result.change('slug', '');
    expect(result.automatic.value).toBe(true);
  });

  it.each([null, parent])(
    'creates with the unchanged default parent %s only on submit',
    async (initialParent) => {
      let resolve!: (value: CategoryInput) => void;
      const save = vi.fn<(input: CategoryInput) => Promise<CategoryInput>>(
        () =>
          new Promise((done) => {
            resolve = done;
          }),
      );
      const result = setup(save, ref(initialParent), 'create');
      await nextTick();
      expect(save).not.toHaveBeenCalled();
      expect(result.submitDisabled.value).toBe(false);
      const saving = result.submit();
      await vi.waitFor(() =>
        expect(save).toHaveBeenCalledExactlyOnceWith({
          name: 'Category',
          slug: null,
          parent_id: initialParent,
          allow_slug_suffix: false,
        }),
      );
      await result.submit();
      expect(save).toHaveBeenCalledTimes(1);
      resolve({ name: 'Category', slug: 'category', parent_id: initialParent });
      await saving;
      await nextTick();
      await result.submit();
      expect(save).toHaveBeenCalledTimes(1);
    },
  );

  it('updates an unchanged parent field when the server category moves', async () => {
    const current = ref<string | null>(null);
    const result = setup(vi.fn(), current);
    current.value = parent;
    await nextTick();
    expect(result.values.value.parent_id).toBe(parent);
    expect(result.submitDisabled.value).toBe(true);
    current.value = null;
    await nextTick();
    expect(result.values.value.parent_id).toBeNull();
    expect(result.submitDisabled.value).toBe(true);
  });

  it('preserves an unsaved parent draft while updating the server baseline', async () => {
    const draft = '0195f582-9762-7c2a-9228-4060489e06d9';
    const current = ref<string | null>(null);
    const result = setup(vi.fn(), current);
    result.select(draft);
    await nextTick();
    current.value = parent;
    await nextTick();
    expect(result.values.value.parent_id).toBe(draft);
    expect(result.submitDisabled.value).toBe(false);
    result.select(parent);
    await nextTick();
    expect(result.submitDisabled.value).toBe(true);
  });

  it('saves only changed values, blocks duplicate requests and updates its baseline', async () => {
    let resolve!: (input: CategoryInput) => void;
    const save = vi.fn<(input: CategoryInput) => Promise<CategoryInput>>(
      () =>
        new Promise((done) => {
          resolve = done;
        }),
    );
    const result = setup(save);
    await result.submit();
    expect(save).not.toHaveBeenCalled();
    result.select(parent);
    await nextTick();
    const saving = result.submit();
    await vi.waitFor(() => expect(save).toHaveBeenCalledTimes(1));
    await result.submit();
    expect(save).toHaveBeenCalledTimes(1);
    resolve({ name: 'Category', slug: 'category', parent_id: parent });
    await saving;
    await nextTick();
    expect(result.submitDisabled.value).toBe(true);
    result.select(null);
    await nextTick();
    expect(result.submitDisabled.value).toBe(false);
    result.select(parent);
    await nextTick();
    expect(result.submitDisabled.value).toBe(true);
  });
  it('preserves the draft on failure and supports retry', async () => {
    const save = vi.fn<(input: CategoryInput) => Promise<CategoryInput>>().mockRejectedValue({
      isAxiosError: true,
      response: { status: 409, data: { error: 'Cycle detected' } },
    });
    const result = setup(save);
    result.select(parent);
    await nextTick();
    await result.submit();
    expect(result.values.value.parent_id).toBe(parent);
    expect(result.error.value?.message).toBe('Cycle detected');
    save.mockResolvedValue({ name: 'Category', slug: 'category', parent_id: parent });
    await result.submit();
    expect(result.error.value).toBeNull();
    expect(result.saved.value).toBe(true);
  });
});
