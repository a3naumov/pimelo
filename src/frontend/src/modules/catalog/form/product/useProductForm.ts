import { computed, ref } from 'vue';
import { useForm } from '@tanstack/vue-form';
import { getApiError, type ApiError } from '@/shared/api/errors';
import { productInputSchema, type ProductInput } from '../../model/product/schemas';

interface ProductFormOptions {
  readonly initialSku?: string;
  readonly requireChanges?: boolean;
  readonly disabled?: boolean;
  readonly onSave: (input: ProductInput) => Promise<ProductInput>;
}

export function useProductForm(options: ProductFormOptions) {
  const error = ref<ApiError | null>(null);
  const savedValues = ref<ProductInput>({ sku: options.initialSku ?? '' });
  const form = useForm({
    defaultValues: savedValues.value,
    validators: { onSubmit: productInputSchema },
    onSubmit: async ({ value }) => {
      if (options.disabled || (options.requireChanges && !hasChanges.value)) {
        return;
      }

      error.value = null;

      try {
        const saved = await options.onSave(productInputSchema.parse(value));
        savedValues.value = saved;
        form.reset(saved);
      } catch (cause) {
        error.value = getApiError(cause);

        if (error.value.status === 409) {
          error.value.fields.sku = error.value.message;
        }
      }
    },
  });
  const isSubmitting = form.useSelector((state) => state.isSubmitting);
  const values = form.useSelector((state) => state.values);
  const hasChanges = computed(() => {
    const parsed = productInputSchema.safeParse(values.value);
    const current = parsed.success ? parsed.data : values.value;

    return current.sku !== savedValues.value.sku;
  });
  const fieldsDisabled = computed(() => options.disabled || isSubmitting.value);
  const submitDisabled = computed(
    () => fieldsDisabled.value || (options.requireChanges && !hasChanges.value),
  );

  function submit() {
    if (submitDisabled.value) {
      return;
    }

    return form.handleSubmit();
  }

  function changeSku(value: string | number, handleChange: (value: string) => void) {
    error.value = null;
    handleChange(String(value));
  }

  return { form, error, isSubmitting, fieldsDisabled, submitDisabled, submit, changeSku };
}
