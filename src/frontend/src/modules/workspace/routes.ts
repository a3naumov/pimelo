import type { RouteRecordRaw } from 'vue-router';

import { overviewRoutes } from './route/overview/routes';

export const workspaceRoutes: RouteRecordRaw[] = [...overviewRoutes];
