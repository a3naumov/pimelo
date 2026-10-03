<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { WifiOffIcon } from '@lucide/vue';
import { useTranslation } from '@/shared/i18n';
import { providePageInteraction } from '@/shared/composable/usePageInteraction';
import { Button } from '@/shared/ui/button';
import {
  Empty,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
  EmptyDescription,
  EmptyContent,
} from '@/shared/ui/empty';

const props = defineProps<{
  available: boolean;
  checking: boolean;
  fetching: boolean;
  routeKey: string;
}>();
defineEmits<{ retry: [] }>();
const { t } = useTranslation();
const retainedRoute = ref<string>();
const heading = ref<HTMLHeadingElement>();

watch(
  () => [props.available, props.routeKey] as const,
  ([available, key]) => {
    if (available) {
      retainedRoute.value = key;
    } else if (retainedRoute.value !== key) {
      retainedRoute.value = undefined;
    }
  },
  { immediate: true, flush: 'sync' },
);

const renderPage = computed(() => props.available || retainedRoute.value === props.routeKey);
providePageInteraction(computed(() => props.available));

watch(
  () => props.available,
  async (available) => {
    if (!available) {
      await nextTick();
      heading.value?.focus();
    }
  },
);
</script>

<template>
  <div
    v-if="renderPage"
    v-show="available"
    :inert="!available"
    :aria-hidden="!available"
    class="flex flex-1 flex-col"
  >
    <slot />
  </div>
  <Empty v-if="!available" class="flex-1" data-testid="service-unavailable">
    <EmptyHeader>
      <EmptyMedia variant="icon"><WifiOffIcon aria-hidden="true" /></EmptyMedia>
      <EmptyTitle
        ><h1 ref="heading" tabindex="-1">
          {{ checking ? t('app.availability.checking') : t('app.availability.unavailableTitle') }}
        </h1></EmptyTitle
      >
      <EmptyDescription>{{
        checking
          ? t('app.availability.checkingDescription')
          : t('app.availability.unavailableDescription')
      }}</EmptyDescription>
    </EmptyHeader>
    <EmptyContent>
      <div class="flex flex-wrap justify-center gap-2">
        <Button as-child
          ><RouterLink to="/">{{ t('app.availability.goHome') }}</RouterLink></Button
        >
        <Button variant="outline" :disabled="fetching" @click="$emit('retry')">{{
          t('app.availability.checkAgain')
        }}</Button>
      </div>
    </EmptyContent>
  </Empty>
</template>
