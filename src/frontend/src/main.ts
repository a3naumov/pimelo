import { createApp } from 'vue';
import { createAppI18n } from '@/shared/i18n';
import { VueQueryPlugin } from '@tanstack/vue-query';
import './app/styles.css';
import App from './app/App.vue';
import { createAppRouter } from './app/router';
import { createQueryClient } from './app/queryClient';

const i18n = createAppI18n();
const router = createAppRouter(i18n.global.t);

createApp(App)
  .use(i18n)
  .use(VueQueryPlugin, { queryClient: createQueryClient() })
  .use(router)
  .mount('#app');
