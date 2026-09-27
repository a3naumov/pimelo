<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { getApiError } from '@/shared/api/errors';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { Button } from '@/shared/ui/button';

const { t } = useTranslation();

const props = defineProps<{ error: unknown; pending?: boolean }>();
defineEmits<{ retry: [] }>();
const details = computed(() => getApiError(props.error, t));
</script>

<template>
  <Alert variant="destructive">
    <AlertTitle>{{
      details.status === 404 ? t('catalog.product.error.notFound') : t('catalog.product.error.load')
    }}</AlertTitle>
    <AlertDescription>
      <p>{{ details.message }}</p>
      <Button v-if="details.status === 404" variant="outline" as-child
        ><RouterLink :to="{ name: 'catalog.products' }">{{
          t('catalog.product.back')
        }}</RouterLink></Button
      >
      <Button v-else variant="outline" :disabled="pending" @click="$emit('retry')">{{
        t('common.tryAgain')
      }}</Button>
    </AlertDescription>
  </Alert>
</template>
