<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { useRouter } from 'vue-router';
import { useAttributeMutations } from '../../model/attribute/queries';
import type { AttributeInput } from '../../model/attribute/schemas';
import AttributeForm from '../../ui/attribute/AttributeForm.vue';

const { t } = useTranslation();

const router = useRouter();
const { create } = useAttributeMutations();

async function save(input: AttributeInput) {
  const attribute = await create.mutateAsync(input);
  await router.push({ name: 'attributes.edit', params: { id: attribute.id } });

  return { name: attribute.name };
}
</script>

<template>
  <section class="flex flex-col gap-6" aria-labelledby="create-attribute-title">
    <h1 id="create-attribute-title" class="text-2xl font-semibold tracking-tight">
      {{ t('attributes.attribute.create') }}
    </h1>
    <AttributeForm
      :submit-label="t('attributes.attribute.create')"
      :on-save="save"
      @cancel="router.push({ name: 'attributes.list' })"
    />
  </section>
</template>
