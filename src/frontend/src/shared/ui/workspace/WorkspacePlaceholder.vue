<script setup lang="ts">
import type { Component } from 'vue';
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/shared/ui/empty';

defineProps<{
  title: string;
  description: string;
  icon: Component;
}>();
</script>

<template>
  <section class="flex flex-1 flex-col gap-6" aria-labelledby="page-title">
    <div class="flex flex-col gap-1">
      <h1 id="page-title" class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
      <p class="text-sm text-muted-foreground">{{ description }}</p>
    </div>
    <Empty class="min-h-80 border">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <component :is="icon" aria-hidden="true" />
        </EmptyMedia>
        <EmptyTitle>Hello world</EmptyTitle>
        <EmptyDescription>Welcome to your Pimelo workspace.</EmptyDescription>
      </EmptyHeader>
    </Empty>
  </section>
</template>
