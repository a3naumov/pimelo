import { onScopeDispose, watch } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { AxiosError } from 'axios';
import { apiClient } from '@/shared/api/client';

export function usePimAvailability(isAvailable: () => boolean): void {
  const client = useQueryClient();
  const interceptor = apiClient.interceptors.request.use((request) => {
    if (!isAvailable()) {
      throw new AxiosError('Service unavailable.', 'ERR_SERVICE_UNAVAILABLE', request);
    }

    return request;
  });

  watch(
    isAvailable,
    (available, previous) => {
      const filters = {
        predicate: (query: { queryKey: readonly unknown[] }) =>
          query.queryKey[0] === 'catalog' || query.queryKey[0] === 'attributes',
      };

      if (!available) {
        void client.cancelQueries(filters);
      } else if (previous === false) {
        void client.refetchQueries({ ...filters, type: 'active' });
      }
    },
    { flush: 'sync' },
  );

  onScopeDispose(() => apiClient.interceptors.request.eject(interceptor));
}
