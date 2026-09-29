<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { PackageIcon, UnlinkIcon } from '@lucide/vue';
import { FlexRender } from '@tanstack/vue-table';
import { useTranslation } from '@/shared/i18n';
import { Button } from '@/shared/ui/button';
import { Separator } from '@/shared/ui/separator';
import {
  Table,
  TableHeader,
  TableHead,
  TableBody,
  TableCell,
  TableRow,
  TableEmpty,
} from '@/shared/ui/table';
import type { Product } from '../../model/product/schemas';
import { useCategoryProductsTable } from '../../table/category/useCategoryProductsTable';
const props = defineProps<{
  products: Product[];
  search: string;
  pending: boolean;
  readOnly?: boolean;
}>();
const emit = defineEmits<{ detach: [product: Product] }>();
const { t } = useTranslation();
const { table } = useCategoryProductsTable(
  () => props.products,
  () => props.search,
);
</script>
<template>
  <Table :aria-label="t('catalog.category.products')" class="min-w-[540px]">
    <TableHeader
      ><TableRow v-for="group in table.getHeaderGroups()" :key="group.id"
        ><TableHead v-for="header in group.headers" :key="header.id"
          ><FlexRender :header="header" /></TableHead></TableRow
    ></TableHeader>
    <TableBody
      ><TableRow v-for="row in table.getRowModel().rows" :key="row.id"
        ><TableCell v-for="cell in row.getAllCells()" :key="cell.id">
          <div v-if="cell.column.id === 'sku'" class="flex items-center gap-3">
            <div
              class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground"
            >
              <PackageIcon class="size-5" />
            </div>
            <RouterLink
              class="max-w-64 whitespace-normal break-words font-medium hover:text-primary hover:underline"
              :to="{ name: 'catalog.products.edit', params: { id: row.id } }"
              >{{ row.original.sku }}</RouterLink
            >
          </div>
          <Button
            v-else-if="cell.column.id === 'actions' && !readOnly"
            variant="ghost"
            size="icon-sm"
            :disabled="pending"
            :aria-label="`${t('catalog.category.detach')}: ${row.original.sku}`"
            @click="emit('detach', row.original)"
            ><UnlinkIcon
          /></Button>
          <span v-else class="text-xs text-muted-foreground"
            ><FlexRender :cell="cell"
          /></span> </TableCell></TableRow
      ><TableEmpty v-if="!table.getRowModel().rows.length" :colspan="3">{{
        t('catalog.product.noResults')
      }}</TableEmpty></TableBody
    >
  </Table>
  <Separator />
  <p class="px-5 py-4 text-xs text-muted-foreground" role="status">
    {{
      t('catalog.product.results', {
        shown: table.getRowModel().rows.length,
        total: products.length,
      })
    }}
  </p>
</template>
