import type { RouteRecordRaw } from 'vue-router';

import { productRoutes } from './route/product/routes';
import { categoryRoutes } from './route/category/routes';

export const catalogRoutes: RouteRecordRaw[] = [...productRoutes, ...categoryRoutes];
