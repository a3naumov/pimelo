import type { RouteRecordRaw } from 'vue-router';

export const catalogRoutes: RouteRecordRaw[] = [
  {
    path: '/products',
    name: 'catalog.products',
    component: () => import('./page/product/ProductsPage.vue'),
    meta: { title: 'Products', group: 'Catalog' },
  },
  {
    path: '/categories',
    name: 'catalog.categories',
    component: () => import('./page/category/CategoriesPage.vue'),
    meta: { title: 'Categories', group: 'Catalog' },
  },
];
