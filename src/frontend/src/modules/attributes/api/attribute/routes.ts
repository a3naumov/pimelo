export const attributeApiRoutes = {
  collection: '/attributes/',
  item: (id: string): string => `/attributes/${encodeURIComponent(id)}`,
  restore: (id: string): string => `/attributes/${encodeURIComponent(id)}/restore`,
  permanentDelete: (id: string): string => `/attributes/${encodeURIComponent(id)}/permanent`,
} as const;
