<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { PlusIcon, FolderTreeIcon } from '@lucide/vue';
import { useTranslation } from '@/shared/i18n';
import { getApiError } from '@/shared/api/errors';
import { Alert, AlertTitle, AlertDescription } from '@/shared/ui/alert';
import { Button } from '@/shared/ui/button';
import { Switch } from '@/shared/ui/switch';
import { Label } from '@/shared/ui/label';
import { Skeleton } from '@/shared/ui/skeleton';
import { Empty, EmptyHeader, EmptyTitle, EmptyDescription } from '@/shared/ui/empty';
import {
  useCategories,
  useCategory,
  useCategoryMutations,
  useCategoryProducts,
  useCategoryPath,
  useCategoryTreeRefresh,
} from '../../model/category/queries';
import type { Product } from '../../model/product/schemas';
import { useCategoryPageRefresh } from '../../composable/category/useCategoryPageRefresh';
import { useCategoryPageEditor } from '../../composable/category/useCategoryPageEditor';
import { useCategoryPageProducts } from '../../composable/category/useCategoryPageProducts';
import CategoryTreePanel from '../../ui/category/CategoryTreePanel.vue';
import CategoryDetailsPanel from '../../ui/category/CategoryDetailsPanel.vue';
import CategoryProductsPanel from '../../ui/category/CategoryProductsPanel.vue';
import CategoryForm from '../../ui/category/CategoryForm.vue';
import CategoryConfirmDialog from '../../ui/category/CategoryConfirmDialog.vue';
import CategoryAddProductSheet from '../../ui/category/CategoryAddProductSheet.vue';

const { t } = useTranslation();
const route = useRoute();
const router = useRouter();
const id = computed(() =>
  typeof route.params.id === 'string' ? route.params.id.toLowerCase() : '',
);
const includeDeleted = computed(() => route.query.include_deleted === '1');
const categories = useCategories(() => !id.value, includeDeleted);
const detail = useCategory(id, includeDeleted);
const productsQuery = useCategoryProducts(
  id,
  () => !!detail.data.value && !detail.error.value,
  includeDeleted,
);
const categoryPathQuery = useCategoryPath(id, includeDeleted);
const path = computed(() => categoryPathQuery.data.value ?? []);
const products = computed(() => productsQuery.data.value?.products ?? []);
const categoryTreePanel = ref<InstanceType<typeof CategoryTreePanel>>();
const treeRefresh = useCategoryTreeRefresh();
const { create, update, remove, restore, purge, attach, detach } = useCategoryMutations();
const pending = computed(
  () =>
    treeRefresh.isPending.value ||
    create.isPending.value ||
    update.isPending.value ||
    remove.isPending.value ||
    restore.isPending.value ||
    purge.isPending.value ||
    attach.isPending.value ||
    detach.isPending.value,
);
const collapseTree = () => categoryTreePanel.value?.collapseToSelected();
const { recoveredSelection, refresh } = useCategoryPageRefresh({
  id,
  includeDeleted,
  path,
  pending,
  collapseTree,
  treeRefresh,
});
const {
  draft,
  draftParentPath,
  previewPath,
  displayedPath,
  draggedParent,
  deleting,
  restoring,
  isDeleted,
  deletion,
  canCreate,
  select,
  startCreating,
  moveCategory,
  saveNewCategory,
  save,
  restoreSelected,
  deleteSelected,
} = useCategoryPageEditor({
  id,
  includeDeleted,
  path,
  detail,
  pending,
  create,
  update,
  remove,
  restore,
  purge,
  collapseTree,
  refresh,
});
const { productSearch, adding, detaching, available, add, detachSelected } =
  useCategoryPageProducts({ id, pending, productsQuery, attach, detach });

function toggleDeleted(show: boolean) {
  if (pending.value || show === includeDeleted.value) {
    return;
  }

  const selected =
    !show && isDeleted.value
      ? [...path.value].reverse().find((category) => !category.deleted_at)?.id
      : id.value;
  const query = { ...route.query, include_deleted: show ? '1' : undefined };
  void router.push(
    selected
      ? { name: 'catalog.categories.detail', params: { id: selected }, query }
      : { name: 'catalog.categories', query },
  );
}

function retryTree() {
  if (treeRefresh.error.value) {
    void refresh();
  } else if (id.value) {
    void categoryPathQuery.refetch();
  } else {
    void categories.refetch();
  }
}

function openRestore() {
  restore.reset();
  restoring.value = true;
}

function openDelete() {
  deletion.value.reset();
  deleting.value = true;
}

function openAdd() {
  attach.reset();
  adding.value = true;
}

function openDetach(product: Product) {
  detach.reset();
  detaching.value = product;
}
</script>

<template>
  <section class="flex flex-col gap-6" aria-labelledby="categories-title">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex flex-col gap-2">
        <div class="flex items-center gap-3">
          <h1 id="categories-title" class="text-[28px] leading-[34px] font-semibold tracking-tight">
            {{ t('catalog.category.title') }}
          </h1>
        </div>
        <p class="text-[13px] text-muted-foreground">{{ t('catalog.category.description') }}</p>
      </div>
      <Button :disabled="!canCreate" @click="startCreating"
        ><PlusIcon data-icon="inline-start" />{{ t('catalog.category.create') }}</Button
      >
    </div>
    <div class="flex items-center gap-3">
      <Switch
        id="show-deleted-categories"
        :model-value="includeDeleted"
        :disabled="pending"
        @update:model-value="toggleDeleted"
      />
      <Label for="show-deleted-categories">{{ t('catalog.category.showDeleted') }}</Label>
    </div>
    <Alert v-if="recoveredSelection" role="status">
      <AlertTitle>{{ t('catalog.category.notFound') }}</AlertTitle>
      <AlertDescription>{{ t('catalog.category.selectionRecovered') }}</AlertDescription>
    </Alert>
    <div class="grid min-w-0 items-start gap-6 lg:grid-cols-[280px_minmax(0,1fr)] lg:items-stretch">
      <CategoryTreePanel
        ref="categoryTreePanel"
        :id="id"
        :categories="categories.data.value?.categories"
        :root-pending="categories.isPending.value"
        :root-error="categories.error.value"
        :path-error="categoryPathQuery.error.value"
        :refresh-error="treeRefresh.error.value"
        :pending="pending"
        :refresh-disabled="
          pending ||
          categories.isFetching.value ||
          categoryPathQuery.isFetching.value ||
          productsQuery.isFetching.value
        "
        :retry-disabled="
          pending || categories.isFetching.value || categoryPathQuery.isFetching.value
        "
        :include-deleted="includeDeleted"
        :draft="!!draft"
        :preview-path="previewPath"
        :draft-parent-path="draft ? draftParentPath : undefined"
        @refresh="refresh"
        @retry="retryTree"
        @select="select"
        @move="moveCategory"
      />
      <div class="flex min-w-0 flex-col gap-6">
        <section
          v-if="draft"
          class="flex flex-col gap-5 rounded-xl border bg-card p-5"
          aria-labelledby="create-category-title"
        >
          <h2 id="create-category-title" class="text-base font-semibold">
            {{ t('catalog.category.create') }}
          </h2>
          <CategoryForm
            key="create"
            :initial-parent="draft.parentId"
            :context-id="id"
            :include-deleted="includeDeleted"
            :pending="pending"
            :on-save="saveNewCategory"
            @draft-parent="draftParentPath = $event"
            @cancel="draft = null"
          />
        </section>
        <Empty v-else-if="!id" class="min-h-80 rounded-xl border bg-card"
          ><EmptyHeader
            ><FolderTreeIcon class="mx-auto size-8 text-muted-foreground" /><EmptyTitle>{{
              t('catalog.category.select')
            }}</EmptyTitle
            ><EmptyDescription>{{
              t('catalog.category.selectDescription')
            }}</EmptyDescription></EmptyHeader
          ></Empty
        >
        <template v-else>
          <Alert v-if="detail.error.value" variant="destructive"
            ><AlertTitle>{{
              t(
                getApiError(detail.error.value, t).status === 404
                  ? 'catalog.category.notFound'
                  : 'catalog.category.loadError',
              )
            }}</AlertTitle
            ><AlertDescription
              >{{ getApiError(detail.error.value, t).message
              }}<Button
                variant="outline"
                size="sm"
                :disabled="detail.isFetching.value"
                @click="detail.refetch()"
                >{{ t('common.tryAgain') }}</Button
              ></AlertDescription
            ></Alert
          >
          <Skeleton v-if="detail.isPending.value" class="h-72 w-full" />
          <template v-else-if="detail.data.value">
            <CategoryDetailsPanel
              :id="id"
              :category="detail.data.value"
              :displayed-path="displayedPath"
              :path-pending="categoryPathQuery.isPending.value"
              :path-error="categoryPathQuery.error.value"
              :path-available="!!categoryPathQuery.data.value"
              :detail-error="detail.error.value"
              :pending="pending"
              :form-pending="
                pending ||
                !categories.data.value ||
                !!categories.error.value ||
                !!detail.error.value
              "
              :include-deleted="includeDeleted"
              :parent-selection="draggedParent?.categoryId === id ? draggedParent : undefined"
              :on-save="save"
              @restore="openRestore"
              @delete="openDelete"
              @retry-path="categoryPathQuery.refetch()"
              @preview="previewPath = $event"
            />
            <CategoryProductsPanel
              :products="products"
              :has-products="!!productsQuery.data.value"
              :products-error="productsQuery.error.value"
              :products-pending="productsQuery.isPending.value"
              :products-fetching="productsQuery.isFetching.value"
              :detail-error="detail.error.value"
              :read-only="isDeleted"
              :pending="pending"
              v-model:search="productSearch"
              @add="openAdd"
              @detach="openDetach"
              @retry="productsQuery.refetch()"
            />
          </template>
        </template>
      </div>
    </div>
    <CategoryConfirmDialog
      :open="deleting"
      :title="t(isDeleted ? 'catalog.category.confirmPermanent' : 'catalog.category.confirmDelete')"
      :description="
        t(
          isDeleted
            ? 'catalog.category.permanentDescription'
            : 'catalog.category.deleteDescription',
          { id },
        )
      "
      :action="t(isDeleted ? 'catalog.category.permanent' : 'catalog.category.delete')"
      :pending="deletion.isPending.value"
      :error="deletion.error.value"
      @cancel="deleting = false"
      @confirm="deleteSelected"
    />
    <CategoryConfirmDialog
      :open="restoring"
      :title="t('catalog.category.confirmRestore')"
      :description="t('catalog.category.restoreDescription', { id })"
      :action="t('catalog.category.restore')"
      :destructive="false"
      :pending="restore.isPending.value"
      :error="restore.error.value"
      @cancel="restoring = false"
      @confirm="restoreSelected"
    />
    <CategoryConfirmDialog
      :open="!!detaching"
      :title="t('catalog.category.confirmDetach')"
      :description="t('catalog.category.detachDescription', { sku: detaching?.sku ?? '' })"
      :action="t('catalog.category.detach')"
      :pending="detach.isPending.value"
      :error="detach.error.value"
      @cancel="detaching = null"
      @confirm="detachSelected"
    />
    <CategoryAddProductSheet
      :open="adding"
      :category-id="id"
      :products="available.data.value?.products ?? []"
      :linked-ids="products.map((product) => product.id)"
      :loading="available.isPending.value"
      :load-error="available.error.value"
      @retry="available.refetch()"
      :pending="attach.isPending.value"
      :error="attach.error.value"
      :unavailable="!!productsQuery.error.value || !!detail.error.value || !!available.error.value"
      @close="adding = false"
      @add="add"
    />
  </section>
</template>
