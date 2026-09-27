<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
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

const { t } = useTranslation();

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
          permanent
            ? t('catalog.product.confirm.permanentDelete')
            : t('catalog.product.confirm.delete')
        }}</AlertDialogTitle>
        <AlertDialogDescription>
          <template v-if="permanent">
            {{
              t('catalog.product.confirm.permanentDeleteDescription', { sku: product?.sku ?? '' })
            }}
          </template>
          <template v-else>
            {{ t('catalog.product.confirm.deleteDescription', { sku: product?.sku ?? '' }) }}
          </template>
        </AlertDialogDescription>
      </AlertDialogHeader>
      <Alert v-if="error" variant="destructive">
        <AlertTitle>{{ t('catalog.product.error.delete') }}</AlertTitle>
        <AlertDescription>{{ getApiError(error, t).message }}</AlertDescription>
      </Alert>
      <AlertDialogFooter>
        <AlertDialogCancel :disabled="pending">{{ t('common.cancel') }}</AlertDialogCancel>
        <Button variant="destructive" :disabled="pending" @click="emit('confirm')">
          <Spinner v-if="pending" data-icon="inline-start" />
          {{
            pending
              ? t('common.deleting')
              : permanent
                ? t('catalog.product.deletePermanently')
                : t('catalog.product.delete')
          }}
        </Button>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
