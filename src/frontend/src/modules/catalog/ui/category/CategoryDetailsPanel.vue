<script setup lang="ts">
import { computed } from 'vue';
import { Trash2Icon } from '@lucide/vue';
import { useTranslation } from '@/shared/i18n';
import { Button } from '@/shared/ui/button';
import { Badge } from '@/shared/ui/badge';
import { Separator } from '@/shared/ui/separator';
import { Spinner } from '@/shared/ui/spinner';
import type { Category, CategoryInput } from '../../model/category/schemas';
import CategoryForm from './CategoryForm.vue';

const props = defineProps<{
  id: string;
  category: Category;
  displayedPath: Category[];
  pathPending: boolean;
  pathError: unknown;
  pathAvailable: boolean;
  detailError: unknown;
  pending: boolean;
  formPending: boolean;
  includeDeleted: boolean;
  parentSelection?: { categoryId: string; parent_id: string | null };
  onSave: (input: CategoryInput) => Promise<Category>;
}>();
const emit = defineEmits<{
  restore: [];
  delete: [];
  retryPath: [];
  preview: [path: Category[] | undefined];
}>();
const { t } = useTranslation();
const isDeleted = computed(() => !!props.category.deleted_at);
</script>
<template>
  <div class="overflow-hidden rounded-xl border bg-card text-card-foreground">
    <div class="flex flex-wrap items-center justify-between gap-3 p-5">
      <h2 class="text-base font-semibold">{{ t('catalog.category.details') }}</h2>
      <Badge v-if="isDeleted" variant="secondary">{{ t('catalog.category.deleted') }}</Badge>
      <Button
        v-if="isDeleted"
        variant="outline"
        :disabled="pending || !!detailError"
        @click="emit('restore')"
        >{{ t('catalog.category.restore') }}</Button
      >
      <Button
        variant="outline"
        size="sm"
        :disabled="pending || !!detailError"
        @click="emit('delete')"
        ><Trash2Icon data-icon="inline-start" />{{
          t(isDeleted ? 'catalog.category.permanent' : 'catalog.category.delete')
        }}</Button
      >
    </div>
    <Separator />
    <div class="flex flex-col gap-6 p-5">
      <dl class="flex flex-col gap-4 text-xs">
        <div class="flex flex-col gap-2">
          <dt class="text-muted-foreground">{{ t('catalog.category.id') }}</dt>
          <dd class="break-all font-medium">{{ category.id }}</dd>
        </div>
        <div class="flex flex-col gap-2">
          <dt class="text-muted-foreground">{{ t('catalog.category.path') }}</dt>
          <dd class="break-all text-muted-foreground">
            <Spinner v-if="pathPending" />
            <span v-else-if="pathError && !pathAvailable"
              >{{ t('catalog.category.loadError') }}
              <Button variant="outline" @click="emit('retryPath')">{{
                t('common.tryAgain')
              }}</Button></span
            >
            <span v-else>{{
              [t('catalog.category.root'), ...displayedPath.map((item) => item.name)].join(' / ')
            }}</span>
          </dd>
        </div>
      </dl>
      <p v-if="category.deleted_at" class="text-sm text-muted-foreground">
        {{ t('catalog.category.deletedAt', { date: category.deleted_at }) }}
      </p>
      <dl v-if="isDeleted" class="flex flex-col gap-4 text-sm">
        <div>
          <dt class="text-muted-foreground">{{ t('catalog.category.name') }}</dt>
          <dd>{{ category.name }}</dd>
        </div>
        <div>
          <dt class="text-muted-foreground">{{ t('catalog.category.slug') }}</dt>
          <dd>{{ category.slug }}</dd>
        </div>
      </dl>
      <CategoryForm
        v-if="!isDeleted"
        :include-deleted="includeDeleted"
        :key="id"
        :category="category"
        :parent-selection="parentSelection"
        :pending="formPending"
        :on-save="onSave"
        @preview="emit('preview', $event)"
      />
    </div>
  </div>
</template>
