import { createApp } from 'vue';
import { VueQueryPlugin } from '@tanstack/vue-query';
import './app/styles.css';
import App from './app/App.vue';
import { router } from './app/router';
import { createQueryClient } from './app/queryClient';

createApp(App).use(VueQueryPlugin, { queryClient: createQueryClient() }).use(router).mount('#app');
