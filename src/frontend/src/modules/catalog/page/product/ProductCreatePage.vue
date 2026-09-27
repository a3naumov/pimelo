<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useProductMutations } from '../../model/product/queries';
import type { ProductInput } from '../../model/product/schemas';
import ProductForm from '../../ui/product/ProductForm.vue';

const router = useRouter();
const { create } = useProductMutations();

async function save(input: ProductInput) {
  const product = await create.mutateAsync(input);
  await router.push({ name: 'catalog.products.edit', params: { id: product.id } });

  return { sku: product.sku };
}
</script>

<template>
  <section class="flex flex-col gap-6" aria-labelledby="create-product-title">
    <h1 id="create-product-title" class="text-2xl font-semibold tracking-tight">Create product</h1>
    <ProductForm
      submit-label="Create product"
      :on-save="save"
      @cancel="router.push({ name: 'catalog.products' })"
    />
  </section>
</template>
