import { h, nextTick } from 'vue';
import { afterEach, describe, expect, it } from 'vitest';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { SidebarProvider } from '@/shared/ui/sidebar';
import AppContent from './AppContent.vue';

enableAutoUnmount(afterEach);

async function mountContent(path = '/') {
  const page = { render: () => null };
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: page, meta: { title: 'Overview', group: 'Workspace' } },
      { path: '/products', component: page, meta: { title: 'Products', group: 'Catalog' } },
    ],
  });
  await router.push(path);
  await router.isReady();
  const wrapper = mount(() => h(SidebarProvider, () => h(AppContent)), {
    global: { plugins: [router] },
  });
  return { wrapper, router };
}

describe('AppContent breadcrumbs', () => {
  it('shows the group and title of the current route', async () => {
    const { wrapper } = await mountContent('/products');

    expect(wrapper.get('nav[aria-label="breadcrumb"]').text()).toContain('Catalog');
    expect(wrapper.get('[data-slot="breadcrumb-page"]').text()).toBe('Products');
  });

  it('updates the group and title after navigation', async () => {
    const { wrapper, router } = await mountContent();
    expect(wrapper.get('[data-slot="breadcrumb-page"]').text()).toBe('Overview');

    await router.push('/products');
    await nextTick();

    const breadcrumb = wrapper.get('nav[aria-label="breadcrumb"]');
    expect(breadcrumb.text()).toContain('Catalog');
    expect(breadcrumb.text()).not.toContain('Workspace');
    expect(wrapper.get('[data-slot="breadcrumb-page"]').text()).toBe('Products');
  });
});
