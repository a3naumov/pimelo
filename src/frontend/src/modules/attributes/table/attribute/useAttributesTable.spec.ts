import { createAppI18n } from '@/shared/i18n';
import { defineComponent, h, nextTick, ref } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import type { Attribute } from '../../model/attribute/schemas';
import { useAttributesTable } from './useAttributesTable';

enableAutoUnmount(afterEach);

function setup(attributes: Parameters<typeof useAttributesTable>[0]) {
  let result!: ReturnType<typeof useAttributesTable>;
  mount(
    defineComponent({
      setup() {
        result = useAttributesTable(attributes);

        return () => h('div');
      },
    }),
    { global: { plugins: [createAppI18n()] } },
  );

  return result.table;
}

const first: Attribute = {
  id: '0195f582-9762-7c2a-9228-4060489e06d8',
  name: 'FIRST',
  created_at: '2026-09-30T10:00:00+00:00',
  updated_at: '2026-09-30T10:00:00+00:00',
  deleted_at: null,
};
const second: Attribute = {
  id: '0195f582-9762-7c2a-9228-4060489e06d9',
  name: 'SECOND',
  created_at: '2026-09-30T10:00:00+00:00',
  updated_at: '2026-09-30T10:00:00+00:00',
  deleted_at: null,
};

describe('Attribute table data', () => {
  it('updates rows from a reactive getter and keeps attribute IDs after reordering', async () => {
    const attributes = ref<Attribute[]>([first, second]);
    const table = setup(() => attributes.value);
    expect(table.getRowModel().rows.map((row) => row.id)).toEqual([first.id, second.id]);
    attributes.value = [second, { ...first, name: 'UPDATED' }];
    await nextTick();
    expect(table.getRowModel().rows.map((row) => row.id)).toEqual([second.id, first.id]);
    expect(table.getRow(first.id).getValue('name')).toBe('UPDATED');

    attributes.value = [{ ...first, deleted_at: '2026-09-25T10:00:00+00:00' }];
    await nextTick();
    expect(table.getRowModel().rows).toHaveLength(1);
    expect(table.getRow(first.id).original.deleted_at).not.toBeNull();
    attributes.value = [];
    await nextTick();
    expect(table.getRowModel().rows).toEqual([]);
  });

  it('keeps table instances independent when a ref changes', async () => {
    const attributes = ref<Attribute[]>([first]);
    const table = setup(attributes);
    const other = setup([second]);
    attributes.value = [];
    await nextTick();
    expect(table.getRowModel().rows).toEqual([]);
    expect(other.getRowModel().rows.map((row) => row.original)).toEqual([second]);
  });
});
