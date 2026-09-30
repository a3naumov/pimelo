import { useTranslation } from '@/shared/i18n';
import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { tableFeatures, useTable, type ColumnDef } from '@tanstack/vue-table';
import type { Attribute } from '../../model/attribute/schemas';

export function useAttributesTable(
  attributes: MaybeRefOrGetter<Attribute[]>,
  search: MaybeRefOrGetter<string> = '',
) {
  const { t } = useTranslation();
  const features = tableFeatures({});
  const columns: ColumnDef<typeof features, Attribute>[] = [
    { accessorKey: 'name', header: () => t('attributes.attribute.attribute') },
    { accessorKey: 'id', header: () => t('attributes.attribute.id') },
    { accessorKey: 'created_at', header: () => t('attributes.attribute.createdAt') },
    { accessorKey: 'updated_at', header: () => t('attributes.attribute.updatedAt') },
    { id: 'status', header: () => t('attributes.attribute.status') },
    { id: 'actions', header: () => t('common.actions') },
  ];
  const table = useTable({
    features,
    data: computed(() => {
      const query = toValue(search).trim().toLocaleLowerCase();

      return toValue(attributes).filter(
        (attribute) =>
          attribute.name.toLocaleLowerCase().includes(query) ||
          attribute.id.toLocaleLowerCase().includes(query),
      );
    }),
    columns,
    getRowId: (attribute: Attribute) => attribute.id,
  });

  return { table };
}
