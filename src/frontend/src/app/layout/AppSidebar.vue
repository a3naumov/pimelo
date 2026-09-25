<script setup lang="ts">
import { watch } from 'vue';
import { BoxesIcon, XIcon } from '@lucide/vue';
import { RouterLink, useRoute } from 'vue-router';
import { Button } from '@/shared/ui/button';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
  SidebarSeparator,
  useSidebar,
} from '@/shared/ui/sidebar';
import { navigationGroups } from '../navigation';

const route = useRoute();
const { isMobile, setOpenMobile } = useSidebar();

watch(
  () => route.fullPath,
  () => setOpenMobile(false),
);
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem class="flex items-center">
          <SidebarMenuButton size="lg" as-child tooltip="Pimelo">
            <RouterLink to="/" aria-label="Pimelo home" @click="setOpenMobile(false)">
              <div
                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground"
              >
                <BoxesIcon aria-hidden="true" />
              </div>
              <div class="grid min-w-0 gap-0.5 group-data-[collapsible=icon]:hidden">
                <span class="truncate font-semibold">Pimelo</span>
                <span class="truncate text-xs text-muted-foreground">Product workspace</span>
              </div>
            </RouterLink>
          </SidebarMenuButton>
          <Button
            v-if="isMobile"
            variant="ghost"
            size="icon-sm"
            aria-label="Close navigation"
            @click="setOpenMobile(false)"
          >
            <XIcon data-icon="inline-start" aria-hidden="true" />
          </Button>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>
    <SidebarSeparator />
    <SidebarContent>
      <nav id="app-navigation" aria-label="Main navigation">
        <SidebarGroup v-for="group in navigationGroups" :key="group.title">
          <SidebarGroupLabel>{{ group.title }}</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem v-for="item in group.items" :key="item.name">
                <SidebarMenuButton
                  as-child
                  :is-active="route.name === item.name"
                  :tooltip="item.title"
                >
                  <RouterLink
                    :to="{ name: item.name }"
                    :aria-label="item.title"
                    @click="setOpenMobile(false)"
                  >
                    <component :is="item.icon" aria-hidden="true" />
                    <span>{{ item.title }}</span>
                  </RouterLink>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </nav>
    </SidebarContent>
    <SidebarFooter></SidebarFooter>
    <SidebarRail />
  </Sidebar>
</template>
