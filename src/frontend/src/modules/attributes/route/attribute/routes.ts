import type { RouteRecordRaw } from 'vue-router';

export const attributeRoutes: RouteRecordRaw[] = [
  {
    path: '/attributes',
    name: 'attributes.list',
    component: () => import('../../page/attribute/AttributesPage.vue'),
    meta: { titleKey: 'attributes.attribute.title', groupKey: 'attributes.title' },
  },
  {
    path: '/attributes/new',
    name: 'attributes.create',
    component: () => import('../../page/attribute/AttributeCreatePage.vue'),
    meta: {
      titleKey: 'attributes.attribute.create',
      groupKey: 'attributes.title',
      navigationItem: 'attributes.list',
      parent: { name: 'attributes.list', titleKey: 'attributes.attribute.title' },
    },
  },
  {
    path: '/attributes/:id/edit',
    name: 'attributes.edit',
    component: () => import('../../page/attribute/AttributeEditPage.vue'),
    meta: {
      titleKey: 'attributes.attribute.edit',
      groupKey: 'attributes.title',
      navigationItem: 'attributes.list',
      parent: { name: 'attributes.list', titleKey: 'attributes.attribute.title' },
    },
  },
];
