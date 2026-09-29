<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { getApiError } from '@/shared/api/errors';
import { Button } from '@/shared/ui/button';
import { Spinner } from '@/shared/ui/spinner';
import { Alert, AlertTitle, AlertDescription } from '@/shared/ui/alert';
import {
  AlertDialog,
  AlertDialogContent,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogCancel,
} from '@/shared/ui/alert-dialog';
defineProps<{
  open: boolean;
  title: string;
  description: string;
  action: string;
  pending: boolean;
  error: unknown;
  destructive?: boolean;
}>();
const emit = defineEmits<{ cancel: []; confirm: [] }>();
const { t } = useTranslation();
</script>
<template>
  <AlertDialog :open="open" @update:open="!$event && !pending && emit('cancel')">
    <AlertDialogContent @escape-key-down="pending && $event.preventDefault()">
      <AlertDialogHeader
        ><AlertDialogTitle>{{ title }}</AlertDialogTitle
        ><AlertDialogDescription class="break-words">{{
          description
        }}</AlertDialogDescription></AlertDialogHeader
      >
      <Alert v-if="error" variant="destructive"
        ><AlertTitle>{{ t('catalog.category.error') }}</AlertTitle
        ><AlertDescription>{{ getApiError(error, t).message }}</AlertDescription></Alert
      >
      <AlertDialogFooter
        ><AlertDialogCancel :disabled="pending">{{ t('common.cancel') }}</AlertDialogCancel
        ><Button
          :variant="destructive === false ? 'default' : 'destructive'"
          :disabled="pending"
          @click="emit('confirm')"
          ><Spinner v-if="pending" data-icon="inline-start" />{{ action }}</Button
        ></AlertDialogFooter
      >
    </AlertDialogContent>
  </AlertDialog>
</template>
