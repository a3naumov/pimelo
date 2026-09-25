<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

import { PanelLeftIcon } from '@lucide/vue';
import { cn } from '@/shared/lib/utils';
import { Button } from '@/shared/ui/button';
import { useSidebar } from './utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();

const { toggleSidebar, isMobile, openMobile, open } = useSidebar();
</script>

<template>
  <Button
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon-sm"
    :aria-expanded="isMobile ? openMobile : open"
    :class="cn('', props.class)"
    @click="toggleSidebar"
  >
    <PanelLeftIcon class="cn-rtl-flip" data-icon="inline-start" aria-hidden="true" />
    <span class="sr-only">Toggle Sidebar</span>
  </Button>
</template>
