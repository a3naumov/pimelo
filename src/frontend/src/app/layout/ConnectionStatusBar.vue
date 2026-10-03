<script setup lang="ts">
import { WifiOffIcon } from '@lucide/vue';
import { useTranslation } from '@/shared/i18n';
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';

defineProps<{ connectionProblem: boolean; services: readonly string[] }>();
const { t } = useTranslation();
</script>

<template>
  <Alert variant="destructive" class="mb-4" data-testid="connection-status">
    <WifiOffIcon aria-hidden="true" />
    <AlertTitle>{{ t('app.availability.warning') }}</AlertTitle>
    <AlertDescription>
      {{
        connectionProblem
          ? t('app.availability.connectionProblem')
          : t('app.availability.serviceProblem', { services: services.join(', ').toUpperCase() })
      }}
    </AlertDescription>
  </Alert>
</template>
