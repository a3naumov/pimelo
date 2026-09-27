import { defineComponent, h, ref } from 'vue';
import { mount, enableAutoUnmount } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { afterEach, describe, expect, it, vi } from 'vitest';
import * as api from '../../api/product/products';
import { productKeys, useProduct, useProductMutations, useProducts } from './queries';
import type { ProductStatus } from './schemas';

const product = { id: '0195f582-9762-7c2a-9228-4060489e06d8', sku: 'SKU-01', deleted_at: null };
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

describe('Product query lifecycle', () => {
  it('cancels the previous list request when its status changes', async () => {
    const signals: AbortSignal[] = [];
    vi.spyOn(api, 'getProducts').mockImplementation((_status, signal) => {
      if (signal) {
        signals.push(signal);
      }

      return new Promise(() => {});
    });
    const status = ref<ProductStatus>('active');
    const { result } = setup(() => useProducts(status));
    await vi.waitFor(() => expect(signals).toHaveLength(1));
    status.value = 'deleted';
    await vi.waitFor(() => expect(signals).toHaveLength(2));
    expect(signals[0]?.aborted).toBe(true);
    expect(result.data.value).toBeUndefined();
  });
  it('cancels the previous detail request when the route ID changes', async () => {
    const signals: AbortSignal[] = [];
    vi.spyOn(api, 'getProduct').mockImplementation((_id, signal) => {
      if (signal) {
        signals.push(signal);
      }

      return new Promise(() => {});
    });
    const id = ref(product.id);
    setup(() => useProduct(id));
    await vi.waitFor(() => expect(signals).toHaveLength(1));
    id.value = '0195f582-9762-7c2a-9228-4060489e06d9';
    await vi.waitFor(() => expect(signals).toHaveLength(2));
    expect(signals[0]?.aborted).toBe(true);
    expect(signals[1]?.aborted).toBe(false);
  });
});

describe('Product mutation cache consistency', () => {
  it('refreshes both lists and the cached detail when a product is restored', async () => {
    const archived = { ...product, deleted_at: '2026-09-25T10:00:00+00:00' };
    let restored = false;
    vi.spyOn(api, 'getProducts').mockImplementation(async (status) => ({
      products: status === 'deleted' ? (restored ? [] : [archived]) : restored ? [product] : [],
    }));
    vi.spyOn(api, 'restoreProduct').mockImplementation(async () => {
      restored = true;

      return product;
    });
    const { client, result } = setup(() => ({
      active: useProducts('active'),
      deleted: useProducts('deleted'),
      ...useProductMutations(),
    }));
    client.setQueryData(productKeys.detail(product.id), archived);
    await vi.waitFor(() => expect(result.deleted.data.value?.products).toEqual([archived]));
    await result.restore.mutateAsync(product.id);
    expect(result.active.data.value?.products).toEqual([product]);
    expect(result.deleted.data.value?.products).toEqual([]);
    expect(client.getQueryData(productKeys.detail(product.id))).toEqual(product);
  });

  it('invalidates both inactive lists and evicts details after permanent deletion', async () => {
    vi.spyOn(api, 'deleteProductPermanently').mockResolvedValue(undefined);
    const { client, result } = setup(useProductMutations);
    client.setQueryData(productKeys.list('active'), { products: [] });
    client.setQueryData(productKeys.list('deleted'), { products: [product] });
    client.setQueryData(productKeys.detail(product.id), product);
    await result.purge.mutateAsync(product.id);
    expect(client.getQueryData(productKeys.detail(product.id))).toBeUndefined();
    expect(client.getQueryState(productKeys.list('active'))?.isInvalidated).toBe(true);
    expect(client.getQueryState(productKeys.list('deleted'))?.isInvalidated).toBe(true);
  });
  it('refreshes active lists and publishes created and updated details', async () => {
    const list = vi.spyOn(api, 'getProducts').mockResolvedValue({ products: [] });
    vi.spyOn(api, 'createProduct').mockResolvedValue(product);
    const updated = { ...product, sku: 'UPDATED' };
    vi.spyOn(api, 'updateProduct').mockResolvedValue(updated);
    const { client, result } = setup(() => ({ list: useProducts(), ...useProductMutations() }));
    await vi.waitFor(() => expect(result.list.isSuccess.value).toBe(true));
    list.mockResolvedValue({ products: [product] });
    await result.create.mutateAsync({ sku: product.sku });
    expect(client.getQueryData(productKeys.detail(product.id))).toEqual(product);
    expect(result.list.data.value).toEqual({ products: [product] });
    list.mockResolvedValue({ products: [updated] });
    await result.update.mutateAsync({ id: product.id, input: { sku: 'UPDATED' } });
    expect(client.getQueryData(productKeys.detail(product.id))).toEqual(updated);
    expect(result.list.data.value).toEqual({ products: [updated] });
  });

  it('removes deleted details and refreshes the list', async () => {
    const list = vi.spyOn(api, 'getProducts').mockResolvedValue({ products: [product] });
    vi.spyOn(api, 'deleteProduct').mockResolvedValue(undefined);
    const { client, result } = setup(() => ({ list: useProducts(), ...useProductMutations() }));
    client.setQueryData(productKeys.detail(product.id), product);
    await vi.waitFor(() => expect(result.list.isSuccess.value).toBe(true));
    list.mockResolvedValue({ products: [] });
    await result.remove.mutateAsync(product.id);
    expect(client.getQueryData(productKeys.detail(product.id))).toBeUndefined();
    expect(result.list.data.value).toEqual({ products: [] });
  });

  it('does not retry failed writes or modify cached data', async () => {
    const request = vi.spyOn(api, 'updateProduct').mockRejectedValue(new Error('Offline'));
    const { client, result } = setup(useProductMutations);
    client.setQueryData(productKeys.detail(product.id), product);
    await expect(
      result.update.mutateAsync({ id: product.id, input: { sku: 'OTHER' } }),
    ).rejects.toThrow('Offline');
    expect(request).toHaveBeenCalledTimes(1);
    expect(client.getQueryData(productKeys.detail(product.id))).toEqual(product);
  });
});
