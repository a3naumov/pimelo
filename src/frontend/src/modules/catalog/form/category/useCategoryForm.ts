import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import { useForm } from '@tanstack/vue-form';
import { useTranslation } from '@/shared/i18n';
import { getApiError, type ApiError } from '@/shared/api/errors';
import { categoryInputSchema, type CategoryInput } from '../../model/category/schemas';

export function useCategoryForm(options: {
  mode?: 'create' | 'edit';
  initialParent: MaybeRefOrGetter<string | null>;
  disabled: () => boolean;
  onSave: (input: CategoryInput) => Promise<CategoryInput>;
}) {
  const { t } = useTranslation();
  const error = ref<ApiError | null>(null);
  const saved = ref(false);
  const baseline = ref(toValue(options.initialParent));
  const form = useForm({
    defaultValues: { parent_id: baseline.value },
    validators: { onSubmit: categoryInputSchema },
    onSubmit: async ({ value }) => {
      if (
        options.disabled() ||
        (options.mode === 'create' ? saved.value : value.parent_id === baseline.value)
      ) {
        return;
      }

      error.value = null;
      saved.value = false;

      try {
        const result = await options.onSave(categoryInputSchema.parse(value));
        baseline.value = result.parent_id;
        form.reset(result);
        saved.value = true;
      } catch (cause) {
        error.value = getApiError(cause, t);
      }
    },
  });
  const values = form.useSelector((state) => state.values);
  const isSubmitting = form.useSelector((state) => state.isSubmitting);
  const disabled = computed(() => options.disabled() || isSubmitting.value);
  const submitDisabled = computed(
    () =>
      disabled.value ||
      (options.mode === 'create' ? saved.value : values.value.parent_id === baseline.value),
  );

  watch(
    () => toValue(options.initialParent),
    (parent) => {
      if (isSubmitting.value) {
        return;
      }

      const pristine = values.value.parent_id === baseline.value;
      baseline.value = parent;
      saved.value = false;

      if (pristine) {
        form.reset({ parent_id: parent });
        error.value = null;
      }
    },
  );

  function select(parent: string | null) {
    if (disabled.value) {
      return;
    }

    error.value = null;
    saved.value = false;
    form.setFieldValue('parent_id', parent);
  }

  function submit() {
    if (submitDisabled.value) {
      return;
    }

    return form.handleSubmit();
  }

  return { form, values, isSubmitting, disabled, submitDisabled, error, saved, select, submit };
}
