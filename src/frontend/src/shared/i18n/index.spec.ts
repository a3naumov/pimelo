import { defineComponent, h } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, expectTypeOf, it } from 'vitest';
import { createAppI18n, useTranslation, type MessageKey, type Translate } from './index';
import en from './locale/en.json';

enableAutoUnmount(afterEach);

function setup() {
  const i18n = createAppI18n();
  let t!: Translate;
  const wrapper = mount(
    defineComponent({
      setup() {
        ({ t } = useTranslation());

        return () => h('p', t('catalog.product.confirm.deleteDescription', { sku: '<b>SKU</b>' }));
      },
    }),
    { global: { plugins: [i18n] } },
  );

  return { i18n, t, wrapper };
}

describe('English localization', () => {
  it('uses English defaults and preserves interpolated values as text', () => {
    const { i18n, t, wrapper } = setup();
    expect(i18n.global.locale.value).toBe('en');
    expect(i18n.global.fallbackLocale.value).toBe('en');
    expect(t('common.cancel')).toBe('Cancel');
    expect(t('catalog.product.actionsFor', { sku: 'SKU-{test}@01' })).toBe(
      'Actions for SKU-{test}@01',
    );
    expect(wrapper.text()).toBe(
      '<b>SKU</b> will be removed from the catalog. Its SKU will remain reserved.',
    );
    expect(wrapper.find('b').exists()).toBe(false);
  });

  it('compiles every message in the English dictionary', () => {
    const i18n = createAppI18n();

    function messageKeys(messages: object, prefix = ''): string[] {
      const entries: [string, unknown][] = Object.entries(messages);

      return entries.flatMap(([name, value]) => {
        const key = prefix ? `${prefix}.${name}` : name;

        if (typeof value === 'string') {
          return [key];
        }

        if (value && typeof value === 'object') {
          return messageKeys(value, key);
        }

        throw new Error(`Invalid locale message: ${key}`);
      });
    }

    for (const key of messageKeys(en)) {
      const translated = i18n.global.t(key, { sku: 'SKU-01', title: 'Products' });
      expect(translated).not.toBe(key);
      expect(translated).not.toMatch(/\{(?:sku|title)\}/);
    }
  });

  it('restricts translation calls to dictionary leaf keys', () => {
    const { t } = setup();
    expectTypeOf(t).parameter(0).toEqualTypeOf<MessageKey>();
    expectTypeOf<'common.cancel'>().toExtend<MessageKey>();
    expectTypeOf<'common.missing'>().not.toExtend<MessageKey>();
    expectTypeOf<'catalog'>().not.toExtend<MessageKey>();
    expectTypeOf<string>().not.toExtend<MessageKey>();
  });
});
