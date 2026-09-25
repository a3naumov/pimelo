<script setup lang="ts">
import { RouterView } from 'vue-router';
import { SidebarProvider } from '@/shared/ui/sidebar';
import AppSidebar from './layout/AppSidebar.vue';
import AppContent from './layout/AppContent.vue';
</script>

<template>
  <SidebarProvider>
    <AppSidebar />
    <AppContent>
      <RouterView />
    </AppContent>
  </SidebarProvider>
</template>
