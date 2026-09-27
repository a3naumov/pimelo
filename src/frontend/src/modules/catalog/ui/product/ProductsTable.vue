<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { RouterLink } from 'vue-router';
import { ChevronDownIcon, PencilIcon, Trash2Icon, RotateCcwIcon } from '@lucide/vue';
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/ui/table';
import type { Product } from '../../model/product/schemas';
import { useProductsTable } from '../../table/product/useProductsTable';

const { t } = useTranslation();

const props = defineProps<{ products: Product[]; pending?: boolean }>();
const emit = defineEmits<{ delete: [product: Product]; restore: [product: Product] }>();
const { table } = useProductsTable(() => props.products);
</script>

<template>
  <Table :aria-label="t('catalog.product.title')">
    <TableHeader>
      <TableRow v-for="group in table.getHeaderGroups()" :key="group.id">
        <TableHead v-for="header in group.headers" :key="header.id">
          <FlexRender v-if="!header.isPlaceholder" :header="header" />
        </TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
        <TableCell v-for="cell in row.getAllCells()" :key="cell.id">
          <DropdownMenu v-if="cell.column.id === 'actions'" :modal="false">
            <DropdownMenuTrigger as-child>
              <Button
                variant="outline"
                size="sm"
                :disabled="pending"
                :aria-label="t('catalog.product.actionsFor', { sku: row.original.sku })"
                >{{ t('common.actions') }}<ChevronDownIcon data-icon="inline-end" />
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
          <div v-else class="flex items-center gap-2">
            <RouterLink
              v-if="cell.column.id === 'sku'"
              :to="{ name: 'catalog.products.edit', params: { id: row.original.id } }"
              class="underline underline-offset-4"
            >
              {{ row.original.sku }}
            </RouterLink>
            <FlexRender v-else :cell="cell" />
            <Badge v-if="cell.column.id === 'sku' && row.original.deleted_at" variant="secondary">{{
              t('catalog.product.deleted')
            }}</Badge>
          </div>
        </TableCell>
      </TableRow>
    </TableBody>
  </Table>
</template>
