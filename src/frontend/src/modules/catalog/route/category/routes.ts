import type { RouteRecordRaw } from 'vue-router';

export const categoryRoutes: RouteRecordRaw[] = [
  {
    path: '/categories',
    name: 'catalog.categories',
    component: () => import('../../page/category/CategoriesPage.vue'),
    meta: {
      requiredServices: ['pim'],
      titleKey: 'catalog.category.title',
      groupKey: 'catalog.title',
    },
  },
  {
    path: '/categories/:id',
    name: 'catalog.categories.detail',
    component: () => import('../../page/category/CategoriesPage.vue'),
    meta: {
      requiredServices: ['pim'],
      titleKey: 'catalog.category.details',
      groupKey: 'catalog.title',
      navigationItem: 'catalog.categories',
      parent: { name: 'catalog.categories', titleKey: 'catalog.category.title' },
    },
  },
];
