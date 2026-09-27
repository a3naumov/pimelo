<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { cn } from '@/shared/lib/utils';
import { RouterLink } from 'vue-router';
import { EllipsisIcon, PackageIcon, PencilIcon, Trash2Icon, RotateCcwIcon } from '@lucide/vue';
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
import type { Product } from '../../model/product/schemas';
import { useProductsTable } from '../../table/product/useProductsTable';

const { t } = useTranslation();

const props = defineProps<{ products: Product[]; pending?: boolean; search?: string }>();
const emit = defineEmits<{ delete: [product: Product]; restore: [product: Product] }>();
const { table } = useProductsTable(
  () => props.products,
  () => props.search ?? '',
);
</script>

<template>
  <Table class="min-w-[640px]" :aria-label="t('catalog.product.title')">
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
                :aria-label="t('catalog.product.actionsFor', { sku: row.original.sku })"
                ><EllipsisIcon aria-hidden="true" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuGroup>
                <DropdownMenuItem v-if="!row.original.deleted_at" as-child>
                  <RouterLink
                    :to="{ name: 'catalog.products.edit', params: { id: row.original.id } }"
                  >
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
                      ? t('catalog.product.deletePermanently')
                      : t('common.delete')
                  }}
                </DropdownMenuItem>
              </DropdownMenuGroup>
            </DropdownMenuContent>
          </DropdownMenu>
          <div v-else-if="cell.column.id === 'sku'" class="flex items-center gap-3">
            <div class="product-symbol"><PackageIcon aria-hidden="true" /></div>
            <RouterLink
              :to="{ name: 'catalog.products.edit', params: { id: row.original.id } }"
              class="product-link"
            >
              {{ row.original.sku }}
            </RouterLink>
          </div>
          <Badge
            v-else-if="cell.column.id === 'status'"
            :variant="row.original.deleted_at ? 'secondary' : 'success'"
          >
            <span class="size-[5px] rounded-full bg-current" aria-hidden="true" />
            {{
              row.original.deleted_at ? t('catalog.product.deleted') : t('catalog.product.active')
            }}
          </Badge>
          <span v-else class="product-id"><FlexRender :cell="cell" /></span>
        </TableCell>
      </TableRow>
      <TableEmpty v-if="!table.getRowModel().rows.length" :colspan="table.getAllColumns().length">
        {{ t('catalog.product.noResults') }}
      </TableEmpty>
    </TableBody>
  </Table>
  <Separator />
  <div class="flex min-h-[58px] items-center px-5 text-xs text-muted-foreground" role="status">
    {{
      t('catalog.product.results', {
        shown: table.getRowModel().rows.length,
        total: products.length,
      })
    }}
  </div>
</template>

<style scoped>
.product-symbol {
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
.product-symbol > svg {
  width: 24px;
  height: 24px;
  stroke-width: 1.5;
}
.product-link {
  max-width: 360px;
  overflow-wrap: anywhere;
  white-space: normal;
  font-size: 12px;
  font-weight: 500;
  color: var(--secondary-foreground);
}
.product-link:hover {
  color: var(--primary);
  text-decoration: underline;
  text-underline-offset: 4px;
}
.product-link:focus-visible {
  outline: 2px solid var(--ring);
  outline-offset: 4px;
  border-radius: 2px;
}
.product-id {
  font-size: 11px;
  color: var(--muted-foreground);
}
</style>
