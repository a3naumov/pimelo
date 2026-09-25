import { catalogNavigation } from '@/modules/catalog';
import { workspaceNavigation } from '@/modules/workspace';

export const navigationGroups = [workspaceNavigation, catalogNavigation] as const;
