import { defineComponent, h, nextTick, ref, type Ref } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createAppI18n } from '@/shared/i18n';
import { useCategoryForm } from './useCategoryForm';
import type { CategoryInput } from '../../model/category/schemas';
const parent = '0195f582-9762-7c2a-9228-4060489e06d8';
enableAutoUnmount(afterEach);

function setup(
  onSave: (input: CategoryInput) => Promise<CategoryInput>,
  initialParent: Ref<string | null> = ref(null),
  mode: 'create' | 'edit' = 'edit',
) {
  let result!: ReturnType<typeof useCategoryForm>;
  mount(
    defineComponent({
      setup() {
        result = useCategoryForm({ initialParent, mode, disabled: () => false, onSave });

        return () => h('div');
      },
    }),
    { global: { plugins: [createAppI18n()] } },
  );

  return result;
}

describe('Category parent form', () => {
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
        expect(save).toHaveBeenCalledExactlyOnceWith({ parent_id: initialParent }),
      );
      await result.submit();
      expect(save).toHaveBeenCalledTimes(1);
      resolve({ parent_id: initialParent });
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
    resolve({ parent_id: parent });
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
    save.mockResolvedValue({ parent_id: parent });
    await result.submit();
    expect(result.error.value).toBeNull();
    expect(result.saved.value).toBe(true);
  });
});
