import { createI18n, useI18n } from 'vue-i18n';
import en from './locale/en.json';

export type MessageSchema = typeof en;
type LeafKeys<T> = {
  [K in keyof T & string]: T[K] extends string ? K : `${K}.${LeafKeys<T[K]>}`;
}[keyof T & string];
export type MessageKey = LeafKeys<MessageSchema>;
export type Translate = (key: MessageKey, values?: Record<string, string | number>) => string;

export function createAppI18n() {
  return createI18n({
    legacy: false,
    globalInjection: false,
    locale: 'en',
    fallbackLocale: 'en',
    messages: { en },
  });
}

export function useTranslation() {
  const composer = useI18n<{ message: MessageSchema }>({ useScope: 'global' });
  const t: Translate = (key, values) => (values ? composer.t(key, values) : composer.t(key));

  return { t };
}
