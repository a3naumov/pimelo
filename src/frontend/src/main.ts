import { createApp } from 'vue';
import './app/styles.css';
import App from './app/App.vue';
import { router } from './app/router';

createApp(App).use(router).mount('#app');
