<script setup lang="ts">
import { useRoute } from 'vue-router';
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/shared/ui/breadcrumb';
import { Separator } from '@/shared/ui/separator';
import { SidebarInset, SidebarTrigger } from '@/shared/ui/sidebar';

const route = useRoute();
</script>

<template>
  <SidebarInset class="min-w-0">
    <header class="flex h-14 shrink-0 items-center gap-3 px-4 sm:px-6">
      <SidebarTrigger aria-controls="app-navigation" />
      <Separator orientation="vertical" class="my-4" />
      <Breadcrumb>
        <BreadcrumbList>
          <BreadcrumbItem class="hidden sm:inline-flex">{{ route.meta.group }}</BreadcrumbItem>
          <BreadcrumbSeparator class="hidden sm:block" />
          <BreadcrumbItem>
            <BreadcrumbPage>{{ route.meta.title }}</BreadcrumbPage>
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
