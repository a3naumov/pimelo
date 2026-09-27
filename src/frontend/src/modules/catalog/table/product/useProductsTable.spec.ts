import { createAppI18n } from '@/shared/i18n';
import { defineComponent, h, nextTick, ref } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import type { Product } from '../../model/product/schemas';
import { useProductsTable } from './useProductsTable';

enableAutoUnmount(afterEach);

function setup(products: Parameters<typeof useProductsTable>[0]) {
  let result!: ReturnType<typeof useProductsTable>;
  mount(
    defineComponent({
      setup() {
        result = useProductsTable(products);

        return () => h('div');
      },
    }),
    { global: { plugins: [createAppI18n()] } },
  );

  return result.table;
}

const first: Product = {
  id: '0195f582-9762-7c2a-9228-4060489e06d8',
  sku: 'FIRST',
  deleted_at: null,
};
const second: Product = {
  id: '0195f582-9762-7c2a-9228-4060489e06d9',
  sku: 'SECOND',
  deleted_at: null,
};

describe('Product table data', () => {
  it('updates rows from a reactive getter and keeps product IDs after reordering', async () => {
    const products = ref<Product[]>([first, second]);
    const table = setup(() => products.value);
    expect(table.getRowModel().rows.map((row) => row.id)).toEqual([first.id, second.id]);
    products.value = [second, { ...first, sku: 'UPDATED' }];
    await nextTick();
    expect(table.getRowModel().rows.map((row) => row.id)).toEqual([second.id, first.id]);
    expect(table.getRow(first.id).getValue('sku')).toBe('UPDATED');

    products.value = [{ ...first, deleted_at: '2026-09-25T10:00:00+00:00' }];
    await nextTick();
    expect(table.getRowModel().rows).toHaveLength(1);
    expect(table.getRow(first.id).original.deleted_at).not.toBeNull();
    products.value = [];
    await nextTick();
    expect(table.getRowModel().rows).toEqual([]);
  });

  it('keeps table instances independent when a ref changes', async () => {
    const products = ref<Product[]>([first]);
    const table = setup(products);
    const other = setup([second]);
    products.value = [];
    await nextTick();
    expect(table.getRowModel().rows).toEqual([]);
    expect(other.getRowModel().rows.map((row) => row.original)).toEqual([second]);
  });
});
