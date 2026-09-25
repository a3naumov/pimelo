import { afterEach, describe, expect, it, vi } from 'vitest';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import App from './App.vue';
import { routes } from './router';

enableAutoUnmount(afterEach);

describe('App layout composition', () => {
  it('renders routed pages inside the same layout across navigation', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes });
    await router.push('/categories');
    await router.isReady();
    const wrapper = mount(App, { global: { plugins: [router] } });
    const navigation = wrapper.get('nav[aria-label="Main navigation"]').element;
    const main = wrapper.get('main').element;

    expect(wrapper.get('main h1').text()).toBe('Categories');

    await router.push('/products');
    await vi.waitFor(() => {
      expect(wrapper.get('main h1').text()).toBe('Products');
    });

    expect(wrapper.get('main').element).toBe(main);
    expect(wrapper.get('nav[aria-label="Main navigation"]').element).toBe(navigation);
  });
});
