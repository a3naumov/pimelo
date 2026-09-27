<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { Button } from '@/shared/ui/button';
import { Badge } from '@/shared/ui/badge';
import { getApiError } from '@/shared/api/errors';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { Skeleton } from '@/shared/ui/skeleton';
import type { Product, ProductInput } from '../../model/product/schemas';
import { useProduct, useProductMutations } from '../../model/product/queries';
import ProductForm from '../../ui/product/ProductForm.vue';
import ProductDeleteDialog from '../../ui/product/ProductDeleteDialog.vue';
import ProductRestoreDialog from '../../ui/product/ProductRestoreDialog.vue';
import ProductLoadError from '../../ui/product/ProductLoadError.vue';

const { t } = useTranslation();

const route = useRoute();
const router = useRouter();
const id = computed(() => String(route.params.id));
const { data, error, isPending, isFetching, refetch } = useProduct(id);
const { update, remove, restore, purge } = useProductMutations();
const isDeleted = computed(() => !!data.value?.deleted_at);
const deletion = computed(() => (isDeleted.value ? purge : remove));
const pending = computed(
  () =>
    update.isPending.value ||
    remove.isPending.value ||
    restore.isPending.value ||
    purge.isPending.value,
);
const deleting = ref(false);
const restoringProduct = ref<Product | null>(null);
const canShowProduct = computed(
  () => data.value && (!error.value || getApiError(error.value, t).status !== 404),
);

async function save(input: ProductInput): Promise<ProductInput> {
  const product = await update.mutateAsync({ id: id.value, input });

  return { sku: product.sku };
}

function cancel() {
  if (pending.value) {
    return;
  }

  return router.push({
    name: 'catalog.products',
    query: isDeleted.value ? { status: 'deleted' } : {},
  });
}

watch(id, () => {
  deleting.value = false;
  restoringProduct.value = null;
  restore.reset();
  remove.reset();
  purge.reset();
});

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
  if (!data.value || pending.value) {
    return;
  }

  const wasDeleted = isDeleted.value;

  try {
    await deletion.value.mutateAsync(data.value.id);
    deleting.value = false;
    await router.push({ name: 'catalog.products', query: wasDeleted ? { status: 'deleted' } : {} });
  } catch {
    // The confirmation dialog displays the mutation error.
  }
}
</script>

<template>
  <section class="flex flex-col gap-6" aria-labelledby="edit-product-title">
    <h1 id="edit-product-title" class="text-2xl font-semibold tracking-tight">
      {{ t('catalog.product.edit') }}
    </h1>
    <Badge v-if="isDeleted" variant="secondary">{{ t('catalog.product.deleted') }}</Badge>
    <ProductLoadError v-if="error" :error="error" :pending="isFetching" @retry="refetch()" />
    <Skeleton
      v-if="isPending"
      class="h-36 w-full max-w-xl"
      role="status"
      :aria-label="t('catalog.product.loading')"
    />

    <template v-else-if="data && canShowProduct">
      <Alert v-if="isDeleted">
        <AlertTitle>{{ t('catalog.product.deletedTitle') }}</AlertTitle>
        <AlertDescription>{{ t('catalog.product.deletedDescription') }}</AlertDescription>
      </Alert>

      <ProductForm
        :key="`${data.id}:${isDeleted}`"
        :initial-sku="data.sku"
        require-changes
        :disabled="isDeleted || pending || deleting || !!restoringProduct"
        :submit-label="t('common.saveChanges')"
        :on-save="save"
        @cancel="cancel"
      />

      <div class="flex flex-wrap gap-2">
        <Button v-if="isDeleted" :disabled="pending" @click="selectRestoreProduct(data)">{{
          t('catalog.product.restore')
        }}</Button>
        <Button
          variant="destructive"
          :disabled="pending"
          @click="
            remove.reset();
            purge.reset();
            deleting = true;
          "
          >{{
            isDeleted ? t('catalog.product.deletePermanently') : t('catalog.product.delete')
          }}</Button
        >
        <Button variant="outline" as-child
          ><RouterLink
            :to="{ name: 'catalog.products', query: isDeleted ? { status: 'deleted' } : {} }"
            >{{ t('catalog.product.back') }}</RouterLink
          ></Button
        >
      </div>
    </template>

    <ProductRestoreDialog
      :product="restoringProduct"
      :pending="restore.isPending.value"
      :error="restore.error.value"
      @cancel="restoringProduct = null"
      @confirm="confirmRestore"
    />

    <ProductDeleteDialog
      :product="deleting ? (data ?? null) : null"
      :pending="deletion.isPending.value"
      :error="deletion.error.value"
      @cancel="deleting = false"
      @confirm="confirmDelete"
    />
  </section>
</template>
