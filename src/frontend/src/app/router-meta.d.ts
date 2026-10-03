import 'vue-router';
import type { MessageKey } from '@/shared/i18n';

declare module 'vue-router' {
  interface RouteMeta {
    requiredServices?: readonly string[];
    titleKey?: MessageKey;
    groupKey?: MessageKey;
    navigationItem?: string;
    parent?: { name: string; titleKey: MessageKey };
  }
}
