import { afterEach, describe, expect, it, vi } from 'vitest';
import { gatewayClient, getHealthcheck } from './healthcheck';
import { z } from 'zod';

afterEach(() => vi.restoreAllMocks());

describe('Gateway healthcheck contract', () => {
  it('uses the gateway origin without a PIM prefix and passes cancellation', async () => {
    const data = { service: 'gateway', status: 'ok', services: { pim: { status: 'ok' } } };
    const get = vi.spyOn(gatewayClient, 'get').mockResolvedValue({ status: 200, data });
    const signal = new AbortController().signal;

    await expect(getHealthcheck(signal)).resolves.toEqual(data);
    expect(get).toHaveBeenCalledWith('/healthcheck', expect.objectContaining({ signal }));
    expect(gatewayClient.defaults.baseURL).not.toContain('/pim');
    expect(gatewayClient.defaults.timeout).toBe(5000);
  });

  it('accepts a structured 503 as a dependency report', async () => {
    const data = {
      service: 'gateway',
      status: 'degraded',
      services: { pim: { status: 'unavailable' } },
    };
    vi.spyOn(gatewayClient, 'get').mockResolvedValue({ status: 503, data });

    await expect(getHealthcheck()).resolves.toEqual(data);
  });

  it.each([
    '<html>proxy error</html>',
    { service: 'gateway', status: 'ok', services: { pim: { status: 'unavailable' } } },
    { service: 'gateway', status: 'ok' },
  ])('rejects malformed or contradictory response %j', async (data) => {
    vi.spyOn(gatewayClient, 'get').mockResolvedValue({ status: 200, data });

    await expect(getHealthcheck()).rejects.toThrow(z.ZodError);
  });
});
