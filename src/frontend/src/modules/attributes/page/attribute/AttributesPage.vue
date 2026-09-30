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
import { useAttributes, useAttributeMutations } from '../../model/attribute/queries';
import type { Attribute, AttributeStatus } from '../../model/attribute/schemas';
import AttributesTable from '../../ui/attribute/AttributesTable.vue';
import AttributeDeleteDialog from '../../ui/attribute/AttributeDeleteDialog.vue';
import AttributeRestoreDialog from '../../ui/attribute/AttributeRestoreDialog.vue';
import AttributeLoadError from '../../ui/attribute/AttributeLoadError.vue';

const { t } = useTranslation();

const route = useRoute();
const router = useRouter();
const status = computed<AttributeStatus>(() =>
  route.query.status === 'deleted' ? 'deleted' : 'active',
);
const { data, error, isPending, isFetching, refetch } = useAttributes(status);
const { remove, restore, purge } = useAttributeMutations();
const pending = computed(
  () => remove.isPending.value || restore.isPending.value || purge.isPending.value,
);
const selectedAttribute = ref<Attribute | null>(null);
const search = ref('');
const deletion = computed(() => (selectedAttribute.value?.deleted_at ? purge : remove));

function changeStatus(value: unknown) {
  if (value !== 'active' && value !== 'deleted') {
    return;
  }

  void router.push({ query: { ...route.query, status: value === 'deleted' ? value : undefined } });
}

const restoringAttribute = ref<Attribute | null>(null);
watch(status, () => {
  search.value = '';
  selectedAttribute.value = null;
  restoringAttribute.value = null;
  restore.reset();
});

function selectAttribute(attribute: Attribute) {
  remove.reset();
  purge.reset();
  selectedAttribute.value = attribute;
}

function selectRestoreAttribute(attribute: Attribute) {
  if (pending.value) {
    return;
  }

  restore.reset();
  restoringAttribute.value = attribute;
}

async function confirmRestore() {
  if (!restoringAttribute.value || pending.value) {
    return;
  }

  try {
    await restore.mutateAsync(restoringAttribute.value.id);
    restoringAttribute.value = null;
  } catch {
    // The confirmation dialog displays the mutation error.
  }
}

async function confirmDelete() {
  if (!selectedAttribute.value || pending.value) {
    return;
  }

  try {
    await deletion.value.mutateAsync(selectedAttribute.value.id);
    selectedAttribute.value = null;
  } catch {
    // The confirmation dialog displays the mutation error.
  }
}
</script>

<template>
  <section class="flex flex-col gap-6" aria-labelledby="attributes-title">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex flex-col gap-2">
        <div class="flex items-center gap-3">
          <h1 id="attributes-title" class="text-[28px] leading-[34px] font-semibold tracking-tight">
            {{ t('attributes.attribute.title') }}
          </h1>
          <Badge v-if="data" variant="secondary">{{ data.attributes.length }}</Badge>
        </div>
        <p class="text-[13px] text-muted-foreground">{{ t('attributes.attribute.description') }}</p>
      </div>
      <Button as-child
        ><RouterLink :to="{ name: 'attributes.create' }"
          ><PlusIcon data-icon="inline-start" aria-hidden="true" />{{
            t('attributes.attribute.create')
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
          :aria-label="t('attributes.attribute.status')"
          @update:model-value="changeStatus"
        >
          <ToggleGroupItem value="active">{{ t('attributes.attribute.active') }}</ToggleGroupItem>
          <ToggleGroupItem value="deleted">{{ t('attributes.attribute.deleted') }}</ToggleGroupItem>
        </ToggleGroup>
      </div>
      <Separator />
      <div class="flex flex-wrap items-center gap-3 p-5">
        <Input
          v-model="search"
          type="search"
          :aria-label="t('attributes.attribute.search')"
          :placeholder="t('attributes.attribute.search')"
          class="min-w-40 flex-1"
          :disabled="isPending || !data?.attributes.length"
        />
        <Button variant="outline" size="sm" :disabled="isFetching || pending" @click="refetch()">
          <RefreshCwIcon
            data-icon="inline-start"
            aria-hidden="true"
            :class="cn(isFetching && 'animate-spin')"
          />
          {{ t('attributes.attribute.refresh') }}
        </Button>
      </div>
      <AttributeLoadError v-if="error" :error="error" :pending="isFetching" @retry="refetch()" />
      <div
        v-if="isPending"
        class="flex flex-col gap-3 p-5"
        role="status"
        :aria-label="t('attributes.attribute.loadingList')"
      >
        <Skeleton v-for="row in 4" :key="row" class="h-12 w-full" />
      </div>
      <template v-else-if="data">
        <AttributesTable
          v-if="data.attributes.length"
          :attributes="data.attributes"
          :search="search"
          :pending="pending"
          @delete="selectAttribute"
          @restore="selectRestoreAttribute"
        />
        <Empty v-else
          ><EmptyHeader
            ><EmptyTitle>{{
              status === 'deleted'
                ? t('attributes.attribute.emptyDeleted.title')
                : t('attributes.attribute.empty.title')
            }}</EmptyTitle
            ><EmptyDescription>{{
              status === 'deleted'
                ? t('attributes.attribute.emptyDeleted.description')
                : t('attributes.attribute.empty.description')
            }}</EmptyDescription></EmptyHeader
          ></Empty
        >
      </template>
    </div>
    <AttributeRestoreDialog
      :attribute="restoringAttribute"
      :pending="restore.isPending.value"
      :error="restore.error.value"
      @cancel="restoringAttribute = null"
      @confirm="confirmRestore"
    />
    <AttributeDeleteDialog
      :attribute="selectedAttribute"
      :pending="deletion.isPending.value"
      :error="deletion.error.value"
      @cancel="selectedAttribute = null"
      @confirm="confirmDelete"
    />
  </section>
</template>
