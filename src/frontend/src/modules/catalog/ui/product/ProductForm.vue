<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { Button } from '@/shared/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/shared/ui/field';
import { Input } from '@/shared/ui/input';
import { Spinner } from '@/shared/ui/spinner';
import type { ProductInput } from '../../model/product/schemas';
import { useProductForm } from '../../form/product/useProductForm';

const { t } = useTranslation();

const props = withDefaults(
  defineProps<{
    initialSku?: string;
    requireChanges?: boolean;
    disabled?: boolean;
    submitLabel: string;
    onSave: (input: ProductInput) => Promise<ProductInput>;
  }>(),
  { initialSku: '' },
);
const emit = defineEmits<{ cancel: [] }>();
const {
  formatValidationError,
  form,
  error,
  isSubmitting,
  fieldsDisabled,
  submitDisabled,
  submit,
  changeSku,
} = useProductForm(props);
</script>

<template>
  <form class="flex w-full max-w-xl flex-col gap-6" novalidate @submit.prevent="submit">
    <Alert v-if="error && !error.fields.sku" variant="destructive">
      <AlertTitle>{{ t('catalog.product.error.save') }}</AlertTitle>
      <AlertDescription>{{ error.message }}</AlertDescription>
    </Alert>
    <FieldGroup>
      <form.Field name="sku">
        <template #default="{ field }">
          <Field
            :data-invalid="field.state.meta.errors.length > 0 || !!error?.fields.sku"
            :data-disabled="fieldsDisabled"
          >
            <FieldLabel for="product-sku">{{ t('catalog.product.sku') }}</FieldLabel>
            <Input
              id="product-sku"
              name="sku"
              autocomplete="off"
              :model-value="field.state.value"
              :disabled="fieldsDisabled"
              :aria-invalid="field.state.meta.errors.length > 0 || !!error?.fields.sku"
              aria-describedby="sku-description sku-errors"
              @blur="field.handleBlur"
              @update:model-value="changeSku($event, field.handleChange)"
            />
            <FieldDescription id="sku-description">{{
              t('catalog.product.skuDescription')
            }}</FieldDescription>
            <FieldError
              id="sku-errors"
              :errors="[
                ...field.state.meta.errors.map(formatValidationError),
                ...(error?.fields.sku ? [{ message: error.fields.sku }] : []),
              ]"
            />
          </Field>
        </template>
      </form.Field>
    </FieldGroup>
    <div class="flex flex-wrap gap-2">
      <Button type="submit" :disabled="submitDisabled">
        <Spinner v-if="isSubmitting" data-icon="inline-start" />
        {{ isSubmitting ? t('common.saving') : submitLabel }}
      </Button>
      <Button type="button" variant="outline" :disabled="isSubmitting" @click="emit('cancel')">{{
        t('common.cancel')
      }}</Button>
    </div>
  </form>
</template>
