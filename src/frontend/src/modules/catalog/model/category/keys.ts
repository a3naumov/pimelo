export const categoryKeys = {
  all: ['catalog', 'categories'] as const,
  roots: ['catalog', 'categories', 'roots'] as const,
  rootList: (includeDeleted = false) =>
    [...categoryKeys.roots, ...(includeDeleted ? ([true] as const) : [])] as const,
  children: (id: string, includeDeleted = false) =>
    [
      'catalog',
      'categories',
      'children',
      id,
      ...(includeDeleted ? ([true] as const) : []),
    ] as const,
  details: ['catalog', 'categories', 'detail'] as const,
  detail: (id: string, includeDeleted = false) =>
    [...categoryKeys.details, id, ...(includeDeleted ? ([true] as const) : [])] as const,
  branches: ['catalog', 'categories', 'branch'] as const,
  branch: (id: string, includeDeleted = false) =>
    [...categoryKeys.branches, id, ...(includeDeleted ? ([true] as const) : [])] as const,
  productLists: ['catalog', 'categories', 'products'] as const,
  products: (id: string, includeDeleted = false) =>
    [...categoryKeys.productLists, id, ...(includeDeleted ? ([true] as const) : [])] as const,
};
