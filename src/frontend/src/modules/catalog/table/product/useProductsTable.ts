import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { tableFeatures, useTable, type ColumnDef } from '@tanstack/vue-table';
import type { Product } from '../../model/product/schemas';

export function useProductsTable(products: MaybeRefOrGetter<Product[]>) {
  const features = tableFeatures({});
  const columns: ColumnDef<typeof features, Product>[] = [
    { accessorKey: 'sku', header: 'SKU' },
    { accessorKey: 'id', header: 'ID' },
    { id: 'actions', header: 'Actions' },
  ];
  const table = useTable({
    features,
    data: computed(() => toValue(products)),
    columns,
    getRowId: (product: Product) => product.id,
  });

  return { table };
}
