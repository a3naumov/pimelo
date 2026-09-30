<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { cn } from '@/shared/lib/utils';
import { RouterLink } from 'vue-router';
import { EllipsisIcon, TagsIcon, PencilIcon, Trash2Icon, RotateCcwIcon } from '@lucide/vue';
import { FlexRender } from '@tanstack/vue-table';
import { Button } from '@/shared/ui/button';
import { Badge } from '@/shared/ui/badge';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/shared/ui/dropdown-menu';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  TableEmpty,
} from '@/shared/ui/table';
import { Separator } from '@/shared/ui/separator';
import type { Attribute } from '../../model/attribute/schemas';
import { useAttributesTable } from '../../table/attribute/useAttributesTable';

const { t } = useTranslation();

const props = defineProps<{ attributes: Attribute[]; pending?: boolean; search?: string }>();
const emit = defineEmits<{ delete: [attribute: Attribute]; restore: [attribute: Attribute] }>();
const { table } = useAttributesTable(
  () => props.attributes,
  () => props.search ?? '',
);
</script>

<template>
  <Table class="min-w-[640px]" :aria-label="t('attributes.attribute.title')">
    <TableHeader>
      <TableRow v-for="group in table.getHeaderGroups()" :key="group.id">
        <TableHead
          v-for="header in group.headers"
          :key="header.id"
          :class="cn(header.column.id === 'actions' && 'w-16')"
        >
          <FlexRender
            v-if="!header.isPlaceholder && header.column.id !== 'actions'"
            :header="header"
          />
        </TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
        <TableCell v-for="cell in row.getAllCells()" :key="cell.id">
          <DropdownMenu v-if="cell.column.id === 'actions'" :modal="false">
            <DropdownMenuTrigger as-child>
              <Button
                variant="ghost"
                size="icon-sm"
                :disabled="pending"
                :aria-label="t('attributes.attribute.actionsFor', { name: row.original.name })"
                ><EllipsisIcon aria-hidden="true" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem v-if="!row.original.deleted_at" as-child>
                  <RouterLink :to="{ name: 'attributes.edit', params: { id: row.original.id } }">
                    <PencilIcon />{{ t('common.edit') }}</RouterLink
                  >
                </DropdownMenuItem>
                <DropdownMenuItem v-else @select="emit('restore', row.original)">
                  <RotateCcwIcon />{{ t('common.restore') }}</DropdownMenuItem
                >
              </DropdownMenuGroup>
              <DropdownMenuSeparator />
              <DropdownMenuGroup>
                <DropdownMenuItem variant="destructive" @select="emit('delete', row.original)">
                  <Trash2Icon />
                  {{
                    row.original.deleted_at
                      ? t('attributes.attribute.deletePermanently')
                      : t('common.delete')
                  }}
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
          <div v-else-if="cell.column.id === 'name'" class="flex items-center gap-3">
            <div class="attribute-symbol"><TagsIcon aria-hidden="true" /></div>
            <RouterLink
              :to="{ name: 'attributes.edit', params: { id: row.original.id } }"
              class="attribute-link"
            >
              {{ row.original.name }}
            </RouterLink>
          </div>
          <Badge
            v-else-if="cell.column.id === 'status'"
            :variant="row.original.deleted_at ? 'secondary' : 'success'"
          >
            <span class="size-[5px] rounded-full bg-current" aria-hidden="true" />
            {{
              row.original.deleted_at
                ? t('attributes.attribute.deleted')
                : t('attributes.attribute.active')
            }}
          </Badge>
          <time
            v-else-if="cell.column.id === 'created_at' || cell.column.id === 'updated_at'"
            :datetime="row.original[cell.column.id]"
          >
            {{ new Date(row.original[cell.column.id]).toLocaleString('en') }}
          </time>
          <span v-else class="attribute-id"><FlexRender :cell="cell" /></span>
        </TableCell>
      </TableRow>
      <TableEmpty v-if="!table.getRowModel().rows.length" :colspan="table.getAllColumns().length">
        {{ t('attributes.attribute.noResults') }}
      </TableEmpty>
    </TableBody>
  </Table>
  <Separator />
  <div class="flex min-h-[58px] items-center px-5 text-xs text-muted-foreground" role="status">
    {{
      t('attributes.attribute.results', {
        shown: table.getRowModel().rows.length,
        total: attributes.length,
      })
    }}
  </div>
</template>

<style scoped>
.attribute-symbol {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 42px;
  height: 44px;
  border-radius: 7px;
  background: var(--secondary);
  color: var(--muted-foreground);
}
.attribute-symbol > svg {
  width: 24px;
  height: 24px;
  stroke-width: 1.5;
}
.attribute-link {
  max-width: 360px;
  overflow-wrap: anywhere;
  white-space: normal;
  font-size: 12px;
  font-weight: 500;
  color: var(--secondary-foreground);
}
.attribute-link:hover {
  color: var(--primary);
  text-decoration: underline;
  text-underline-offset: 4px;
}
.attribute-link:focus-visible {
  outline: 2px solid var(--ring);
  outline-offset: 4px;
  border-radius: 2px;
}
.attribute-id {
  font-size: 11px;
  color: var(--muted-foreground);
}
</style>
