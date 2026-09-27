export const productApiRoutes = {
  collection: '/products/',
  item: (id: string): string => `/products/${encodeURIComponent(id)}`,
  restore: (id: string): string => `/products/${encodeURIComponent(id)}/restore`,
  permanentDelete: (id: string): string => `/products/${encodeURIComponent(id)}/permanent`,
} as const;
