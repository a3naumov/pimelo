<script setup lang="ts">
import { PackageIcon } from '@lucide/vue';
import { WorkspacePlaceholder } from '@/shared/ui/workspace';
</script>

<template>
  <WorkspacePlaceholder
    title="Products"
    description="Keep your product information together in one place."
    :icon="PackageIcon"
  />
</template>
