<script setup lang="ts">
import { computed } from 'vue';
import { getApiError } from '@/shared/api/errors';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { Button } from '@/shared/ui/button';
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/shared/ui/alert-dialog';
import { Spinner } from '@/shared/ui/spinner';
import type { Product } from '../../model/product/schemas';

const props = defineProps<{ product: Product | null; pending: boolean; error: unknown }>();
const permanent = computed(() => !!props.product?.deleted_at);
const emit = defineEmits<{ cancel: []; confirm: [] }>();
</script>

<template>
  <AlertDialog
    :open="!!product"
    @update:open="
      (open) => {
        if (!open && !pending) emit('cancel');
      }
    "
  >
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{
          permanent ? 'Permanently delete product?' : 'Delete product?'
        }}</AlertDialogTitle>
        <AlertDialogDescription>
          <template v-if="permanent">
            {{ product?.sku }} and its category links will be permanently deleted. This cannot be
            undone. Its SKU will become available for reuse.
          </template>
          <template v-else>
            {{ product?.sku }} will be removed from the catalog. Its SKU will remain reserved.
          </template>
        </AlertDialogDescription>
      </AlertDialogHeader>
      <Alert v-if="error" variant="destructive">
        <AlertTitle>Could not delete product</AlertTitle>
        <AlertDescription>{{ getApiError(error).message }}</AlertDescription>
      </Alert>
      <AlertDialogFooter>
        <AlertDialogCancel :disabled="pending">Cancel</AlertDialogCancel>
        <Button variant="destructive" :disabled="pending" @click="emit('confirm')">
          <Spinner v-if="pending" data-icon="inline-start" />
          {{ pending ? 'Deleting…' : permanent ? 'Delete permanently' : 'Delete product' }}
        </Button>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
