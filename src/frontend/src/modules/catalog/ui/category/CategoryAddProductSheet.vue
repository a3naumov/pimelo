<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useTranslation } from '@/shared/i18n';
import { getApiError } from '@/shared/api/errors';
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { Badge } from '@/shared/ui/badge';
import { Spinner } from '@/shared/ui/spinner';
import { Alert, AlertTitle, AlertDescription } from '@/shared/ui/alert';
import { Empty, EmptyHeader, EmptyTitle } from '@/shared/ui/empty';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetFooter,
} from '@/shared/ui/sheet';
import type { Product } from '../../model/product/schemas';
const props = defineProps<{
  open: boolean;
  categoryId: string;
  products: Product[];
  linkedIds: string[];
  loading: boolean;
  loadError: unknown;
  pending: boolean;
  error: unknown;
  unavailable: boolean;
}>();
const emit = defineEmits<{ close: []; add: [productId: string]; retry: [] }>();
const { t } = useTranslation();
const search = ref('');
watch(
  () => props.open,
  () => {
    search.value = '';
  },
);
const matches = computed(() => {
  const query = search.value.trim().toLowerCase();

  return props.products.filter(
    (product) =>
      product.sku.toLowerCase().includes(query) || product.id.toLowerCase().includes(query),
  );
});
</script>
<template>
  <Sheet :open="open" @update:open="!$event && !pending && emit('close')"
    ><SheetContent
      :show-close-button="!pending"
      @escape-key-down="pending && $event.preventDefault()"
      @interact-outside="pending && $event.preventDefault()"
    >
      <SheetHeader
        ><SheetTitle>{{ t('catalog.category.addProduct') }}</SheetTitle
        ><SheetDescription>{{
          t('catalog.category.addDescription')
        }}</SheetDescription></SheetHeader
      >
      <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-4">
        <Input
          v-model="search"
          type="search"
          :aria-label="t('catalog.product.search')"
          :placeholder="t('catalog.product.search')"
        />
        <Alert v-if="error || unavailable" variant="destructive"
          ><AlertTitle>{{ t('catalog.category.error') }}</AlertTitle
          ><AlertDescription>{{
            error ? getApiError(error, t).message : t('catalog.category.productsError')
          }}</AlertDescription></Alert
        >
        <div v-if="loading" role="status" :aria-label="t('catalog.category.loadingProducts')">
          <Spinner />
        </div>
        <Alert v-if="loadError" variant="destructive"
          ><AlertTitle>{{ t('catalog.category.productsError') }}</AlertTitle
          ><AlertDescription
            >{{ getApiError(loadError, t).message
            }}<Button variant="outline" @click="emit('retry')">{{
              t('common.tryAgain')
            }}</Button></AlertDescription
          ></Alert
        >
        <ul v-if="!loading" class="flex flex-col divide-y">
          <li
            v-for="item in matches"
            :key="item.id"
            class="flex items-center justify-between gap-3 py-4"
          >
            <div class="flex min-w-0 flex-col gap-1">
              <span class="break-words text-sm font-medium">{{ item.sku }}</span
              ><span class="truncate text-xs text-muted-foreground" :title="item.id">{{
                item.id
              }}</span>
            </div>
            <Badge v-if="linkedIds.includes(item.id)" variant="secondary">{{
              t('catalog.category.linked')
            }}</Badge>
            <Button
              v-else
              variant="outline"
              size="sm"
              :disabled="pending || unavailable"
              :aria-label="`${t('catalog.category.add')}: ${item.sku}`"
              @click="emit('add', item.id)"
              ><Spinner v-if="pending" data-icon="inline-start" />{{
                t('catalog.category.add')
              }}</Button
            >
          </li>
        </ul>
        <Empty v-if="!loading && !loadError && !matches.length"
          ><EmptyHeader
            ><EmptyTitle>{{ t('catalog.product.noResults') }}</EmptyTitle></EmptyHeader
          ></Empty
        >
      </div>
      <SheetFooter
        ><Button variant="outline" :disabled="pending" @click="emit('close')">{{
          t('common.close')
        }}</Button></SheetFooter
      >
    </SheetContent></Sheet
  >
</template>
