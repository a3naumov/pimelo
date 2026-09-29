import type { Category, CategoryBranchResponse } from './schemas';

export function previewCategoryMove(
  categories: Category[],
  path: Category[],
  branch: CategoryBranchResponse,
): Category[] {
  const original = branch.path[branch.path.length - 1];
  const moved = path[path.length - 1];
  const byId = new Map([...categories, ...path].map((category) => [category.id, category]));

  if (!original || !moved) {
    return categories;
  }

  const previousParent = original.parent_id && byId.get(original.parent_id);
  const previousLevel = branch.levels.find((level) => level.parent_id === original.parent_id);

  if (previousParent && previousLevel) {
    byId.set(previousParent.id, {
      ...previousParent,
      has_children: previousLevel.categories.some((category) => category.id !== moved.id),
    });
  }

  const parent = moved.parent_id && byId.get(moved.parent_id);

  if (parent) {
    byId.set(parent.id, { ...parent, has_children: true });
  }

  return [...byId.values()];
}

export function categoryPath(categories: Category[], id: string): Category[] {
  const byId = new Map(categories.map((category) => [category.id, category]));
  const path: Category[] = [];
  const seen = new Set<string>();
  let current = byId.get(id);

  while (current && !seen.has(current.id)) {
    seen.add(current.id);
    path.unshift(current);
    current = current.parent_id ? byId.get(current.parent_id) : undefined;
  }

  return path;
}

export function subtreeIds(categories: Category[], id: string): Set<string> {
  const children = new Map<string, string[]>();

  for (const category of categories) {
    if (category.parent_id) {
      children.set(category.parent_id, [...(children.get(category.parent_id) ?? []), category.id]);
    }
  }

  const result = new Set<string>();
  const queue = [id];

  while (queue.length) {
    const next = queue.pop()!;

    if (result.has(next)) {
      continue;
    }

    result.add(next);
    queue.push(...(children.get(next) ?? []));
  }

  return result;
}

export interface CategoryRow {
  category: Category;
  depth: number;
  hasChildren: boolean;
}

export function categoryRows(categories: Category[], expanded: Set<string>): CategoryRow[] {
  const children = new Map<string | null, Category[]>();

  for (const category of categories) {
    const parent = category.parent_id;
    children.set(parent, [...(children.get(parent) ?? []), category]);
  }

  const rows: CategoryRow[] = [];
  const visited = new Set<string>();

  const visit = (category: Category, depth: number) => {
    if (visited.has(category.id)) {
      return;
    }

    visited.add(category.id);

    rows.push({ category, depth, hasChildren: category.has_children });

    if (expanded.has(category.id)) {
      for (const child of children.get(category.id) ?? []) {
        visit(child, depth + 1);
      }
    }
  };

  for (const root of children.get(null) ?? []) {
    visit(root, 0);
  }

  return rows;
}
