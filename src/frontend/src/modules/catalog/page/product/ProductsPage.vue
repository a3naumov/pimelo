<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { cn } from '@/shared/lib/utils';
import { computed, ref, watch } from 'vue';
import { PlusIcon, RefreshCwIcon } from '@lucide/vue';
import { Input } from '@/shared/ui/input';
import { Badge } from '@/shared/ui/badge';
import { Separator } from '@/shared/ui/separator';
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
const search = ref('');
const deletion = computed(() => (selectedProduct.value?.deleted_at ? purge : remove));

function changeStatus(value: unknown) {
  if (value !== 'active' && value !== 'deleted') {
    return;
  }

  void router.push({ query: { ...route.query, status: value === 'deleted' ? value : undefined } });
}

const restoringProduct = ref<Product | null>(null);
watch(status, () => {
  search.value = '';
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
      <div class="flex flex-col gap-2">
        <div class="flex items-center gap-3">
          <h1 id="products-title" class="text-[28px] leading-[34px] font-semibold tracking-tight">
            {{ t('catalog.product.title') }}
          </h1>
          <Badge v-if="data" variant="secondary">{{ data.products.length }}</Badge>
        </div>
        <p class="text-[13px] text-muted-foreground">{{ t('catalog.product.description') }}</p>
      </div>
      <Button as-child
        ><RouterLink :to="{ name: 'catalog.products.create' }"
          ><PlusIcon data-icon="inline-start" aria-hidden="true" />{{
            t('catalog.product.create')
          }}</RouterLink
        ></Button
      >
    </div>
    <div class="overflow-hidden rounded-xl border bg-card text-card-foreground">
      <div class="overflow-x-auto px-5">
        <ToggleGroup
          type="single"
          variant="underline"
          size="tab"
          :spacing="7"
          :model-value="status"
          :disabled="pending"
          :aria-label="t('catalog.product.status')"
          @update:model-value="changeStatus"
        >
          <ToggleGroupItem value="active">{{ t('catalog.product.active') }}</ToggleGroupItem>
          <ToggleGroupItem value="deleted">{{ t('catalog.product.deleted') }}</ToggleGroupItem>
        </ToggleGroup>
      </div>
      <Separator />
      <div class="flex flex-wrap items-center gap-3 p-5">
        <Input
          v-model="search"
          type="search"
          :aria-label="t('catalog.product.search')"
          :placeholder="t('catalog.product.search')"
          class="min-w-40 flex-1"
          :disabled="isPending || !data?.products.length"
        />
        <Button variant="outline" size="sm" :disabled="isFetching || pending" @click="refetch()">
          <RefreshCwIcon
            data-icon="inline-start"
            aria-hidden="true"
            :class="cn(isFetching && 'animate-spin')"
          />
          {{ t('catalog.product.refresh') }}
        </Button>
      </div>
      <ProductLoadError v-if="error" :error="error" :pending="isFetching" @retry="refetch()" />
      <div
        v-if="isPending"
        class="flex flex-col gap-3 p-5"
        role="status"
        :aria-label="t('catalog.product.loadingList')"
      >
        <Skeleton v-for="row in 4" :key="row" class="h-12 w-full" />
      </div>
      <template v-else-if="data">
        <ProductsTable
          v-if="data.products.length"
          :products="data.products"
          :search="search"
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
    </div>
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
