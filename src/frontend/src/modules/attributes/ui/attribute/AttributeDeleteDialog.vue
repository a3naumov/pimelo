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
import type { Attribute } from '../../model/attribute/schemas';

const { t } = useTranslation();

const props = defineProps<{ attribute: Attribute | null; pending: boolean; error: unknown }>();
const permanent = computed(() => !!props.attribute?.deleted_at);
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
        <AlertDialogTitle>{{
          permanent
            ? t('attributes.attribute.confirm.permanentDelete')
            : t('attributes.attribute.confirm.delete')
        }}</AlertDialogTitle>
        <AlertDialogDescription>
          <template v-if="permanent">
            {{
              t('attributes.attribute.confirm.permanentDeleteDescription', {
                name: attribute?.name ?? '',
              })
            }}
          </template>
          <template v-else>
            {{
              t('attributes.attribute.confirm.deleteDescription', { name: attribute?.name ?? '' })
            }}
          </template>
        </AlertDialogDescription>
      </AlertDialogHeader>
      <Alert v-if="error" variant="destructive">
        <AlertTitle>{{ t('attributes.attribute.error.delete') }}</AlertTitle>
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
                ? t('attributes.attribute.deletePermanently')
                : t('attributes.attribute.delete')
          }}
        </Button>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
