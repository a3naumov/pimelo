import { ref, watch, type ComputedRef } from 'vue';
import { useCategoryProducts, useCategoryMutations } from '../../model/category/queries';
import { useProducts } from '../../model/product/queries';
import type { Product } from '../../model/product/schemas';

type Mutations = ReturnType<typeof useCategoryMutations>;

export function useCategoryPageProducts(options: {
  id: ComputedRef<string>;
  pending: ComputedRef<boolean>;
  productsQuery: ReturnType<typeof useCategoryProducts>;
  attach: Mutations['attach'];
  detach: Mutations['detach'];
}) {
  const productSearch = ref('');
  const adding = ref(false);
  const detaching = ref<Product | null>(null);
  const available = useProducts('active', () => adding.value);

  watch(options.id, () => {
    productSearch.value = '';
    adding.value = false;
    detaching.value = null;
  });

  async function add(productId: string) {
    if (options.pending.value || options.productsQuery.error.value) {
      return;
    }

    try {
      await options.attach.mutateAsync({ categoryId: options.id.value, productId });
      adding.value = false;
    } catch {
      // Keep the picker and its search available for retry.
    }
  }

  async function detachSelected() {
    if (!detaching.value || options.pending.value) {
      return;
    }

    try {
      await options.detach.mutateAsync({
        categoryId: options.id.value,
        productId: detaching.value.id,
      });
      detaching.value = null;
    } catch {
      // Keep the confirmation open for retry.
    }
  }

  return { productSearch, adding, detaching, available, add, detachSelected };
}
