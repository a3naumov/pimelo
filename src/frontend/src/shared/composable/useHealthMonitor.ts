import { computed, onScopeDispose, reactive } from 'vue';
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { getHealthcheck } from '@/shared/api/healthcheck';
import {
  createAvailabilityState,
  hasAvailabilityIssues,
  isServiceAvailable,
  observeConnectionFailure,
  observeHealthcheck,
} from '../model/availability';

export const healthcheckKey = ['shared', 'healthcheck'] as const;

export function useHealthMonitor(requiredServices: readonly string[]) {
  const state = reactive(createAvailabilityState(requiredServices));
  const client = useQueryClient();
  const hasIssues = computed(() => hasAvailabilityIssues(state));
  let disposed = false;
  const query = useQuery({
    queryKey: healthcheckKey,
    queryFn: async ({ signal }) => {
      try {
        const result = await getHealthcheck(signal);

        if (!disposed && !signal.aborted) {
          observeHealthcheck(state, result);
        }

        return result;
      } catch (error) {
        if (!disposed && !signal.aborted) {
          observeConnectionFailure(state);
        }

        throw error;
      }
    },
    retry: false,
    networkMode: 'always',
    staleTime: 0,
    gcTime: 0,
    refetchOnMount: 'always',
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
    refetchInterval: computed(() => (hasIssues.value ? 5000 : 15000)),
    refetchIntervalInBackground: false,
  });

  function checkNow(): void {
    void query.refetch({ cancelRefetch: false });
  }

  function onVisible(): void {
    if (document.visibilityState === 'visible') {
      checkNow();
    }
  }

  function onOffline(): void {
    observeConnectionFailure(state);
    void client.cancelQueries({ queryKey: healthcheckKey });
  }

  window.addEventListener('online', checkNow);
  window.addEventListener('offline', onOffline);
  window.addEventListener('focus', onVisible);
  document.addEventListener('visibilitychange', onVisible);
  onScopeDispose(() => {
    disposed = true;
    window.removeEventListener('online', checkNow);
    window.removeEventListener('offline', onOffline);
    window.removeEventListener('focus', onVisible);
    document.removeEventListener('visibilitychange', onVisible);
    void client.cancelQueries({ queryKey: healthcheckKey });
  });

  return {
    state,
    hasIssues,
    isFetching: query.isFetching,
    checkNow,
    isAvailable: (name: string) => isServiceAvailable(state, name),
    unavailableServices: computed(() =>
      Object.keys(state.services).filter((name) => !isServiceAvailable(state, name)),
    ),
  };
}
