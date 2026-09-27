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
import { SidebarInset, SidebarTrigger } from '@/shared/ui/sidebar';

const { t } = useTranslation();

const route = useRoute();
</script>

<template>
  <SidebarInset class="min-w-0">
    <header class="flex h-14 shrink-0 items-center gap-3 px-4 sm:px-6">
      <SidebarTrigger aria-controls="app-navigation" />
      <Separator orientation="vertical" class="my-4" />
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
    <div class="flex flex-1 flex-col p-4 sm:p-6 lg:p-8">
      <slot />
    </div>
  </SidebarInset>
</template>
