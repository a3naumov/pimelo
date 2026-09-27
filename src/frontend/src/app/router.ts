import type { Translate } from '@/shared/i18n';
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { catalogRoutes } from '@/modules/catalog';
import { workspaceRoutes } from '@/modules/workspace';

export const routes: RouteRecordRaw[] = [
  ...workspaceRoutes,
  ...catalogRoutes,
  { path: '/:pathMatch(.*)*', redirect: '/' },
];

export function createAppRouter(t: Translate) {
  const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes,
  });

  router.afterEach((to) => {
    document.title = t('app.documentTitle', { title: t(to.meta.titleKey ?? 'workspace.title') });
  });

  return router;
}
