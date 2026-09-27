<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { RouterLink, useRoute } from 'vue-router';
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/shared/ui/breadcrumb';
import { Separator } from '@/shared/ui/separator';
import { SidebarInset, SidebarTrigger, useSidebar } from '@/shared/ui/sidebar';

const { t } = useTranslation();
const { isMobile } = useSidebar();

const route = useRoute();
</script>

<template>
  <SidebarInset class="min-w-0">
    <header class="workspace-header flex h-[76px] shrink-0 items-center gap-3 px-4 sm:px-8">
      <template v-if="isMobile">
        <SidebarTrigger aria-controls="app-navigation" />
        <Separator orientation="vertical" class="my-4" />
      </template>
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem class="hidden sm:inline-flex">{{
            route.meta.groupKey ? t(route.meta.groupKey) : ''
          }}</BreadcrumbItem>
          <BreadcrumbSeparator class="hidden sm:block" />
          <template v-if="route.meta.parent">
            <BreadcrumbItem
              ><BreadcrumbLink as-child
                ><RouterLink :to="{ name: route.meta.parent.name }">{{
                  t(route.meta.parent.titleKey)
                }}</RouterLink></BreadcrumbLink
              ></BreadcrumbItem
            >
            <BreadcrumbSeparator />
          </template>
          <BreadcrumbItem>
            <BreadcrumbPage>{{ route.meta.titleKey ? t(route.meta.titleKey) : '' }}</BreadcrumbPage>
          </BreadcrumbItem>
        </BreadcrumbList>
      </Breadcrumb>
    </header>
    <Separator />
    <div class="flex flex-1 flex-col p-4 sm:p-8">
      <slot />
    </div>
  </SidebarInset>
</template>

<style scoped>
.workspace-header {
  background: var(--card);
}
.workspace-header :deep([data-slot='breadcrumb-list']) {
  font-size: 12px;
}
</style>
