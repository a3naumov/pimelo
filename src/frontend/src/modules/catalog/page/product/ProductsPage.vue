<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { ToggleGroup, ToggleGroupItem } from '@/shared/ui/toggle-group';
import { Button } from '@/shared/ui/button';
import { Empty, EmptyDescription, EmptyHeader, EmptyTitle } from '@/shared/ui/empty';
import { Skeleton } from '@/shared/ui/skeleton';
import { useProducts, useProductMutations } from '../../model/product/queries';
import type { Product, ProductStatus } from '../../model/product/schemas';
import ProductsTable from '../../ui/product/ProductsTable.vue';
import ProductDeleteDialog from '../../ui/product/ProductDeleteDialog.vue';
import ProductRestoreDialog from '../../ui/product/ProductRestoreDialog.vue';
import ProductLoadError from '../../ui/product/ProductLoadError.vue';

const { t } = useTranslation();

const route = useRoute();
const router = useRouter();
const status = computed<ProductStatus>(() =>
  route.query.status === 'deleted' ? 'deleted' : 'active',
);
const { data, error, isPending, isFetching, refetch } = useProducts(status);
const { remove, restore, purge } = useProductMutations();
const pending = computed(
  () => remove.isPending.value || restore.isPending.value || purge.isPending.value,
);
const selectedProduct = ref<Product | null>(null);
const deletion = computed(() => (selectedProduct.value?.deleted_at ? purge : remove));

function changeStatus(value: unknown) {
  if (value !== 'active' && value !== 'deleted') {
    return;
  }

  void router.push({ query: { ...route.query, status: value === 'deleted' ? value : undefined } });
}

const restoringProduct = ref<Product | null>(null);
watch(status, () => {
  selectedProduct.value = null;
  restoringProduct.value = null;
  restore.reset();
});

function selectProduct(product: Product) {
  remove.reset();
  purge.reset();
  selectedProduct.value = product;
}

function selectRestoreProduct(product: Product) {
  if (pending.value) {
    return;
  }

  restore.reset();
  restoringProduct.value = product;
}

async function confirmRestore() {
  if (!restoringProduct.value || pending.value) {
    return;
  }

  try {
    await restore.mutateAsync(restoringProduct.value.id);
    restoringProduct.value = null;
  } catch {
    // The confirmation dialog displays the mutation error.
  }
}

async function confirmDelete() {
  if (!selectedProduct.value || pending.value) {
    return;
  }

  try {
    await deletion.value.mutateAsync(selectedProduct.value.id);
    selectedProduct.value = null;
  } catch {
    // The confirmation dialog displays the mutation error.
  }
}
</script>

<template>
  <section class="flex flex-col gap-6" aria-labelledby="products-title">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 id="products-title" class="text-2xl font-semibold tracking-tight">
          {{ t('catalog.product.title') }}
        </h1>
        <p class="text-muted-foreground">{{ t('catalog.product.description') }}</p>
      </div>
      <Button as-child
        ><RouterLink :to="{ name: 'catalog.products.create' }">{{
          t('catalog.product.create')
        }}</RouterLink></Button
      >
    </div>
    <ToggleGroup
      type="single"
      variant="outline"
      :model-value="status"
      :disabled="pending"
      :aria-label="t('catalog.product.status')"
      @update:model-value="changeStatus"
    >
      <ToggleGroupItem value="active">{{ t('catalog.product.active') }}</ToggleGroupItem>
      <ToggleGroupItem value="deleted">{{ t('catalog.product.deleted') }}</ToggleGroupItem>
    </ToggleGroup>
    <ProductLoadError v-if="error" :error="error" :pending="isFetching" @retry="refetch()" />
    <div
      v-if="isPending"
      class="flex flex-col gap-3"
      role="status"
      :aria-label="t('catalog.product.loadingList')"
    >
      <Skeleton v-for="row in 4" :key="row" class="h-12 w-full" />
    </div>
    <template v-else-if="data">
      <ProductsTable
        v-if="data.products.length"
        :products="data.products"
        :pending="pending"
        @delete="selectProduct"
        @restore="selectRestoreProduct"
      />
      <Empty v-else
        ><EmptyHeader
          ><EmptyTitle>{{
            status === 'deleted'
              ? t('catalog.product.emptyDeleted.title')
              : t('catalog.product.empty.title')
          }}</EmptyTitle
          ><EmptyDescription>{{
            status === 'deleted'
              ? t('catalog.product.emptyDeleted.description')
              : t('catalog.product.empty.description')
          }}</EmptyDescription></EmptyHeader
        ></Empty
      >
    </template>
    <ProductRestoreDialog
      :product="restoringProduct"
      :pending="restore.isPending.value"
      :error="restore.error.value"
      @cancel="restoringProduct = null"
      @confirm="confirmRestore"
    />
    <ProductDeleteDialog
      :product="selectedProduct"
      :pending="deletion.isPending.value"
      :error="deletion.error.value"
      @cancel="selectedProduct = null"
      @confirm="confirmDelete"
    />
  </section>
</template>
