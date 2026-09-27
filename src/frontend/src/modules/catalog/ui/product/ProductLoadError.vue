<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { getApiError } from '@/shared/api/errors';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { Button } from '@/shared/ui/button';

const props = defineProps<{ error: unknown; pending?: boolean }>();
defineEmits<{ retry: [] }>();
const details = computed(() => getApiError(props.error));
</script>

<template>
  <Alert variant="destructive">
    <AlertTitle>{{
      details.status === 404 ? 'Product not found' : 'Could not load products'
    }}</AlertTitle>
    <AlertDescription>
      <p>{{ details.message }}</p>
      <Button v-if="details.status === 404" variant="outline" as-child
        ><RouterLink :to="{ name: 'catalog.products' }">Back to products</RouterLink></Button
      >
      <Button v-else variant="outline" :disabled="pending" @click="$emit('retry')"
        >Try again</Button
      >
    </AlertDescription>
  </Alert>
</template>
