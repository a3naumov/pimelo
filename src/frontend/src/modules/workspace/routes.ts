import type { RouteRecordRaw } from 'vue-router';

export const workspaceRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'workspace.overview',
    component: () => import('./page/overview/OverviewPage.vue'),
    meta: { title: 'Overview', group: 'Workspace' },
  },
];
