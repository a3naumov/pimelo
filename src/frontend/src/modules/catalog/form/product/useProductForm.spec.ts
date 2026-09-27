import { createAppI18n } from '@/shared/i18n';
import { defineComponent, h, nextTick, reactive } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ProductInput } from '../../model/product/schemas';
import { useProductForm } from './useProductForm';

enableAutoUnmount(afterEach);

function setup(options: Parameters<typeof useProductForm>[0]) {
  let result!: ReturnType<typeof useProductForm>;
  mount(
    defineComponent({
      setup() {
        result = useProductForm(options);

        return () => h(result.form.Field, { name: 'sku' }, { default: () => h('input') });
      },
    }),
    { global: { plugins: [createAppI18n()] } },
  );

  return result;
}

describe('Product form state', () => {
  it('reacts to disabled and requireChanges without replacing the draft on prop updates', async () => {
    const options = reactive({
      initialSku: 'ORIGINAL',
      requireChanges: true,
      disabled: false,
      onSave: vi.fn<(input: ProductInput) => Promise<ProductInput>>(
        async (input: ProductInput) => input,
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
    result.form.setFieldValue('sku', 'DRAFT');
    options.initialSku = 'REFRESHED';
    await nextTick();
    expect(result.form.state.values.sku).toBe('DRAFT');
    expect(result.submitDisabled.value).toBe(false);

    options.disabled = true;
    await nextTick();
    expect(result.fieldsDisabled.value).toBe(true);
    await result.submit();
    expect(options.onSave).not.toHaveBeenCalled();
    options.disabled = false;
    result.form.setFieldValue('sku', 'ORIGINAL');
    await nextTick();
    expect(result.submitDisabled.value).toBe(true);
  });

  it('keeps each form instance independent', async () => {
    const options = { initialSku: 'ORIGINAL', onSave: async (input: ProductInput) => input };
    const first = setup(options);
    const second = setup(options);
    first.form.setFieldValue('sku', 'FIRST');
    await nextTick();
    expect(second.form.state.values.sku).toBe('ORIGINAL');
    expect(second.isSubmitting.value).toBe(false);
  });
});

describe('Product form submission', () => {
  it('blocks duplicate saves and uses the successful response as the new baseline', async () => {
    let complete!: (input: ProductInput) => void;
    const onSave = vi.fn<(input: ProductInput) => Promise<ProductInput>>(
      () => new Promise<ProductInput>((resolve) => (complete = resolve)),
    );
    const result = setup({ initialSku: 'ORIGINAL', requireChanges: true, onSave });
    result.form.setFieldValue('sku', 'DRAFT');
    await nextTick();
    const submission = result.submit();
    await vi.waitFor(() => expect(onSave).toHaveBeenCalledExactlyOnceWith({ sku: 'DRAFT' }));
    expect(result.fieldsDisabled.value).toBe(true);
    await result.submit();
    expect(onSave).toHaveBeenCalledTimes(1);

    complete({ sku: 'SAVED' });
    await submission;
    await nextTick();
    expect(result.form.state.values.sku).toBe('SAVED');
    expect(result.submitDisabled.value).toBe(true);
    expect(result.isSubmitting.value).toBe(false);
    result.form.setFieldValue('sku', 'ORIGINAL');
    await nextTick();
    expect(result.submitDisabled.value).toBe(false);
    result.form.setFieldValue('sku', 'SAVED');
    await nextTick();
    expect(result.submitDisabled.value).toBe(true);
  });

  it.each([{ sku: '' }, { sku: 'x'.repeat(256) }])(
    'validates input before calling onSave: %j',
    async (input) => {
      const onSave = vi.fn<(input: ProductInput) => Promise<ProductInput>>(
        async (value: ProductInput) => value,
      );
      const result = setup({ onSave });
      result.form.setFieldValue('sku', input.sku);
      await nextTick();
      await result.submit();
      expect(onSave).not.toHaveBeenCalled();
    },
  );

  it('preserves the draft and baseline after a conflict and clears the error on editing', async () => {
    const onSave = vi.fn<(input: ProductInput) => Promise<ProductInput>>().mockRejectedValue({
      isAxiosError: true,
      response: { status: 409, data: { error: 'SKU already exists.' } },
    });
    const result = setup({ initialSku: 'ORIGINAL', requireChanges: true, onSave });
    result.form.setFieldValue('sku', 'DUPLICATE');
    await nextTick();
    await result.submit();
    await nextTick();
    expect(result.form.state.values.sku).toBe('DUPLICATE');
    expect(result.error.value?.fields.sku).toBe('SKU already exists.');
    expect(result.submitDisabled.value).toBe(false);
    expect(result.isSubmitting.value).toBe(false);

    result.changeSku('ORIGINAL', (value) => result.form.setFieldValue('sku', value));
    await nextTick();
    expect(result.error.value).toBeNull();
    expect(result.submitDisabled.value).toBe(true);
  });
});
