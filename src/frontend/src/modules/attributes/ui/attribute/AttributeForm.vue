<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { Button } from '@/shared/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/shared/ui/field';
import { Input } from '@/shared/ui/input';
import { Spinner } from '@/shared/ui/spinner';
import type { AttributeInput } from '../../model/attribute/schemas';
import { useAttributeForm } from '../../form/attribute/useAttributeForm';

const { t } = useTranslation();

const props = withDefaults(
  defineProps<{
    initialName?: string;
    requireChanges?: boolean;
    disabled?: boolean;
    submitLabel: string;
    onSave: (input: AttributeInput) => Promise<AttributeInput>;
  }>(),
  { initialName: '' },
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
  changeName,
} = useAttributeForm(props);
</script>

<template>
  <form class="flex w-full max-w-xl flex-col gap-6" novalidate @submit.prevent="submit">
    <Alert v-if="error && !error.fields.name" variant="destructive">
      <AlertTitle>{{ t('attributes.attribute.error.save') }}</AlertTitle>
      <AlertDescription>{{ error.message }}</AlertDescription>
    </Alert>
    <FieldGroup>
      <form.Field name="name">
        <template #default="{ field }">
          <Field
            :data-invalid="field.state.meta.errors.length > 0 || !!error?.fields.name"
            :data-disabled="fieldsDisabled"
          >
            <FieldLabel for="attribute-name">{{ t('attributes.attribute.name') }}</FieldLabel>
            <Input
              id="attribute-name"
              name="name"
              autocomplete="off"
              :model-value="field.state.value"
              :disabled="fieldsDisabled"
              :aria-invalid="field.state.meta.errors.length > 0 || !!error?.fields.name"
              aria-describedby="name-description name-errors"
              @blur="field.handleBlur"
              @update:model-value="changeName($event, field.handleChange)"
            />
            <FieldDescription id="name-description">{{
              t('attributes.attribute.nameDescription')
            }}</FieldDescription>
            <FieldError
              id="name-errors"
              :errors="[
                ...field.state.meta.errors.map(formatValidationError),
                ...(error?.fields.name ? [{ message: error.fields.name }] : []),
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
