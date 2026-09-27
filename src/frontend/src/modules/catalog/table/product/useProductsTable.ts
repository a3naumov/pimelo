import { useTranslation } from '@/shared/i18n';
import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { tableFeatures, useTable, type ColumnDef } from '@tanstack/vue-table';
import type { Product } from '../../model/product/schemas';

export function useProductsTable(
  products: MaybeRefOrGetter<Product[]>,
  search: MaybeRefOrGetter<string> = '',
) {
  const { t } = useTranslation();
  const features = tableFeatures({});
  const columns: ColumnDef<typeof features, Product>[] = [
    { accessorKey: 'sku', header: () => t('catalog.product.product') },
    { accessorKey: 'id', header: () => t('catalog.product.id') },
    { id: 'status', header: () => t('catalog.product.status') },
    { id: 'actions', header: () => t('common.actions') },
  ];
  const table = useTable({
    features,
    data: computed(() => {
      const query = toValue(search).trim().toLocaleLowerCase();

      return toValue(products).filter(
        (product) =>
          product.sku.toLocaleLowerCase().includes(query) ||
          product.id.toLocaleLowerCase().includes(query),
      );
    }),
    columns,
    getRowId: (product: Product) => product.id,
  });

  return { table };
}
