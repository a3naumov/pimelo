import { QueryClient } from '@tanstack/vue-query';

export function createQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: { staleTime: 30000, retry: false },
      mutations: { retry: false },
    },
  });
}
