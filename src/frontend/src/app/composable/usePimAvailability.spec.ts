import { defineComponent, h, ref } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { type AxiosAdapter } from 'axios';
import { apiClient } from '@/shared/api/client';
import { usePimAvailability } from './usePimAvailability';

let wrapper: VueWrapper;
let client: QueryClient;

function start() {
  const available = ref(false);
  client = new QueryClient();
  wrapper = mount(
    defineComponent({
      setup() {
        usePimAvailability(() => available.value);

        return () => h('div');
      },
    }),
    { global: { plugins: [[VueQueryPlugin, { queryClient: client }]] } },
  );

  return available;
}

afterEach(() => {
  wrapper?.unmount();
  client?.clear();
  vi.restoreAllMocks();
});

describe('PIM availability transport guard', () => {
  it('blocks writes before transport and removes the interceptor on disposal', async () => {
    const available = start();
    const adapter = vi.fn<AxiosAdapter>(async (config) => ({
      config,
      data: {},
      headers: {},
      status: 200,
      statusText: 'OK',
    }));

    await expect(apiClient.post('/products/', {}, { adapter })).rejects.toMatchObject({
      code: 'ERR_SERVICE_UNAVAILABLE',
    });
    expect(adapter).not.toHaveBeenCalled();
    available.value = true;
    await apiClient.post('/products/', {}, { adapter });
    expect(adapter).toHaveBeenCalledTimes(1);
    available.value = false;
    wrapper.unmount();
    await apiClient.post('/products/', {}, { adapter });
    expect(adapter).toHaveBeenCalledTimes(2);
  });

  it('cancels only PIM queries on failure and refreshes active reads on recovery', () => {
    const available = start();
    const cancel = vi.spyOn(client, 'cancelQueries');
    const refresh = vi.spyOn(client, 'refetchQueries');
    client.setQueryData(['catalog', 'products'], []);
    client.setQueryData(['attributes', 'list'], []);
    client.setQueryData(['shared', 'healthcheck'], {});
    available.value = true;
    refresh.mockClear();
    available.value = false;

    expect(cancel).toHaveBeenCalledTimes(1);
    const filter = cancel.mock.calls[0]?.[0];
    expect(
      client
        .getQueryCache()
        .findAll(filter)
        .map((query) => query.queryKey),
    ).toEqual([
      ['catalog', 'products'],
      ['attributes', 'list'],
    ]);
    expect(refresh).not.toHaveBeenCalled();
    available.value = true;
    expect(refresh).toHaveBeenCalledWith({ predicate: expect.any(Function), type: 'active' });
  });
});
