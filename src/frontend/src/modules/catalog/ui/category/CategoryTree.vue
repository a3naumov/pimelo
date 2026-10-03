<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import {
  ChevronRightIcon,
  FolderIcon,
  FolderOpenIcon,
  FolderPlusIcon,
  GripVerticalIcon,
} from '@lucide/vue';
import { useTranslation } from '@/shared/i18n';
import { cn } from '@/shared/lib/utils';
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import { Empty, EmptyHeader, EmptyTitle } from '@/shared/ui/empty';
import { useCategoryTree } from '../../composable/category/useCategoryTree';
import { useCategoryDrag } from '../../composable/category/useCategoryDrag';
import { getApiError } from '@/shared/api/errors';
import { Spinner } from '@/shared/ui/spinner';
import { Alert, AlertTitle, AlertDescription } from '@/shared/ui/alert';
import { categoryPath, type CategoryRow } from '../../model/category/hierarchy';
import type { Category } from '../../model/category/schemas';

const props = defineProps<{
  selected?: string | null;
  excluded?: string;
  disabled?: boolean;
  showError?: boolean;
  label?: string;
  previewPath?: Category[];
  includeDeleted?: boolean;
  toggleOnSelect?: boolean;
  draftParentPath?: Category[];
  allowDrag?: boolean;
}>();
const emit = defineEmits<{ select: [id: string]; move: [id: string, parentId: string | null] }>();
const { t } = useTranslation();
const {
  roots,
  path,
  rows,
  entries,
  states,
  expanded,
  categories,
  serverCategories,
  toggle,
  collapseToSelected,
} = useCategoryTree(
  () => props.selected ?? '',
  () => props.excluded ?? '',
  () => props.previewPath,
  () => props.includeDeleted ?? false,
  () => props.draftParentPath,
);
const drag = useCategoryDrag(
  categories,
  () => !!props.allowDrag && !props.disabled && !path.isFetching.value,
  (id, parentId) => emit('move', id, parentId),
  serverCategories,
  { isExpanded: (id) => expanded.value.has(id), expand: toggle },
);
defineExpose({ collapseToSelected });
const focused = ref<string>();
const tree = ref<HTMLElement>();
const draftRows = ref<HTMLElement[]>([]);
watch(
  () => props.draftParentPath,
  async () => {
    await nextTick();
    draftRows.value[0]?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
  },
  { immediate: true, flush: 'post' },
);
const tabStop = computed(
  () =>
    [focused.value, props.selected].find((id) =>
      rows.value.some((row) => row.category.id === id),
    ) ?? rows.value[0]?.category.id,
);

function focus(index: number) {
  const row = rows.value[index];

  if (!row) {
    return;
  }

  focused.value = row.category.id;
  tree.value?.querySelectorAll<HTMLElement>('[role="treeitem"][tabindex]')[index]?.focus();
}

function select(row: CategoryRow) {
  if (props.disabled) {
    return;
  }

  if (props.toggleOnSelect && row.hasChildren) {
    toggle(row.category.id);
  }

  emit('select', row.category.id);
}

function keydown(event: KeyboardEvent, row: CategoryRow, index: number) {
  if (props.disabled || event.target !== event.currentTarget) {
    return;
  }

  if (
    !['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Enter', ' '].includes(
      event.key,
    )
  ) {
    return;
  }

  event.preventDefault();

  if (event.key === 'ArrowDown') {
    focus(index + 1);
  }

  if (event.key === 'ArrowUp') {
    focus(index - 1);
  }

  if (event.key === 'Home') {
    focus(0);
  }

  if (event.key === 'End') {
    focus(rows.value.length - 1);
  }

  if (event.key === 'ArrowRight' && row.hasChildren) {
    if (expanded.value.has(row.category.id)) {
      focus(index + 1);
    } else {
      toggle(row.category.id);
    }
  }

  if (event.key === 'ArrowLeft') {
    if (expanded.value.has(row.category.id)) {
      toggle(row.category.id);
    } else {
      focus(rows.value.findIndex((item) => item.category.id === row.category.parent_id));
    }
  }

  if (event.key === 'Enter' || event.key === ' ') {
    select(row);
  }
}
</script>

<template>
  <div v-if="allowDrag" class="flex flex-col gap-2 p-2">
    <p class="text-xs text-muted-foreground">{{ t('catalog.category.dragHelp') }}</p>
    <div
      role="group"
      :aria-label="t('catalog.category.rootDrop')"
      :class="
        cn(
          'rounded-lg border border-dashed p-2 text-center text-xs text-muted-foreground',
          drag.target.value === null && 'bg-accent ring-2 ring-ring',
        )
      "
      @dragover="drag.over($event, null)"
      @dragleave="drag.leave"
      @drop.stop="drag.drop($event, null)"
    >
      {{ t('catalog.category.rootDrop') }}
    </div>
  </div>
  <div
    v-if="roots.isPending.value"
    role="status"
    :aria-label="t('catalog.category.loading')"
    class="p-4"
  >
    <Spinner />
  </div>
  <Alert
    v-if="showError !== false && ((!selected && roots.error.value) || path.error.value)"
    variant="destructive"
  >
    <AlertTitle>{{ t('catalog.category.loadError') }}</AlertTitle>
    <AlertDescription
      >{{ getApiError(roots.error.value ?? path.error.value, t).message }}
      <Button
        variant="outline"
        :disabled="disabled"
        @click="selected ? path.refetch() : roots.refetch()"
        >{{ t('common.tryAgain') }}</Button
      >
    </AlertDescription>
  </Alert>
  <div
    v-if="path.isFetching.value && !roots.isPending.value"
    role="status"
    :aria-label="t('catalog.category.loading')"
    class="p-2"
  >
    <Spinner />
  </div>
  <div class="overflow-x-auto">
    <div
      ref="tree"
      role="tree"
      :aria-label="label ?? t('catalog.category.tree')"
      class="flex w-max min-w-full flex-col gap-1 p-2"
      :aria-busy="!!disabled || path.isFetching.value || roots.isFetching.value"
    >
      <template
        v-for="entry in entries"
        :key="entry.kind === 'draft' ? 'draft' : entry.row.category.id"
      >
        <div
          v-if="entry.kind === 'draft'"
          ref="draftRows"
          role="treeitem"
          aria-disabled="true"
          :aria-level="entry.depth + 1"
          :aria-label="t('catalog.category.newCategory')"
          :title="
            [
              ...(draftParentPath ?? []).map((category) => category.name),
              t('catalog.category.newCategory'),
            ].join(' / ')
          "
          :style="{ paddingLeft: `${8 + entry.depth * 16}px` }"
          class="flex min-h-10 items-center gap-2 rounded-lg border border-dashed border-primary/50 bg-accent pr-2 text-xs text-accent-foreground"
        >
          <span class="w-8 shrink-0" />
          <FolderPlusIcon class="size-4 shrink-0" aria-hidden="true" />
          <span>{{ t('catalog.category.newCategory') }}</span>
          <Badge variant="outline">{{ t('catalog.category.unsaved') }}</Badge>
        </div>
        <div
          v-else
          role="treeitem"
          :aria-level="entry.row.depth + 1"
          :aria-selected="selected === entry.row.category.id"
          :aria-expanded="entry.row.hasChildren ? expanded.has(entry.row.category.id) : undefined"
          :aria-disabled="disabled"
          :aria-busy="
            expanded.has(entry.row.category.id) && states.get(entry.row.category.id)?.isFetching
          "
          :aria-label="entry.row.category.name"
          :title="
            categoryPath(categories, entry.row.category.id)
              .map((item) => item.name)
              .join(' / ')
          "
          :tabindex="tabStop === entry.row.category.id ? 0 : -1"
          :draggable="drag.canDrag(entry.row.category)"
          :class="
            cn(
              'flex min-h-10 min-w-0 cursor-pointer items-center gap-2 rounded-lg pr-2 text-xs outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring',
              selected === entry.row.category.id && 'bg-accent text-accent-foreground font-medium',
              drag.dragged.value === entry.row.category.id && 'opacity-50',
              drag.target.value === entry.row.category.id && 'bg-accent ring-2 ring-ring',
            )
          "
          :style="{ paddingLeft: `${8 + entry.row.depth * 16}px` }"
          @focus="focused = entry.row.category.id"
          @keydown="keydown($event, entry.row, entry.index)"
          @click="select(entry.row)"
          @dragstart.stop="drag.start($event, entry.row.category)"
          @dragend="drag.end"
          @dragover.stop="drag.over($event, entry.row.category.id)"
          @dragleave="drag.leave"
          @drop.stop="drag.drop($event, entry.row.category.id)"
        >
          <GripVerticalIcon
            v-if="drag.canDrag(entry.row.category)"
            class="size-3 shrink-0 cursor-grab text-muted-foreground"
            aria-hidden="true"
          />
          <Button
            v-if="entry.row.hasChildren"
            variant="ghost"
            size="icon-sm"
            class="shrink-0"
            :tabindex="-1"
            :disabled="disabled"
            :aria-label="
              t(
                expanded.has(entry.row.category.id)
                  ? 'catalog.category.collapse'
                  : 'catalog.category.expand',
                { id: entry.row.category.name },
              )
            "
            @click.stop="!disabled && toggle(entry.row.category.id)"
          >
            <ChevronRightIcon :class="cn(expanded.has(entry.row.category.id) && 'rotate-90')" />
          </Button>
          <span v-else class="w-8 shrink-0" />
          <component
            :is="selected === entry.row.category.id ? FolderOpenIcon : FolderIcon"
            class="size-4 shrink-0 text-muted-foreground"
            aria-hidden="true"
          />
          <Spinner
            v-if="
              expanded.has(entry.row.category.id) && states.get(entry.row.category.id)?.isFetching
            "
          />
          <Button
            v-if="expanded.has(entry.row.category.id) && states.get(entry.row.category.id)?.error"
            variant="outline"
            size="sm"
            :disabled="disabled"
            :aria-label="t('catalog.category.retryChildren', { id: entry.row.category.name })"
            @click.stop="states.get(entry.row.category.id)?.refetch()"
            >{{ t('common.tryAgain') }}</Button
          >
          <span
            v-if="expanded.has(entry.row.category.id) && states.get(entry.row.category.id)?.error"
            role="alert"
            class="sr-only"
            >{{ t('catalog.category.loadError') }}</span
          >
          <Badge v-if="entry.row.category.deleted_at" variant="secondary">{{
            t('catalog.category.deleted')
          }}</Badge>
          <span class="max-w-44 truncate">{{ entry.row.category.name }}</span>
        </div>
      </template>
    </div>
  </div>
  <Empty v-if="!roots.isPending.value && !roots.error.value && !entries.length"
    ><EmptyHeader
      ><EmptyTitle>{{ t('catalog.category.noResults') }}</EmptyTitle></EmptyHeader
    ></Empty
  >
</template>
