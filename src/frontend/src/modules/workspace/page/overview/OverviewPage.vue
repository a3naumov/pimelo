<script setup lang="ts">
import { LayoutDashboardIcon } from '@lucide/vue';
import { WorkspacePlaceholder } from '@/shared/ui/workspace';
</script>

<template>
  <WorkspacePlaceholder
    title="Overview"
    description="A clear home for your product information."
    :icon="LayoutDashboardIcon"
  />
</template>
