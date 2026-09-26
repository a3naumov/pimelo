import 'vue-router';

declare module 'vue-router' {
  interface RouteMeta {
    navigationItem?: string;
    parent?: { name: string; title: string };
  }
}
