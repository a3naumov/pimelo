<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { FolderTreeIcon } from '@lucide/vue';
import { WorkspacePlaceholder } from '@/shared/ui/workspace';

const { t } = useTranslation();
</script>

<template>
  <WorkspacePlaceholder
    :title="t('catalog.category.title')"
    :description="t('catalog.category.description')"
    :icon="FolderTreeIcon"
  />
</template>
