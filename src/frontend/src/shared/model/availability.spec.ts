import { describe, expect, it } from 'vitest';
import type { HealthcheckResponse } from '@/shared/api/healthcheck';
import {
  createAvailabilityState,
  hasAvailabilityIssues,
  isServiceAvailable,
  observeConnectionFailure,
  observeHealthcheck,
} from './availability';

const healthy: HealthcheckResponse = {
  service: 'gateway',
  status: 'ok',
  services: { pim: { status: 'ok' } },
};
const failed: HealthcheckResponse = {
  service: 'gateway',
  status: 'degraded',
  services: { pim: { status: 'unavailable' } },
};

describe('Service availability transitions', () => {
  it('does not allow access or show a failure before the first check', () => {
    const state = createAvailabilityState(['pim']);

    expect(isServiceAvailable(state, 'pim')).toBe(false);
    expect(hasAvailabilityIssues(state)).toBe(false);
    observeHealthcheck(state, healthy);
    expect(isServiceAvailable(state, 'pim')).toBe(true);
  });

  it('blocks immediately and requires two consecutive successes to recover', () => {
    const state = createAvailabilityState(['pim']);
    observeHealthcheck(state, healthy);
    observeHealthcheck(state, failed);
    expect(isServiceAvailable(state, 'pim')).toBe(false);
    expect(hasAvailabilityIssues(state)).toBe(true);
    observeHealthcheck(state, healthy);
    expect(isServiceAvailable(state, 'pim')).toBe(false);
    observeHealthcheck(state, failed);
    observeHealthcheck(state, healthy);
    expect(isServiceAvailable(state, 'pim')).toBe(false);
    observeHealthcheck(state, healthy);
    expect(isServiceAvailable(state, 'pim')).toBe(true);
    expect(hasAvailabilityIssues(state)).toBe(false);
  });

  it('treats a missing required service as unavailable', () => {
    const state = createAvailabilityState(['pim']);
    observeHealthcheck(state, { service: 'gateway', status: 'ok', services: {} });

    expect(state.gateway.phase).toBe('available');
    expect(isServiceAvailable(state, 'pim')).toBe(false);
    expect(hasAvailabilityIssues(state)).toBe(true);
  });

  it('keeps independent services available during a partial outage', () => {
    const state = createAvailabilityState(['pim', 'search']);
    observeHealthcheck(state, {
      ...failed,
      services: { ...failed.services, search: { status: 'ok' } },
    });

    expect(isServiceAvailable(state, 'pim')).toBe(false);
    expect(isServiceAvailable(state, 'search')).toBe(true);
  });

  it('closes all dependent pages when the gateway connection fails', () => {
    const state = createAvailabilityState(['pim']);
    observeHealthcheck(state, healthy);
    observeConnectionFailure(state);
    expect(isServiceAvailable(state, 'pim')).toBe(false);
    observeHealthcheck(state, healthy);
    expect(isServiceAvailable(state, 'pim')).toBe(false);
    observeHealthcheck(state, healthy);
    expect(isServiceAvailable(state, 'pim')).toBe(true);
  });
});
