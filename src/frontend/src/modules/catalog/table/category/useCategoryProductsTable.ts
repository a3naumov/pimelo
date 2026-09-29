import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { tableFeatures, useTable, type ColumnDef } from '@tanstack/vue-table';
import { useTranslation } from '@/shared/i18n';
import type { Product } from '../../model/product/schemas';

export function useCategoryProductsTable(
  products: MaybeRefOrGetter<Product[]>,
  search: MaybeRefOrGetter<string>,
) {
  const { t } = useTranslation();
  const features = tableFeatures({});
  const columns: ColumnDef<typeof features, Product>[] = [
    { accessorKey: 'sku', header: () => t('catalog.product.sku') },
    { accessorKey: 'id', header: () => t('catalog.product.id') },
    { id: 'actions', header: () => t('common.actions') },
  ];
  const table = useTable({
    features,
    columns,
    getRowId: (product: Product) => product.id,
    data: computed(() => {
      const query = toValue(search).trim().toLowerCase();

      return toValue(products).filter(
        (product) =>
          product.sku.toLowerCase().includes(query) || product.id.toLowerCase().includes(query),
      );
    }),
  });

  return { table };
}
