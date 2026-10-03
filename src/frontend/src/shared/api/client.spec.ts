import { afterEach, describe, expect, it, vi } from 'vitest';

afterEach(() => {
  vi.unstubAllEnvs();
  vi.resetModules();
});

describe('API client configuration', () => {
  it.each([
    ['http://localhost', 'http://localhost/pim/web'],
    ['http://localhost:8088/', 'http://localhost:8088/pim/web'],
    ['https://api.example.com/', 'https://api.example.com/pim/web'],
  ])('sends requests through the configured Caddy origin %s', async (origin, baseURL) => {
    vi.stubEnv('VITE_GATEWAY_URL', origin);
    const { apiClient } = await import('./client');

    expect(apiClient.getUri({ url: '/products/' })).toBe(`${baseURL}/products/`);
  });

  it.each([
    undefined,
    '',
    'invalid',
    '/pim/web',
    'ftp://api.example.com',
    'https://user:password@api.example.com',
    'https://api.example.com/pim/web',
    'https://api.example.com?query=1',
    'https://api.example.com#fragment',
  ])('rejects a missing or invalid gateway origin: %s', async (origin) => {
    vi.stubEnv('VITE_GATEWAY_URL', origin);

    await expect(import('./client')).rejects.toThrow('VITE_GATEWAY_URL must be an HTTP(S) origin');
  });
});
