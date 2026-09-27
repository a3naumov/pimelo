<script setup lang="ts">
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

defineProps<{ product: Product | null; pending: boolean; error: unknown }>();
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
        <AlertDialogTitle>Restore product?</AlertDialogTitle>
        <AlertDialogDescription>
          {{ product?.sku }} will return to the active catalog with its SKU and category links.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <Alert v-if="error" variant="destructive">
        <AlertTitle>Could not restore product</AlertTitle>
        <AlertDescription>{{ getApiError(error).message }}</AlertDescription>
      </Alert>
      <AlertDialogFooter>
        <AlertDialogCancel :disabled="pending">Cancel</AlertDialogCancel>
        <Button :disabled="pending" @click="emit('confirm')">
          <Spinner v-if="pending" data-icon="inline-start" />
          {{ pending ? 'Restoring…' : 'Restore product' }}
        </Button>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
