import type { Translate } from '@/shared/i18n';
import axios from 'axios';
import { z } from 'zod';

const errorResponseSchema = z.object({
  error: z.string().optional(),
  violations: z.array(z.object({ propertyPath: z.string(), title: z.string() })).optional(),
});

export interface ApiError {
  message: string;
  status?: number;
  fields: Record<string, string>;
}

export function getApiError(error: unknown, t: Translate): ApiError {
  if (error instanceof z.ZodError) {
    return { message: t('common.error.invalidResponse'), fields: {} };
  }

  if (!axios.isAxiosError(error)) {
    return { message: t('common.error.unexpected'), fields: {} };
  }

  const status = error.response?.status;

  if (!status) {
    return {
      message: t('common.error.network'),
      fields: {},
    };
  }

  const parsed = errorResponseSchema.safeParse(error.response?.data);
  const fields = Object.fromEntries(
    (parsed.success ? (parsed.data.violations ?? []) : []).map(({ propertyPath, title }) => [
      propertyPath,
      title,
    ]),
  );
  let message = t('common.error.request');

  if (status >= 500) {
    message = t('common.error.server');
  } else if (status === 404) {
    message = t('common.error.notFound');
  } else if (status === 422) {
    message = t('common.validation.invalid');
  } else if (parsed.success && parsed.data.error) {
    message = parsed.data.error;
  }

  return { status, message, fields };
}
