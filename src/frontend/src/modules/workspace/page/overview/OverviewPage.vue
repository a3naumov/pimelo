<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { LayoutDashboardIcon } from '@lucide/vue';
import { WorkspacePlaceholder } from '@/shared/ui/workspace';

const { t } = useTranslation();
</script>

<template>
  <WorkspacePlaceholder
    :title="t('workspace.overview.title')"
    :description="t('workspace.overview.description')"
    :icon="LayoutDashboardIcon"
  />
</template>
