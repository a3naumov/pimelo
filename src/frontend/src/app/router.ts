import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { catalogRoutes } from '@/modules/catalog';
import { workspaceRoutes } from '@/modules/workspace';

export const routes: RouteRecordRaw[] = [
  ...workspaceRoutes,
  ...catalogRoutes,
  { path: '/:pathMatch(.*)*', redirect: '/' },
];

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
});

router.afterEach((to) => {
  document.title = `${to.meta.title ?? 'Workspace'} · Pimelo`;
});
