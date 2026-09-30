import { catalogNavigation } from '@/modules/catalog';
import { attributesNavigation } from '@/modules/attributes';
import { workspaceNavigation } from '@/modules/workspace';

export const navigationGroups = [
  workspaceNavigation,
  catalogNavigation,
  attributesNavigation,
] as const;
