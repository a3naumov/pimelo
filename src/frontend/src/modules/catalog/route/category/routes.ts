import type { RouteRecordRaw } from 'vue-router';

export const categoryRoutes: RouteRecordRaw[] = [
  {
    path: '/categories',
    name: 'catalog.categories',
    component: () => import('../../page/category/CategoriesPage.vue'),
    meta: { titleKey: 'catalog.category.title', groupKey: 'catalog.title' },
  },
];
