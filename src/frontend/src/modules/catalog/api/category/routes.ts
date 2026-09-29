export const categoryApiRoutes = {
  collection: '/categories/',
  item: (id: string) => `/categories/${encodeURIComponent(id)}`,
  restore: (id: string) => `/categories/${encodeURIComponent(id)}/restore`,
  permanent: (id: string) => `/categories/${encodeURIComponent(id)}/permanent`,
  branch: (id: string) => `/categories/${encodeURIComponent(id)}/branch`,
  products: (id: string) => `/categories/${encodeURIComponent(id)}/products/`,
  product: (categoryId: string, productId: string) =>
    `/categories/${encodeURIComponent(categoryId)}/products/${encodeURIComponent(productId)}`,
};
