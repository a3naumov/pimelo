<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { Button } from '@/shared/ui/button';
import { Badge } from '@/shared/ui/badge';
import { getApiError } from '@/shared/api/errors';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { Skeleton } from '@/shared/ui/skeleton';
import type { Attribute, AttributeInput } from '../../model/attribute/schemas';
import { useAttribute, useAttributeMutations } from '../../model/attribute/queries';
import AttributeForm from '../../ui/attribute/AttributeForm.vue';
import AttributeDeleteDialog from '../../ui/attribute/AttributeDeleteDialog.vue';
import AttributeRestoreDialog from '../../ui/attribute/AttributeRestoreDialog.vue';
import AttributeLoadError from '../../ui/attribute/AttributeLoadError.vue';

const { t } = useTranslation();

const route = useRoute();
const router = useRouter();
const id = computed(() => String(route.params.id));
const { data, error, isPending, isFetching, refetch } = useAttribute(id);
const { update, remove, restore, purge } = useAttributeMutations();
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
const restoringAttribute = ref<Attribute | null>(null);
const canShowAttribute = computed(
  () => data.value && (!error.value || getApiError(error.value, t).status !== 404),
);

async function save(input: AttributeInput): Promise<AttributeInput> {
  const attribute = await update.mutateAsync({ id: id.value, input });

  return { name: attribute.name };
}

function cancel() {
  if (pending.value) {
    return;
  }

  return router.push({
    name: 'attributes.list',
    query: isDeleted.value ? { status: 'deleted' } : {},
  });
}

watch(id, () => {
  deleting.value = false;
  restoringAttribute.value = null;
  restore.reset();
  remove.reset();
  purge.reset();
});

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
  if (!data.value || pending.value) {
    return;
  }

  const wasDeleted = isDeleted.value;

  try {
    await deletion.value.mutateAsync(data.value.id);
    deleting.value = false;
    await router.push({ name: 'attributes.list', query: wasDeleted ? { status: 'deleted' } : {} });
  } catch {
    // The confirmation dialog displays the mutation error.
  }
}
</script>

<template>
  <section class="flex flex-col gap-6" aria-labelledby="edit-attribute-title">
    <h1 id="edit-attribute-title" class="text-2xl font-semibold tracking-tight">
      {{ t('attributes.attribute.edit') }}
    </h1>
    <Badge v-if="isDeleted" variant="secondary">{{ t('attributes.attribute.deleted') }}</Badge>
    <AttributeLoadError v-if="error" :error="error" :pending="isFetching" @retry="refetch()" />
    <Skeleton
      v-if="isPending"
      class="h-36 w-full max-w-xl"
      role="status"
      :aria-label="t('attributes.attribute.loading')"
    />

    <template v-else-if="data && canShowAttribute">
      <Alert v-if="isDeleted">
        <AlertTitle>{{ t('attributes.attribute.deletedTitle') }}</AlertTitle>
        <AlertDescription>{{ t('attributes.attribute.deletedDescription') }}</AlertDescription>
      </Alert>

      <dl class="grid gap-2 text-sm">
        <div>
          <dt>{{ t('attributes.attribute.id') }}</dt>
          <dd>{{ data.id }}</dd>
        </div>
        <div>
          <dt>{{ t('attributes.attribute.createdAt') }}</dt>
          <dd>
            <time :datetime="data.created_at">{{
              new Date(data.created_at).toLocaleString('en')
            }}</time>
          </dd>
        </div>
        <div>
          <dt>{{ t('attributes.attribute.updatedAt') }}</dt>
          <dd>
            <time :datetime="data.updated_at">{{
              new Date(data.updated_at).toLocaleString('en')
            }}</time>
          </dd>
        </div>
        <div v-if="data.deleted_at">
          <dt>{{ t('attributes.attribute.deletedAt') }}</dt>
          <dd>
            <time :datetime="data.deleted_at">{{
              new Date(data.deleted_at).toLocaleString('en')
            }}</time>
          </dd>
        </div>
      </dl>

      <AttributeForm
        :key="`${data.id}:${isDeleted}`"
        :initial-name="data.name"
        require-changes
        :disabled="isDeleted || pending || deleting || !!restoringAttribute"
        :submit-label="t('common.saveChanges')"
        :on-save="save"
        @cancel="cancel"
      />

      <div class="flex flex-wrap gap-2">
        <Button v-if="isDeleted" :disabled="pending" @click="selectRestoreAttribute(data)">{{
          t('attributes.attribute.restore')
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
            isDeleted
              ? t('attributes.attribute.deletePermanently')
              : t('attributes.attribute.delete')
          }}</Button
        >
        <Button variant="outline" as-child
          ><RouterLink
            :to="{ name: 'attributes.list', query: isDeleted ? { status: 'deleted' } : {} }"
            >{{ t('attributes.attribute.back') }}</RouterLink
          ></Button
        >
      </div>
    </template>

    <AttributeRestoreDialog
      :attribute="restoringAttribute"
      :pending="restore.isPending.value"
      :error="restore.error.value"
      @cancel="restoringAttribute = null"
      @confirm="confirmRestore"
    />

    <AttributeDeleteDialog
      :attribute="deleting ? (data ?? null) : null"
      :pending="deletion.isPending.value"
      :error="deletion.error.value"
      @cancel="deleting = false"
      @confirm="confirmDelete"
    />
  </section>
</template>
