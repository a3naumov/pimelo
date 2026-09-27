<script lang="ts" setup>
import { useTranslation } from '@/shared/i18n';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/shared/lib/utils';

const { t } = useTranslation();

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <nav :aria-label="t('common.breadcrumb')" data-slot="breadcrumb" :class="cn('', props.class)">
    <slot />
  </nav>
</template>
