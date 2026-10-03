import axios from 'axios';
import { z } from 'zod';
import { getGatewayOrigin } from './client';

export const healthcheckSchema = z
  .object({
    service: z.literal('gateway'),
    status: z.enum(['ok', 'degraded']),
    services: z.record(z.string(), z.object({ status: z.enum(['ok', 'unavailable']) })),
  })
  .refine(
    (value) =>
      (value.status === 'ok') ===
      Object.values(value.services).every((service) => service.status === 'ok'),
  );

export type HealthcheckResponse = z.infer<typeof healthcheckSchema>;
export const healthcheckRoutes = { status: '/healthcheck' } as const;

export const gatewayClient = axios.create({
  baseURL: getGatewayOrigin(import.meta.env.VITE_GATEWAY_URL),
  timeout: 5000,
  headers: { Accept: 'application/json' },
});

export async function getHealthcheck(signal?: AbortSignal): Promise<HealthcheckResponse> {
  const response = await gatewayClient.get<HealthcheckResponse>(healthcheckRoutes.status, {
    signal,
    validateStatus: (status) => status === 200 || status === 503,
  });
  const data = healthcheckSchema.parse(response.data);

  if ((response.status === 200) !== (data.status === 'ok')) {
    throw new Error('Inconsistent healthcheck response.');
  }

  return data;
}
