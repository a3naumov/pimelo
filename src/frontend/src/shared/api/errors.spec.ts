import { AxiosError, AxiosHeaders } from 'axios';
import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { getApiError as parseApiError } from './errors';
import { createAppI18n } from '@/shared/i18n';

function getApiError(error: unknown) {
  const i18n = createAppI18n();

  return parseApiError(error, i18n.global.t);
}

function httpError(status: number, data: unknown) {
  return new AxiosError('Request failed', undefined, undefined, undefined, {
    data,
    status,
    statusText: 'Error',
    headers: {},
    config: { headers: new AxiosHeaders() },
  });
}

describe('API error presentation', () => {
  it('reads SKU conflicts from the backend error envelope', () => {
    expect(
      getApiError(httpError(409, { error: 'A product with this SKU already exists.' })),
    ).toMatchObject({ status: 409, message: 'A product with this SKU already exists.' });
  });

  it('maps Symfony violations without exposing debug details', () => {
    const error = getApiError(
      httpError(422, {
        detail: 'Internal stack trace',
        violations: [{ propertyPath: 'sku', title: 'SKU must be a non-empty string.' }],
      }),
    );
    expect(error.fields).toEqual({ sku: 'SKU must be a non-empty string.' });
    expect(error.message).toBe('Please check the highlighted fields.');
  });

  it.each([500, 502, 503])('hides internal messages for status %i', (status) => {
    expect(
      getApiError(httpError(status, { error: 'Database password', detail: 'Stack trace' })).message,
    ).toBe('The server could not complete the request. Please try again.');
  });

  it('handles network failures and timeouts', () => {
    expect(getApiError(new AxiosError('timeout', 'ECONNABORTED')).message).toBe(
      'Could not reach the server. Check your connection and try again.',
    );
  });

  it('handles malformed error bodies and invalid successful responses', () => {
    expect(getApiError(httpError(502, '<html>Bad gateway</html>')).fields).toEqual({});
    const result = z.string().safeParse(10);
    expect(getApiError(result.error).message).toBe(
      'The server returned an invalid response. Please try again.',
    );
  });
});
