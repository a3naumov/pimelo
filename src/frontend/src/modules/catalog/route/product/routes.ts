import type { RouteRecordRaw } from 'vue-router';

export const productRoutes: RouteRecordRaw[] = [
  {
    path: '/products',
    name: 'catalog.products',
    component: () => import('../../page/product/ProductsPage.vue'),
    meta: {
      requiredServices: ['pim'],
      titleKey: 'catalog.product.title',
      groupKey: 'catalog.title',
    },
  },
  {
    path: '/products/new',
    name: 'catalog.products.create',
    component: () => import('../../page/product/ProductCreatePage.vue'),
    meta: {
      requiredServices: ['pim'],
      titleKey: 'catalog.product.create',
      groupKey: 'catalog.title',
      navigationItem: 'catalog.products',
      parent: { name: 'catalog.products', titleKey: 'catalog.product.title' },
    },
  },
  {
    path: '/products/:id/edit',
    name: 'catalog.products.edit',
    component: () => import('../../page/product/ProductEditPage.vue'),
    meta: {
      requiredServices: ['pim'],
      titleKey: 'catalog.product.edit',
      groupKey: 'catalog.title',
      navigationItem: 'catalog.products',
      parent: { name: 'catalog.products', titleKey: 'catalog.product.title' },
    },
  },
  {
    path: '/products/:id',
    redirect: (to) => ({
      name: 'catalog.products.edit',
      params: { id: to.params.id },
      query: to.query,
      hash: to.hash,
    }),
  },
];
