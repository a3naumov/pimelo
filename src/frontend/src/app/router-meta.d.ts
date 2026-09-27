import 'vue-router';
import type { MessageKey } from '@/shared/i18n';

declare module 'vue-router' {
  interface RouteMeta {
    titleKey?: MessageKey;
    groupKey?: MessageKey;
    navigationItem?: string;
    parent?: { name: string; titleKey: MessageKey };
  }
}
