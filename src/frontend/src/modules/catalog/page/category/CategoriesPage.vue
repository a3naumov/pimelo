<script setup lang="ts">
import { FolderTreeIcon } from '@lucide/vue';
import { WorkspacePlaceholder } from '@/shared/ui/workspace';
</script>

<template>
  <WorkspacePlaceholder
    title="Categories"
    description="Give your catalog a clear, organized structure."
    :icon="FolderTreeIcon"
  />
</template>
