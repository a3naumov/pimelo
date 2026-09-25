import { FolderTreeIcon, PackageIcon } from '@lucide/vue';

export const catalogNavigation = {
  title: 'Catalog',
  items: [
    { name: 'catalog.products', title: 'Products', icon: PackageIcon },
    { name: 'catalog.categories', title: 'Categories', icon: FolderTreeIcon },
  ],
} as const;
