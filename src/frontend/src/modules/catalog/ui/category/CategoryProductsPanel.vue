<script setup lang="ts">
import { PlusIcon } from '@lucide/vue';
import { useTranslation } from '@/shared/i18n';
import { getApiError } from '@/shared/api/errors';
import { Alert, AlertTitle, AlertDescription } from '@/shared/ui/alert';
import { Button } from '@/shared/ui/button';
import { Badge } from '@/shared/ui/badge';
import { Input } from '@/shared/ui/input';
import { Separator } from '@/shared/ui/separator';
import { Skeleton } from '@/shared/ui/skeleton';
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from '@/shared/ui/empty';
import type { Product } from '../../model/product/schemas';
import CategoryProductsTable from './CategoryProductsTable.vue';

defineProps<{
  products: Product[];
  hasProducts: boolean;
  productsError: unknown;
  productsPending: boolean;
  productsFetching: boolean;
  detailError: unknown;
  readOnly: boolean;
  pending: boolean;
  search: string;
}>();
const emit = defineEmits<{
  add: [];
  detach: [product: Product];
  retry: [];
  'update:search': [value: string];
}>();
const { t } = useTranslation();

function updateSearch(value: string | number) {
  emit('update:search', String(value));
}
</script>
<template>
  <div class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground">
    <div class="flex flex-wrap items-center justify-between gap-4 p-5">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
          <h2 class="text-sm font-semibold">{{ t('catalog.category.products') }}</h2>
          <Badge v-if="hasProducts" variant="secondary">{{ products.length }}</Badge>
        </div>
        <p class="text-xs text-muted-foreground">
          {{ t('catalog.category.productsDescription') }}
        </p>
      </div>
      <Button
        variant="outline"
        size="sm"
        :disabled="pending || readOnly || !hasProducts || !!productsError || !!detailError"
        @click="emit('add')"
        ><PlusIcon data-icon="inline-start" />{{ t('catalog.category.addProduct') }}</Button
      >
    </div>
    <Separator />
    <div class="p-5">
      <Input
        :model-value="search"
        @update:model-value="updateSearch"
        type="search"
        :placeholder="t('catalog.product.search')"
        :aria-label="t('catalog.product.search')"
      />
    </div>
    <Alert v-if="productsError" variant="destructive"
      ><AlertTitle>{{ t('catalog.category.productsError') }}</AlertTitle
      ><AlertDescription
        >{{ getApiError(productsError, t).message
        }}<span v-if="hasProducts">{{ t('catalog.category.staleProducts') }}</span
        ><Button variant="outline" size="sm" :disabled="productsFetching" @click="emit('retry')">{{
          t('common.tryAgain')
        }}</Button></AlertDescription
      ></Alert
    >
    <div
      v-if="productsPending"
      class="flex flex-col gap-3 p-5"
      role="status"
      :aria-label="t('catalog.category.loadingProducts')"
    >
      <Skeleton v-for="n in 3" :key="n" class="h-14 w-full" />
    </div>
    <template v-else-if="hasProducts"
      ><CategoryProductsTable
        v-if="products.length"
        :products="products"
        :read-only="readOnly"
        :search="search"
        :pending="pending || !!productsError || !!detailError"
        @detach="emit('detach', $event)"
      /><Empty v-else
        ><EmptyHeader
          ><EmptyTitle>{{ t('catalog.category.noProducts') }}</EmptyTitle
          ><EmptyDescription>{{
            t('catalog.category.noProductsDescription')
          }}</EmptyDescription></EmptyHeader
        ></Empty
      ></template
    >
  </div>
</template>
