import { FolderTreeIcon, PackageIcon } from '@lucide/vue';

export const catalogNavigation = {
  titleKey: 'catalog.title',
  items: [
    { name: 'catalog.products', titleKey: 'catalog.product.title', icon: PackageIcon },
    { name: 'catalog.categories', titleKey: 'catalog.category.title', icon: FolderTreeIcon },
  ],
} as const;
