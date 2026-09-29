<script setup lang="ts">
import { computed, ref } from 'vue';
import { RefreshCwIcon } from '@lucide/vue';
import { useTranslation } from '@/shared/i18n';
import { getApiError } from '@/shared/api/errors';
import { Alert, AlertTitle, AlertDescription } from '@/shared/ui/alert';
import { Button } from '@/shared/ui/button';
import { Separator } from '@/shared/ui/separator';
import { Skeleton } from '@/shared/ui/skeleton';
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from '@/shared/ui/empty';
import type { Category } from '../../model/category/schemas';
import CategoryTree from './CategoryTree.vue';

const props = defineProps<{
  id: string;
  categories?: Category[];
  rootPending: boolean;
  rootError: unknown;
  pathError: unknown;
  refreshError: unknown;
  pending: boolean;
  refreshDisabled: boolean;
  retryDisabled: boolean;
  includeDeleted: boolean;
  draft: boolean;
  previewPath?: Category[];
  draftParentPath?: Category[];
}>();
const emit = defineEmits<{
  refresh: [];
  retry: [];
  select: [id: string];
  move: [id: string, parentId: string | null];
}>();
const { t } = useTranslation();
const error = computed(() => props.refreshError ?? props.rootError ?? props.pathError);
const categoryTree = ref<InstanceType<typeof CategoryTree>>();

function collapseToSelected() {
  categoryTree.value?.collapseToSelected();
}

defineExpose({ collapseToSelected });
</script>
<template>
  <aside
    class="flex min-w-0 flex-col overflow-hidden rounded-xl border bg-card text-card-foreground"
    :aria-label="t('catalog.category.tree')"
  >
    <div class="flex min-h-16 shrink-0 items-center justify-between gap-3 px-4">
      <h2 class="text-sm font-semibold">{{ t('catalog.category.tree') }}</h2>
      <Button
        variant="ghost"
        size="icon-sm"
        :aria-label="t('catalog.category.refresh')"
        :title="t('catalog.category.refresh')"
        :disabled="refreshDisabled"
        @click="emit('refresh')"
        ><RefreshCwIcon
      /></Button>
    </div>
    <Separator />
    <Alert v-if="error" variant="destructive"
      ><AlertTitle>{{ t('catalog.category.loadError') }}</AlertTitle
      ><AlertDescription
        >{{ getApiError(error, t).message
        }}<Button variant="outline" size="sm" :disabled="retryDisabled" @click="emit('retry')">{{
          t('common.tryAgain')
        }}</Button></AlertDescription
      ></Alert
    >
    <div
      v-if="rootPending && !pathError"
      class="flex flex-col gap-3 p-4"
      role="status"
      :aria-label="t('catalog.category.loading')"
    >
      <Skeleton v-for="n in 4" :key="n" class="h-10 w-full" />
    </div>
    <template v-else-if="categories"
      ><div
        v-if="categories.length || draft"
        class="max-h-72 overflow-y-auto lg:h-0 lg:max-h-none lg:flex-1"
      >
        <CategoryTree
          ref="categoryTree"
          :selected="id"
          :disabled="pending"
          :show-error="false"
          :preview-path="previewPath"
          :draft-parent-path="draft ? draftParentPath : undefined"
          :include-deleted="includeDeleted"
          toggle-on-select
          :allow-drag="!draft"
          @move="(categoryId, parentId) => emit('move', categoryId, parentId)"
          @select="emit('select', $event)"
        />
      </div>
      <Empty v-else
        ><EmptyHeader
          ><EmptyTitle>{{ t('catalog.category.empty') }}</EmptyTitle
          ><EmptyDescription>{{
            t('catalog.category.emptyDescription')
          }}</EmptyDescription></EmptyHeader
        ></Empty
      ></template
    >
  </aside>
</template>
