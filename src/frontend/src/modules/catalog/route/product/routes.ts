import type { RouteRecordRaw } from 'vue-router';

export const productRoutes: RouteRecordRaw[] = [
  {
    path: '/products',
    name: 'catalog.products',
    component: () => import('../../page/product/ProductsPage.vue'),
    meta: { title: 'Products', group: 'Catalog' },
  },
  {
    path: '/products/new',
    name: 'catalog.products.create',
    component: () => import('../../page/product/ProductCreatePage.vue'),
    meta: {
      title: 'Create product',
      group: 'Catalog',
      navigationItem: 'catalog.products',
      parent: { name: 'catalog.products', title: 'Products' },
    },
  },
  {
    path: '/products/:id/edit',
    name: 'catalog.products.edit',
    component: () => import('../../page/product/ProductEditPage.vue'),
    meta: {
      title: 'Edit product',
      group: 'Catalog',
      navigationItem: 'catalog.products',
      parent: { name: 'catalog.products', title: 'Products' },
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
