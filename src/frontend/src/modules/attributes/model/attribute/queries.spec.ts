import { defineComponent, h, ref } from 'vue';
import { mount, enableAutoUnmount } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as api from '../../api/attribute/attributes';
import { attributeKeys, useAttribute, useAttributeMutations, useAttributes } from './queries';
import type { AttributeStatus } from './schemas';

const attribute = {
  id: '0195f582-9762-7c2a-9228-4060489e06d8',
  name: 'Color',
  created_at: '2026-09-30T10:00:00+00:00',
  updated_at: '2026-09-30T10:00:00+00:00',
  deleted_at: null,
};
const clients: QueryClient[] = [];
enableAutoUnmount(afterEach);
afterEach(() => {
  clients.splice(0).forEach((client) => client.clear());
  vi.restoreAllMocks();
});

function setup<T>(composable: () => T) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  clients.push(client);
  let result!: T;
  mount(
    defineComponent({
      setup() {
        result = composable();

        return () => h('div');
      },
    }),
    {
      global: { plugins: [[VueQueryPlugin, { queryClient: client }]] },
    },
  );

  return { client, result };
}

describe('Attribute query lifecycle', () => {
  it('cancels the previous list request when its status changes', async () => {
    const signals: AbortSignal[] = [];
    vi.spyOn(api, 'getAttributes').mockImplementation((_status, signal) => {
      if (signal) {
        signals.push(signal);
      }

      return new Promise(() => {});
    });
    const status = ref<AttributeStatus>('active');
    const { result } = setup(() => useAttributes(status));
    await vi.waitFor(() => expect(signals).toHaveLength(1));
    status.value = 'deleted';
    await vi.waitFor(() => expect(signals).toHaveLength(2));
    expect(signals[0]?.aborted).toBe(true);
    expect(result.data.value).toBeUndefined();
  });
  it('cancels the previous detail request when the route ID changes', async () => {
    const signals: AbortSignal[] = [];
    vi.spyOn(api, 'getAttribute').mockImplementation((_id, signal) => {
      if (signal) {
        signals.push(signal);
      }

      return new Promise(() => {});
    });
    const id = ref(attribute.id);
    setup(() => useAttribute(id));
    await vi.waitFor(() => expect(signals).toHaveLength(1));
    id.value = '0195f582-9762-7c2a-9228-4060489e06d9';
    await vi.waitFor(() => expect(signals).toHaveLength(2));
    expect(signals[0]?.aborted).toBe(true);
    expect(signals[1]?.aborted).toBe(false);
  });
});

describe('Attribute mutation cache consistency', () => {
  it('refreshes both lists and the cached detail when an attribute is restored', async () => {
    const archived = { ...attribute, deleted_at: '2026-09-25T10:00:00+00:00' };
    let restored = false;
    vi.spyOn(api, 'getAttributes').mockImplementation(async (status) => ({
      attributes: status === 'deleted' ? (restored ? [] : [archived]) : restored ? [attribute] : [],
    }));
    vi.spyOn(api, 'restoreAttribute').mockImplementation(async () => {
      restored = true;

      return attribute;
    });
    const { client, result } = setup(() => ({
      active: useAttributes('active'),
      deleted: useAttributes('deleted'),
      ...useAttributeMutations(),
    }));
    client.setQueryData(attributeKeys.detail(attribute.id), archived);
    await vi.waitFor(() => expect(result.deleted.data.value?.attributes).toEqual([archived]));
    await result.restore.mutateAsync(attribute.id);
    expect(result.active.data.value?.attributes).toEqual([attribute]);
    expect(result.deleted.data.value?.attributes).toEqual([]);
    expect(client.getQueryData(attributeKeys.detail(attribute.id))).toEqual(attribute);
  });

  it('invalidates both inactive lists and evicts details after permanent deletion', async () => {
    vi.spyOn(api, 'deleteAttributePermanently').mockResolvedValue(undefined);
    const { client, result } = setup(useAttributeMutations);
    client.setQueryData(attributeKeys.list('active'), { attributes: [] });
    client.setQueryData(attributeKeys.list('deleted'), { attributes: [attribute] });
    client.setQueryData(attributeKeys.detail(attribute.id), attribute);
    await result.purge.mutateAsync(attribute.id);
    expect(client.getQueryData(attributeKeys.detail(attribute.id))).toBeUndefined();
    expect(client.getQueryState(attributeKeys.list('active'))?.isInvalidated).toBe(true);
    expect(client.getQueryState(attributeKeys.list('deleted'))?.isInvalidated).toBe(true);
  });
  it('refreshes active lists and publishes created and updated details', async () => {
    const list = vi.spyOn(api, 'getAttributes').mockResolvedValue({ attributes: [] });
    vi.spyOn(api, 'createAttribute').mockResolvedValue(attribute);
    const updated = { ...attribute, name: 'UPDATED' };
    vi.spyOn(api, 'updateAttribute').mockResolvedValue(updated);
    const { client, result } = setup(() => ({ list: useAttributes(), ...useAttributeMutations() }));
    await vi.waitFor(() => expect(result.list.isSuccess.value).toBe(true));
    list.mockResolvedValue({ attributes: [attribute] });
    await result.create.mutateAsync({ name: attribute.name });
    expect(client.getQueryData(attributeKeys.detail(attribute.id))).toEqual(attribute);
    expect(result.list.data.value).toEqual({ attributes: [attribute] });
    list.mockResolvedValue({ attributes: [updated] });
    await result.update.mutateAsync({ id: attribute.id, input: { name: 'UPDATED' } });
    expect(client.getQueryData(attributeKeys.detail(attribute.id))).toEqual(updated);
    expect(result.list.data.value).toEqual({ attributes: [updated] });
  });

  it('removes deleted details and refreshes the list', async () => {
    const list = vi.spyOn(api, 'getAttributes').mockResolvedValue({ attributes: [attribute] });
    vi.spyOn(api, 'deleteAttribute').mockResolvedValue(undefined);
    const { client, result } = setup(() => ({ list: useAttributes(), ...useAttributeMutations() }));
    client.setQueryData(attributeKeys.detail(attribute.id), attribute);
    await vi.waitFor(() => expect(result.list.isSuccess.value).toBe(true));
    list.mockResolvedValue({ attributes: [] });
    await result.remove.mutateAsync(attribute.id);
    expect(client.getQueryData(attributeKeys.detail(attribute.id))).toBeUndefined();
    expect(result.list.data.value).toEqual({ attributes: [] });
  });

  it('does not retry failed writes or modify cached data', async () => {
    const request = vi.spyOn(api, 'updateAttribute').mockRejectedValue(new Error('Offline'));
    const { client, result } = setup(useAttributeMutations);
    client.setQueryData(attributeKeys.detail(attribute.id), attribute);
    await expect(
      result.update.mutateAsync({ id: attribute.id, input: { name: 'OTHER' } }),
    ).rejects.toThrow('Offline');
    expect(request).toHaveBeenCalledTimes(1);
    expect(client.getQueryData(attributeKeys.detail(attribute.id))).toEqual(attribute);
  });
});
