import { useTranslation } from '@/shared/i18n';
import { computed, ref } from 'vue';
import { useForm } from '@tanstack/vue-form';
import { getApiError, type ApiError } from '@/shared/api/errors';
import { attributeInputSchema, type AttributeInput } from '../../model/attribute/schemas';

interface AttributeFormOptions {
  readonly initialName?: string;
  readonly requireChanges?: boolean;
  readonly disabled?: boolean;
  readonly onSave: (input: AttributeInput) => Promise<AttributeInput>;
}

export function useAttributeForm(options: AttributeFormOptions) {
  const { t } = useTranslation();
  const error = ref<ApiError | null>(null);
  const savedValues = ref<AttributeInput>({ name: options.initialName ?? '' });
  const form = useForm({
    defaultValues: savedValues.value,
    validators: { onSubmit: attributeInputSchema },
    onSubmit: async ({ value }) => {
      if (options.disabled || (options.requireChanges && !hasChanges.value)) {
        return;
      }

      error.value = null;

      try {
        const saved = await options.onSave(attributeInputSchema.parse(value));
        savedValues.value = saved;
        form.reset(saved);
      } catch (cause) {
        error.value = getApiError(cause, t);
      }
    },
  });
  const isSubmitting = form.useSelector((state) => state.isSubmitting);
  const values = form.useSelector((state) => state.values);
  const hasChanges = computed(() => {
    const parsed = attributeInputSchema.safeParse(values.value);
    const current = parsed.success ? parsed.data : values.value;

    return current.name !== savedValues.value.name;
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

  function changeName(value: string | number, handleChange: (value: string) => void) {
    error.value = null;
    handleChange(String(value));
  }

  function formatValidationError(issue: { message: string } | undefined) {
    const key = issue?.message;

    if (
      key === 'attributes.attribute.validation.required' ||
      key === 'attributes.attribute.validation.tooLong'
    ) {
      return { message: t(key) };
    }

    return { message: t('common.validation.invalid') };
  }

  return {
    formatValidationError,
    form,
    error,
    isSubmitting,
    fieldsDisabled,
    submitDisabled,
    submit,
    changeName,
  };
}
