<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { FolderTreeIcon } from '@lucide/vue';
import { useTranslation } from '@/shared/i18n';
import { Button } from '@/shared/ui/button';
import { Field, FieldGroup, FieldLabel, FieldDescription, FieldError } from '@/shared/ui/field';
import { Alert, AlertTitle, AlertDescription } from '@/shared/ui/alert';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/shared/ui/sheet';
import { Spinner } from '@/shared/ui/spinner';
import { Input } from '@/shared/ui/input';
import { Badge } from '@/shared/ui/badge';
import type { Category, CategoryInput } from '../../model/category/schemas';
import { useCategoryParentPath } from '../../model/category/queries';
import { useCategoryForm } from '../../form/category/useCategoryForm';
import CategoryTree from './CategoryTree.vue';

const props = defineProps<{
  category?: Category;
  initialParent?: string | null;
  contextId?: string;
  pending: boolean;
  includeDeleted?: boolean;
  parentSelection?: Pick<CategoryInput, 'parent_id'>;
  onSave: (input: CategoryInput) => Promise<Category>;
}>();
const emit = defineEmits<{
  cancel: [];
  preview: [path: Category[] | undefined];
  draftParent: [path: Category[] | undefined];
}>();
const { t } = useTranslation();
const open = ref(false);
const {
  values,
  error,
  saved,
  disabled,
  submitDisabled,
  isSubmitting,
  select,
  submit,
  change,
  automatic,
  preview,
  previewCurrent,
  slugConflict,
  fieldErrors,
  saveWithSuffix,
} = useCategoryForm({
  mode: props.category ? 'edit' : 'create',
  initialParent: () => props.category?.parent_id ?? props.initialParent ?? null,
  initialName: () => props.category?.name ?? '',
  initialSlug: () => props.category?.slug ?? '',
  categoryId: props.category?.id,
  disabled: () => props.pending,
  onSave: props.onSave,
});
const parentPath = useCategoryParentPath(
  () => props.category?.id ?? props.contextId ?? '',
  () => values.value.parent_id,
  () => props.includeDeleted ?? false,
);
watch(
  () => props.parentSelection,
  (value) => {
    if (value) {
      select(value.parent_id);
    }
  },
  { immediate: true },
);
const path = computed(() =>
  values.value.parent_id
    ? parentPath.value?.map((item) => item.name).join(' / ') || values.value.parent_id
    : t('catalog.category.root'),
);
const previewPath = computed(() => {
  const category = props.category;
  const parent = values.value.parent_id;

  if (!category || parent === category.parent_id || (parent && !parentPath.value)) {
    return undefined;
  }

  return [...(parentPath.value ?? []), { ...category, parent_id: parent }];
});
watch(previewPath, (value) => emit('preview', value), { immediate: true });
watch(
  () => (values.value.parent_id ? parentPath.value : []),
  (value) => {
    if (!props.category) {
      emit('draftParent', value);
    }
  },
  { immediate: true },
);

function choose(id: string | null) {
  select(id);
  open.value = false;
}
</script>
<template>
  <form class="flex flex-col gap-4" @submit.prevent="submit">
    <Alert v-if="error" variant="destructive"
      ><AlertTitle>{{ t('catalog.category.error') }}</AlertTitle
      ><AlertDescription>{{ error.message }}</AlertDescription></Alert
    >
    <FieldGroup>
      <Field :data-invalid="!!fieldErrors.name" :data-disabled="disabled">
        <FieldLabel for="category-name">{{ t('catalog.category.name') }}</FieldLabel>
        <Input
          id="category-name"
          :model-value="values.name"
          :disabled="disabled"
          :aria-invalid="!!fieldErrors.name"
          @update:model-value="change('name', String($event))"
        />
        <FieldError v-if="fieldErrors.name">{{ fieldErrors.name }}</FieldError>
      </Field>
      <Field :data-invalid="slugConflict || !!fieldErrors.slug" :data-disabled="disabled">
        <FieldLabel for="category-slug"
          >{{ t('catalog.category.slug') }}
          <Badge v-if="automatic" variant="secondary">{{
            t('catalog.category.automatic')
          }}</Badge></FieldLabel
        >
        <Input
          id="category-slug"
          :model-value="values.slug"
          :disabled="disabled"
          :aria-invalid="slugConflict || !!fieldErrors.slug"
          @update:model-value="change('slug', String($event))"
        />
        <FieldDescription>{{
          t(automatic ? 'catalog.category.slugAutomaticHelp' : 'catalog.category.slugCustomHelp')
        }}</FieldDescription>
        <FieldDescription
          v-if="
            !automatic &&
            previewCurrent &&
            preview.data.value &&
            values.slug !== preview.data.value.slug
          "
          >{{
            t('catalog.category.slugNormalized', { slug: preview.data.value.slug })
          }}</FieldDescription
        >
        <FieldError v-if="slugConflict">{{
          t('catalog.category.slugConflict', { slug: preview.data.value?.suggested_slug ?? '' })
        }}</FieldError>
        <FieldError v-else-if="fieldErrors.slug">{{ fieldErrors.slug }}</FieldError>
        <FieldError v-if="previewCurrent && preview.error.value">{{
          t('catalog.category.slugPreviewError')
        }}</FieldError>
      </Field>
      <Field :data-invalid="!!error?.fields.parent_id" :data-disabled="disabled">
        <FieldLabel for="category-parent">{{ t('catalog.category.parent') }}</FieldLabel>
        <Button
          id="category-parent"
          type="button"
          variant="outline"
          class="w-full justify-start"
          :disabled="disabled"
          :aria-invalid="!!error?.fields.parent_id"
          @click="open = true"
        >
          <FolderTreeIcon data-icon="inline-start" /><span class="truncate" :title="path">{{
            parentPath?.[parentPath.length - 1]?.name ??
            values.parent_id ??
            t('catalog.category.root')
          }}</span>
        </Button>
        <FieldDescription>{{ t('catalog.category.parentHelp') }}</FieldDescription>
        <FieldError v-if="error?.fields.parent_id">{{ error.fields.parent_id }}</FieldError>
      </Field>
    </FieldGroup>
    <div class="flex flex-wrap items-center justify-end gap-3">
      <Button
        v-if="!category"
        type="button"
        variant="outline"
        :disabled="disabled"
        @click="emit('cancel')"
        >{{ t('common.cancel') }}</Button
      >
      <span v-if="saved && category" role="status" class="text-xs text-muted-foreground">{{
        t('catalog.category.saved')
      }}</span>
      <Button type="submit" :disabled="submitDisabled"
        ><Spinner v-if="isSubmitting" data-icon="inline-start" />{{
          t(isSubmitting ? 'common.saving' : category ? 'common.saveChanges' : 'common.save')
        }}</Button
      >
      <Button v-if="slugConflict" type="button" :disabled="disabled" @click="saveWithSuffix"
        ><Spinner v-if="isSubmitting" data-icon="inline-start" />{{
          t('catalog.category.saveWithSuffix')
        }}</Button
      >
    </div>
  </form>
  <Sheet v-model:open="open"
    ><SheetContent class="overflow-y-auto">
      <SheetHeader
        ><SheetTitle>{{ t('catalog.category.chooseParent') }}</SheetTitle
        ><SheetDescription>{{ t('catalog.category.parentHelp') }}</SheetDescription></SheetHeader
      >
      <div class="flex flex-col gap-3 px-4 pb-4">
        <Button variant="outline" @click="choose(null)">{{ t('catalog.category.root') }}</Button>
        <CategoryTree
          v-if="open"
          :excluded="category?.id"
          :selected="values.parent_id"
          :label="t('catalog.category.chooseParent')"
          @select="choose"
        />
      </div> </SheetContent
  ></Sheet>
</template>
