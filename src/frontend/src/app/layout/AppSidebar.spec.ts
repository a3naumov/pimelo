import { h } from 'vue';
import { afterEach, describe, expect, it } from 'vitest';
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { SidebarProvider } from '@/shared/ui/sidebar';
import AppSidebar from './AppSidebar.vue';

enableAutoUnmount(afterEach);

async function mountSidebar(path = '/') {
  const page = { render: () => null };
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'workspace.overview', component: page },
      { path: '/products', name: 'catalog.products', component: page },
      { path: '/categories', name: 'catalog.categories', component: page },
    ],
  });
  await router.push(path);
  await router.isReady();
  const wrapper = mount(() => h(SidebarProvider, () => h(AppSidebar)), {
    global: { plugins: [router] },
  });
  return { wrapper, router };
}

describe('AppSidebar navigation', () => {
  it('marks the current route as active', async () => {
    const { wrapper } = await mountSidebar('/categories');
    const navigation = wrapper.get('nav[aria-label="Main navigation"]');

    expect(navigation.get('a[href="/categories"]').attributes('aria-current')).toBe('page');
    expect(navigation.get('a[href="/categories"]').attributes('data-active')).toBe('true');
    expect(navigation.get('a[href="/products"]').attributes('aria-current')).toBeUndefined();
  });

  it('navigates through a link and moves the active state', async () => {
    const { wrapper, router } = await mountSidebar();
    const navigation = wrapper.get('nav[aria-label="Main navigation"]');

    await navigation.get('a[href="/products"]').trigger('click');
    await flushPromises();

    expect(router.currentRoute.value.path).toBe('/products');
    expect(navigation.get('a[href="/products"]').attributes('aria-current')).toBe('page');
    expect(navigation.get('a[href="/products"]').attributes('data-active')).toBe('true');
    expect(navigation.get('a[href="/"]').attributes('aria-current')).toBeUndefined();
    expect(navigation.get('a[href="/"]').attributes('data-active')).toBeUndefined();
  });
});
