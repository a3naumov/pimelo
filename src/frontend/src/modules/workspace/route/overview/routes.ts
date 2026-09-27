import type { RouteRecordRaw } from 'vue-router';

export const overviewRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'workspace.overview',
    component: () => import('../../page/overview/OverviewPage.vue'),
    meta: { titleKey: 'workspace.overview.title', groupKey: 'workspace.title' },
  },
];
