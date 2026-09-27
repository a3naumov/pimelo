import { useTranslation } from '@/shared/i18n';
import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { tableFeatures, useTable, type ColumnDef } from '@tanstack/vue-table';
import type { Product } from '../../model/product/schemas';

export function useProductsTable(products: MaybeRefOrGetter<Product[]>) {
  const { t } = useTranslation();
  const features = tableFeatures({});
  const columns: ColumnDef<typeof features, Product>[] = [
    { accessorKey: 'sku', header: () => t('catalog.product.sku') },
    { accessorKey: 'id', header: () => t('catalog.product.id') },
    { id: 'actions', header: () => t('common.actions') },
  ];
  const table = useTable({
    features,
    data: computed(() => toValue(products)),
    columns,
    getRowId: (product: Product) => product.id,
  });

  return { table };
}
