import { createAppI18n } from '@/shared/i18n';
import { defineComponent, h, nextTick, reactive } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { AttributeInput } from '../../model/attribute/schemas';
import { useAttributeForm } from './useAttributeForm';

enableAutoUnmount(afterEach);

function setup(options: Parameters<typeof useAttributeForm>[0]) {
  let result!: ReturnType<typeof useAttributeForm>;
  mount(
    defineComponent({
      setup() {
        result = useAttributeForm(options);

        return () => h(result.form.Field, { name: 'name' }, { default: () => h('input') });
      },
    }),
    { global: { plugins: [createAppI18n()] } },
  );

  return result;
}

describe('Attribute form state', () => {
  it('reacts to disabled and requireChanges without replacing the draft on prop updates', async () => {
    const options = reactive({
      initialName: 'ORIGINAL',
      requireChanges: true,
      disabled: false,
      onSave: vi.fn<(input: AttributeInput) => Promise<AttributeInput>>(
        async (input: AttributeInput) => input,
      ),
    });
    const result = setup(options);
    expect(result.submitDisabled.value).toBe(true);
    await result.submit();
    expect(options.onSave).not.toHaveBeenCalled();

    options.requireChanges = false;
    await nextTick();
    expect(result.submitDisabled.value).toBe(false);
    options.requireChanges = true;
    result.form.setFieldValue('name', 'DRAFT');
    options.initialName = 'REFRESHED';
    await nextTick();
    expect(result.form.state.values.name).toBe('DRAFT');
    expect(result.submitDisabled.value).toBe(false);

    options.disabled = true;
    await nextTick();
    expect(result.fieldsDisabled.value).toBe(true);
    await result.submit();
    expect(options.onSave).not.toHaveBeenCalled();
    options.disabled = false;
    result.form.setFieldValue('name', 'ORIGINAL');
    await nextTick();
    expect(result.submitDisabled.value).toBe(true);
  });

  it('keeps each form instance independent', async () => {
    const options = { initialName: 'ORIGINAL', onSave: async (input: AttributeInput) => input };
    const first = setup(options);
    const second = setup(options);
    first.form.setFieldValue('name', 'FIRST');
    await nextTick();
    expect(second.form.state.values.name).toBe('ORIGINAL');
    expect(second.isSubmitting.value).toBe(false);
  });
});

describe('Attribute form submission', () => {
  it('blocks duplicate saves and uses the successful response as the new baseline', async () => {
    let complete!: (input: AttributeInput) => void;
    const onSave = vi.fn<(input: AttributeInput) => Promise<AttributeInput>>(
      () => new Promise<AttributeInput>((resolve) => (complete = resolve)),
    );
    const result = setup({ initialName: 'ORIGINAL', requireChanges: true, onSave });
    result.form.setFieldValue('name', 'DRAFT');
    await nextTick();
    const submission = result.submit();
    await vi.waitFor(() => expect(onSave).toHaveBeenCalledExactlyOnceWith({ name: 'DRAFT' }));
    expect(result.fieldsDisabled.value).toBe(true);
    await result.submit();
    expect(onSave).toHaveBeenCalledTimes(1);

    complete({ name: 'SAVED' });
    await submission;
    await nextTick();
    expect(result.form.state.values.name).toBe('SAVED');
    expect(result.submitDisabled.value).toBe(true);
    expect(result.isSubmitting.value).toBe(false);
    result.form.setFieldValue('name', 'ORIGINAL');
    await nextTick();
    expect(result.submitDisabled.value).toBe(false);
    result.form.setFieldValue('name', 'SAVED');
    await nextTick();
    expect(result.submitDisabled.value).toBe(true);
  });

  it.each([{ name: '' }, { name: 'x'.repeat(256) }])(
    'validates input before calling onSave: %j',
    async (input) => {
      const onSave = vi.fn<(input: AttributeInput) => Promise<AttributeInput>>(
        async (value: AttributeInput) => value,
      );
      const result = setup({ onSave });
      result.form.setFieldValue('name', input.name);
      await nextTick();
      await result.submit();
      expect(onSave).not.toHaveBeenCalled();
    },
  );

  it('preserves the draft and baseline after a validation failure and clears the error on editing', async () => {
    const onSave = vi.fn<(input: AttributeInput) => Promise<AttributeInput>>().mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: { violations: [{ propertyPath: 'name', title: 'Name is invalid.' }] },
      },
    });
    const result = setup({ initialName: 'ORIGINAL', requireChanges: true, onSave });
    result.form.setFieldValue('name', 'DUPLICATE');
    await nextTick();
    await result.submit();
    await nextTick();
    expect(result.form.state.values.name).toBe('DUPLICATE');
    expect(result.error.value?.fields.name).toBe('Name is invalid.');
    expect(result.submitDisabled.value).toBe(false);
    expect(result.isSubmitting.value).toBe(false);

    result.changeName('ORIGINAL', (value) => result.form.setFieldValue('name', value));
    await nextTick();
    expect(result.error.value).toBeNull();
    expect(result.submitDisabled.value).toBe(true);
  });
});
