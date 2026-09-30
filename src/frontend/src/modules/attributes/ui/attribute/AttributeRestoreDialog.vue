<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
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
import type { Attribute } from '../../model/attribute/schemas';

const { t } = useTranslation();

defineProps<{ attribute: Attribute | null; pending: boolean; error: unknown }>();
const emit = defineEmits<{ cancel: []; confirm: [] }>();
</script>

<template>
  <AlertDialog
    :open="!!attribute"
    @update:open="
      (open) => {
        if (!open && !pending) emit('cancel');
      }
    "
  >
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{{ t('attributes.attribute.confirm.restore') }}</AlertDialogTitle>
        <AlertDialogDescription>
          {{
            t('attributes.attribute.confirm.restoreDescription', { name: attribute?.name ?? '' })
          }}
        </AlertDialogDescription>
      </AlertDialogHeader>
      <Alert v-if="error" variant="destructive">
        <AlertTitle>{{ t('attributes.attribute.error.restore') }}</AlertTitle>
        <AlertDescription>{{ getApiError(error, t).message }}</AlertDescription>
      </Alert>
      <AlertDialogFooter>
        <AlertDialogCancel :disabled="pending">{{ t('common.cancel') }}</AlertDialogCancel>
        <Button :disabled="pending" @click="emit('confirm')">
          <Spinner v-if="pending" data-icon="inline-start" />
          {{ pending ? t('common.restoring') : t('attributes.attribute.restore') }}
        </Button>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
