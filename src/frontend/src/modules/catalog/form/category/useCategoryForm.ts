import { computed, ref, toValue, watch, type MaybeRefOrGetter } from 'vue';
import { refDebounced } from '@vueuse/core';
import { useForm } from '@tanstack/vue-form';
import { useTranslation } from '@/shared/i18n';
import { getApiError, type ApiError } from '@/shared/api/errors';
import {
  categoryFormSchema,
  categoryInputSchema,
  type CategoryInput,
} from '../../model/category/schemas';
import { useCategorySlugPreview } from '../../model/category/queries';

export function useCategoryForm(options: {
  mode?: 'create' | 'edit';
  initialParent: MaybeRefOrGetter<string | null>;
  initialName?: MaybeRefOrGetter<string>;
  initialSlug?: MaybeRefOrGetter<string>;
  categoryId?: string;
  disabled: () => boolean;
  onSave: (input: CategoryInput) => Promise<CategoryInput>;
}) {
  const { t } = useTranslation();
  const error = ref<ApiError | null>(null);
  const saved = ref(false);
  const automatic = ref(options.mode === 'create');
  const acceptingSuffix = ref(false);
  const initial = () => ({
    name: toValue(options.initialName) ?? '',
    slug: toValue(options.initialSlug) ?? '',
    parent_id: toValue(options.initialParent),
  });
  const baseline = ref(initial());
  const form = useForm({
    defaultValues: initial(),
    validators: { onSubmit: categoryFormSchema },
    onSubmit: async ({ value }) => {
      if (options.disabled() || !changed.value || (slugConflict.value && !acceptingSuffix.value)) {
        return;
      }

      error.value = null;
      saved.value = false;

      try {
        const input = categoryInputSchema.parse({
          ...value,
          slug: automatic.value ? null : value.slug,
          allow_slug_suffix: acceptingSuffix.value,
        });
        const result = await options.onSave(input);
        baseline.value = {
          name: result.name,
          slug: result.slug ?? '',
          parent_id: result.parent_id,
        };
        form.reset(baseline.value);
        saved.value = true;
        automatic.value = false;
      } catch (cause) {
        error.value = getApiError(cause, t);

        if (
          error.value.status === 409 &&
          error.value.message.startsWith('This category slug is already in use.')
        ) {
          error.value.fields.slug = error.value.message;
          void preview.refetch();
        }
      } finally {
        acceptingSuffix.value = false;
      }
    },
  });
  const values = form.useSelector((state) => state.values);
  const isSubmitting = form.useSelector((state) => state.isSubmitting);
  const disabled = computed(() => options.disabled() || isSubmitting.value);
  const changed = computed(() =>
    options.mode === 'create'
      ? !saved.value
      : values.value.name.trim() !== baseline.value.name ||
        values.value.slug !== baseline.value.slug ||
        values.value.parent_id !== baseline.value.parent_id,
  );
  const previewInput = refDebounced(
    computed(() => ({
      name: values.value.name.trim(),
      slug: automatic.value ? null : values.value.slug,
      ...(options.categoryId ? { exclude_id: options.categoryId } : {}),
    })),
    300,
  );
  const previewEnabled = computed(
    () =>
      !disabled.value &&
      (options.mode === 'create' || values.value.slug !== baseline.value.slug) &&
      previewInput.value.name.length > 0 &&
      [...previewInput.value.name].length <= 255,
  );
  const preview = useCategorySlugPreview(previewInput, previewEnabled);
  const previewCurrent = computed(
    () =>
      previewInput.value.name === values.value.name.trim() &&
      previewInput.value.slug === (automatic.value ? null : values.value.slug),
  );
  const slugConflict = computed(
    () =>
      !automatic.value &&
      ((previewCurrent.value && preview.data.value?.available === false) ||
        (!!error.value?.fields.slug && error.value.status === 409)),
  );
  const submitDisabled = computed(
    () =>
      disabled.value ||
      !changed.value ||
      !values.value.name.trim() ||
      slugConflict.value ||
      (!automatic.value && !values.value.slug.trim()),
  );
  const fieldErrors = computed(() => {
    const parsed = categoryFormSchema.safeParse(values.value);
    const fields: Record<string, string> = { ...error.value?.fields };

    if (!parsed.success) {
      for (const issue of parsed.error.issues) {
        if (issue.path[0] === 'name') {
          fields.name = t(
            [...values.value.name.trim()].length > 255
              ? 'catalog.category.nameTooLong'
              : 'catalog.category.nameRequired',
          );
        }
      }
    }

    return fields;
  });

  watch([preview.data, preview.isFetching, automatic, previewCurrent], ([value, fetching]) => {
    if (
      value &&
      !fetching &&
      previewEnabled.value &&
      automatic.value &&
      previewCurrent.value &&
      !isSubmitting.value
    ) {
      form.setFieldValue('slug', value.suggested_slug);
    }
  });
  watch(initial, (value) => {
    if (isSubmitting.value) {
      return;
    }

    const pristine = !changed.value;
    baseline.value = value;
    saved.value = false;

    if (pristine) {
      form.reset(value);
      error.value = null;
    }
  });

  function change(field: 'name' | 'slug', value: string) {
    if (disabled.value) {
      return;
    }

    error.value = null;
    saved.value = false;
    acceptingSuffix.value = false;

    if (field === 'slug') {
      automatic.value = options.mode === 'create' && !value.trim();
    }

    form.setFieldValue(field, value);
  }

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

  async function saveWithSuffix() {
    if (
      disabled.value ||
      !changed.value ||
      !slugConflict.value ||
      !values.value.name.trim() ||
      !values.value.slug.trim()
    ) {
      return;
    }

    acceptingSuffix.value = true;

    try {
      await form.handleSubmit();
    } finally {
      acceptingSuffix.value = false;
    }
  }

  return {
    form,
    values,
    isSubmitting,
    disabled,
    submitDisabled,
    error,
    saved,
    select,
    submit,
    change,
    automatic,
    preview,
    previewCurrent,
    slugConflict,
    fieldErrors,
    saveWithSuffix,
  };
}
