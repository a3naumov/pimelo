<script setup lang="ts">
import { useTranslation } from '@/shared/i18n';
import { watch } from 'vue';
import {
  ArrowUpRightIcon,
  BookOpenIcon,
  SproutIcon,
  StoreIcon,
  XIcon,
  WifiOffIcon,
} from '@lucide/vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@/shared/ui/sidebar';
import { navigationGroups } from '../navigation';

const { t } = useTranslation();
const props = withDefaults(defineProps<{ unavailableServices?: readonly string[] }>(), {
  unavailableServices: () => [],
});
const route = useRoute();
const router = useRouter();
const { isMobile, setOpenMobile } = useSidebar();
const navigationItems = navigationGroups.flatMap((group) => [...group.items]);

function isUnavailable(name: string): boolean {
  return (router.resolve({ name }).meta.requiredServices ?? []).some((service) =>
    props.unavailableServices.includes(service),
  );
}

watch(
  () => route.fullPath,
  () => setOpenMobile(false),
);
</script>

<template>
  <Sidebar collapsible="offcanvas" variant="sidebar">
    <SidebarHeader class="sidebar-header">
      <div class="brand-row">
        <RouterLink
          to="/"
          :aria-label="t('app.home')"
          class="brand-link"
          @click="setOpenMobile(false)"
        >
          <SproutIcon aria-hidden="true" />
          <span class="brand-name">pimelo</span>
        </RouterLink>
        <Button
          v-if="isMobile"
          variant="ghost"
          size="icon-sm"
          :aria-label="t('app.closeNavigation')"
          @click="setOpenMobile(false)"
        >
          <XIcon data-icon="inline-start" aria-hidden="true" />
        </Button>
      </div>
      <div
        class="workspace-label"
        :aria-label="t('app.workspaceName')"
        :title="t('app.workspaceName')"
      >
        <StoreIcon aria-hidden="true" />
        <div class="workspace-details">
          <span class="workspace-name truncate">{{ t('app.workspaceName') }}</span>
          <span class="workspace-caption">{{ t('workspace.title') }}</span>
        </div>
      </div>
    </SidebarHeader>
    <SidebarContent class="px-5 pt-7">
      <nav id="app-navigation" :aria-label="t('app.mainNavigation')">
        <SidebarGroup class="p-0">
          <SidebarGroupContent>
            <SidebarMenu class="gap-[5px]">
              <SidebarMenuItem v-for="item in navigationItems" :key="item.name">
                <SidebarMenuButton
                  as-child
                  :is-active="(route.meta.navigationItem ?? route.name) === item.name"
                  :tooltip="t(item.titleKey)"
                >
                  <RouterLink
                    :to="{ name: item.name }"
                    :aria-label="t(item.titleKey)"
                    :aria-describedby="
                      isUnavailable(item.name) ? `unavailable-${item.name}` : undefined
                    "
                    :aria-current="
                      (route.meta.navigationItem ?? route.name) === item.name ? 'page' : undefined
                    "
                    @click="setOpenMobile(false)"
                  >
                    <component :is="item.icon" aria-hidden="true" />
                    <span>{{ t(item.titleKey) }}</span>
                    <Badge
                      v-if="isUnavailable(item.name)"
                      :id="`unavailable-${item.name}`"
                      :title="t('app.availability.unavailable')"
                      variant="outline"
                      class="ml-auto"
                    >
                      <WifiOffIcon aria-hidden="true" />
                      <span class="sr-only">{{ t('app.availability.unavailable') }}</span>
                    </Badge>
                  </RouterLink>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </nav>
    </SidebarContent>
    <SidebarFooter class="px-5 pb-6 pt-4">
      <RouterLink to="/" class="workspace-guide" @click="setOpenMobile(false)">
        <BookOpenIcon aria-hidden="true" />
        <span class="workspace-guide-title">{{ t('app.catalogGuide') }}</span>
        <span class="workspace-guide-link"
          >{{ t('app.workspaceOverview') }}<ArrowUpRightIcon aria-hidden="true"
        /></span>
      </RouterLink>
    </SidebarFooter>
  </Sidebar>
</template>

<style scoped>
.sidebar-header {
  gap: 0;
  min-width: 0;
  padding: 32px 20px 0;
}
.brand-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-width: 0;
  height: 65px;
  padding-bottom: 30px;
}
.brand-link {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  height: 35px;
  padding-inline: 14px;
  color: var(--brand);
  font-size: 29px;
  font-weight: 700;
  line-height: 35px;
}
.brand-name {
  overflow: hidden;
  white-space: nowrap;
}
.brand-link > svg {
  width: 27px;
  height: 27px;
  flex-shrink: 0;
  color: var(--sidebar-primary);
}
.workspace-label {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  min-height: 55px;
  padding: 11px 10px;
  border: 1px solid var(--sidebar-border);
  border-radius: 8px;
  overflow: hidden;
}
.workspace-details {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  white-space: nowrap;
}
.workspace-label > svg {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  color: var(--secondary-foreground);
}
.workspace-name {
  color: var(--foreground);
  font-size: 13px;
  font-weight: 600;
  line-height: 16px;
}
.workspace-caption {
  font-size: 10px;
  line-height: 12px;
}
.workspace-guide {
  display: flex;
  flex-direction: column;
  gap: 9px;
  padding: 16px;
  border-radius: 8px;
  background: var(--background);
}
.workspace-guide > svg {
  width: 21px;
  height: 21px;
  color: var(--primary);
}
.workspace-guide-title {
  color: var(--secondary-foreground);
  font-size: 12px;
  font-weight: 600;
}
.workspace-guide-link {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
}
.workspace-guide-link > svg {
  width: 12px;
  height: 12px;
}
.workspace-guide:hover {
  background: var(--accent);
}
.brand-link:focus-visible,
.workspace-guide:focus-visible {
  outline: 2px solid var(--ring);
  outline-offset: 4px;
}
</style>
