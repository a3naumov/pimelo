import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import {
  createProduct,
  deleteProduct,
  deleteProductPermanently,
  restoreProduct,
  getProduct,
  getProducts,
  updateProduct,
} from '../../api/product/products';
import type { Product, ProductInput, ProductStatus } from './schemas';
import { categoryKeys } from '../category/keys';

export const productKeys = {
  lists: () => ['catalog', 'products', 'list'] as const,
  list: (status: ProductStatus) => [...productKeys.lists(), { status }] as const,
  detail: (id: string) => ['catalog', 'products', 'detail', id] as const,
};

export function useProducts(
  status: MaybeRefOrGetter<ProductStatus> = 'active',
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => productKeys.list(toValue(status))),
    enabled,
    queryFn: ({ signal }) => getProducts(toValue(status), signal),
  });
}

export function useProduct(id: MaybeRefOrGetter<string>) {
  return useQuery({
    queryKey: computed(() => productKeys.detail(toValue(id))),
    queryFn: ({ signal }) => getProduct(toValue(id), signal),
  });
}

export function useProductMutations() {
  const client = useQueryClient();

  async function saved(product: Product) {
    await client.cancelQueries({ queryKey: productKeys.detail(product.id) });
    client.setQueryData(productKeys.detail(product.id), product);
    await client.cancelQueries({ queryKey: categoryKeys.productLists });
    await Promise.all([
      client.invalidateQueries({ queryKey: productKeys.lists() }),
      client.invalidateQueries({ queryKey: categoryKeys.productLists }),
    ]);
  }

  const create = useMutation({ mutationFn: createProduct, onSuccess: saved });

  const restore = useMutation({ mutationFn: restoreProduct, onSuccess: saved });

  const update = useMutation({
    mutationFn: ({ id, input }: { id: string; input: ProductInput }) => updateProduct(id, input),
    onSuccess: saved,
  });

  async function removed(id: string) {
    await client.cancelQueries({ queryKey: productKeys.detail(id) });
    client.removeQueries({ queryKey: productKeys.detail(id) });
    await client.cancelQueries({ queryKey: categoryKeys.productLists });
    await Promise.all([
      client.invalidateQueries({ queryKey: productKeys.lists() }),
      client.invalidateQueries({ queryKey: categoryKeys.productLists }),
    ]);
  }

  const remove = useMutation({
    mutationFn: deleteProduct,
    onSuccess: (_, id) => removed(id),
  });

  const purge = useMutation({
    mutationFn: deleteProductPermanently,
    onSuccess: (_, id) => removed(id),
  });

  return { create, update, remove, restore, purge };
}
