import { LayoutDashboardIcon } from '@lucide/vue';

export const workspaceNavigation = {
  titleKey: 'workspace.title',
  items: [
    { name: 'workspace.overview', titleKey: 'workspace.overview.title', icon: LayoutDashboardIcon },
  ],
} as const;
