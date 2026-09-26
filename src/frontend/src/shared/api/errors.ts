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

export function getApiError(error: unknown): ApiError {
  if (error instanceof z.ZodError) {
    return { message: 'The server returned an invalid response. Please try again.', fields: {} };
  }
  if (!axios.isAxiosError(error)) {
    return { message: 'Something went wrong. Please try again.', fields: {} };
  }
  const status = error.response?.status;
  if (!status) {
    return {
      message: 'Could not reach the server. Check your connection and try again.',
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
  let message = 'The request could not be completed. Please try again.';
  if (status >= 500) message = 'The server could not complete the request. Please try again.';
  else if (status === 404) message = 'This resource was not found. It may have been deleted.';
  else if (status === 422) message = 'Please check the highlighted fields.';
  else if (parsed.success && parsed.data.error) message = parsed.data.error;

  return { status, message, fields };
}
