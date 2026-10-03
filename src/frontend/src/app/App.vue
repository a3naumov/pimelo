<script setup lang="ts">
import { computed } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import { useHealthMonitor } from '@/shared/composable/useHealthMonitor';
import { usePimAvailability } from './composable/usePimAvailability';
import ConnectionStatusBar from './layout/ConnectionStatusBar.vue';
import ServiceAvailabilityBoundary from './layout/ServiceAvailabilityBoundary.vue';
import { SidebarProvider } from '@/shared/ui/sidebar';
import AppSidebar from './layout/AppSidebar.vue';
import AppContent from './layout/AppContent.vue';

const router = useRouter();
const route = useRoute();
const requiredServices = [
  ...new Set(router.getRoutes().flatMap((record) => record.meta.requiredServices ?? [])),
];
const monitor = useHealthMonitor(requiredServices);
usePimAvailability(() => monitor.isAvailable('pim'));
const required = computed(() => route.meta.requiredServices ?? []);
const available = computed(() => required.value.every(monitor.isAvailable));
const checking = computed(
  () =>
    monitor.state.gateway.phase === 'checking' ||
    required.value.some((name) => monitor.state.services[name]?.phase === 'checking'),
);
</script>

<template>
  <SidebarProvider :desktop-collapsible="false">
    <AppSidebar
      :unavailable-services="monitor.hasIssues.value ? monitor.unavailableServices.value : []"
    />
    <AppContent>
      <ConnectionStatusBar
        v-if="monitor.hasIssues.value"
        :connection-problem="monitor.state.gateway.phase !== 'available'"
        :services="monitor.unavailableServices.value"
      />
      <ServiceAvailabilityBoundary
        :available="available"
        :checking="checking"
        :fetching="monitor.isFetching.value"
        :route-key="route.fullPath"
        @retry="monitor.checkNow"
      >
        <RouterView />
      </ServiceAvailabilityBoundary>
    </AppContent>
  </SidebarProvider>
</template>
