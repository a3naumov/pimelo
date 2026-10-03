import type { HealthcheckResponse } from '@/shared/api/healthcheck';

export type AvailabilityPhase = 'checking' | 'available' | 'unavailable' | 'recovering';
export interface Availability {
  phase: AvailabilityPhase;
  successes: number;
}
export interface AvailabilityState {
  gateway: Availability;
  services: Record<string, Availability>;
}

export function createAvailabilityState(requiredServices: readonly string[]): AvailabilityState {
  return {
    gateway: { phase: 'checking', successes: 0 },
    services: Object.fromEntries(
      requiredServices.map((name) => [name, { phase: 'checking', successes: 0 }]),
    ),
  };
}

function observe(current: Availability, healthy: boolean): void {
  if (!healthy) {
    current.phase = 'unavailable';
    current.successes = 0;

    return;
  }

  if (current.phase === 'checking' || current.phase === 'available') {
    current.phase = 'available';
    current.successes = 2;

    return;
  }

  current.successes += 1;
  current.phase = current.successes >= 2 ? 'available' : 'recovering';
}

export function observeHealthcheck(state: AvailabilityState, result: HealthcheckResponse): void {
  observe(state.gateway, true);

  for (const name of new Set([...Object.keys(state.services), ...Object.keys(result.services)])) {
    const current = state.services[name] ?? { phase: 'checking', successes: 0 };
    observe(current, result.services[name]?.status === 'ok');
    state.services[name] = current;
  }
}

export function observeConnectionFailure(state: AvailabilityState): void {
  observe(state.gateway, false);

  for (const service of Object.values(state.services)) {
    observe(service, false);
  }
}

export function isServiceAvailable(state: AvailabilityState, name: string): boolean {
  return state.gateway.phase === 'available' && state.services[name]?.phase === 'available';
}

export function hasAvailabilityIssues(state: AvailabilityState): boolean {
  return [state.gateway, ...Object.values(state.services)].some(
    (value) => value.phase === 'unavailable' || value.phase === 'recovering',
  );
}
