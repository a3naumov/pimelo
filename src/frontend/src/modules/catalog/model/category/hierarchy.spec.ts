import { describe, expect, it } from 'vitest';
import { categoryPath, categoryRows, previewCategoryMove, subtreeIds } from './hierarchy';
const categories = [
  {
    name: 'root',
    slug: 'category',
    id: 'root',
    parent_id: null,
    has_children: true,
    deleted_at: null,
  },
  {
    name: 'child',
    slug: 'category',
    id: 'child',
    parent_id: 'root',
    has_children: true,
    deleted_at: null,
  },
  {
    name: 'leaf',
    slug: 'category',
    id: 'leaf',
    parent_id: 'child',
    has_children: false,
    deleted_at: null,
  },
  {
    name: 'other',
    slug: 'category',
    id: 'other',
    parent_id: null,
    has_children: true,
    deleted_at: null,
  },
];
describe('Category hierarchy', () => {
  it('expands loaded branches and uses server child availability', () => {
    expect(categoryRows(categories, new Set()).map((row) => row.category.id)).toEqual([
      'root',
      'other',
    ]);
    expect(categoryRows(categories, new Set(['root'])).map((row) => row.category.id)).toEqual([
      'root',
      'child',
      'other',
    ]);
    expect(categoryRows([categories[0]!], new Set())[0]?.hasChildren).toBe(true);
  });
  it('excludes the entire subtree from parent candidates, but keeps ancestors and siblings', () => {
    const excluded = subtreeIds(categories, 'child');
    expect(
      categories.filter((category) => !excluded.has(category.id)).map((category) => category.id),
    ).toEqual(['root', 'other']);
    expect(categoryPath(categories, 'leaf').map((category) => category.id)).toEqual([
      'root',
      'child',
      'leaf',
    ]);
  });
  it('terminates on cycles and handles missing parents', () => {
    const cyclic = [
      {
        name: 'a',
        slug: 'category',
        id: 'a',
        parent_id: 'b',
        has_children: true,
        deleted_at: null,
      },
      {
        name: 'b',
        slug: 'category',
        id: 'b',
        parent_id: 'a',
        has_children: true,
        deleted_at: null,
      },
    ];
    expect(subtreeIds(cyclic, 'a')).toEqual(new Set(['a', 'b']));
    expect(categoryPath(cyclic, 'a')).toHaveLength(2);
    expect(categoryRows(cyclic, new Set(['a', 'b']))).toEqual([]);
    expect(
      categoryRows(
        [
          {
            name: 'orphan',
            slug: 'category',
            id: 'orphan',
            parent_id: 'missing',
            has_children: false,
            deleted_at: null,
          },
        ],
        new Set(),
      )[0]?.depth,
    ).toBeUndefined();
  });
});

describe('Category move preview', () => {
  it('moves a subtree and updates parent flags without changing server objects', () => {
    const root = categories[0]!;
    const child = categories[1]!;
    const other = { ...categories[3]!, has_children: false, deleted_at: null };
    const source = [root, child, categories[2]!, other];
    const snapshot = structuredClone(source);
    const projected = previewCategoryMove(source, [other, { ...child, parent_id: other.id }], {
      path: [root, child],
      levels: [
        { parent_id: null, categories: [root, other] },
        { parent_id: root.id, categories: [child] },
      ],
    });
    expect(source).toEqual(snapshot);
    expect(projected.find((category) => category.id === root.id)?.has_children).toBe(false);
    expect(projected.find((category) => category.id === other.id)?.has_children).toBe(true);
    expect(categoryPath(projected, 'leaf').map((category) => category.id)).toEqual([
      'other',
      'child',
      'leaf',
    ]);
    expect(projected.filter((category) => category.id === child.id)).toHaveLength(1);
  });

  it('previews a root category while retaining the previous parent sibling indicator', () => {
    const root = categories[0]!;
    const child = categories[1]!;
    const sibling = { ...child, id: 'sibling' };
    const projected = previewCategoryMove(categories, [{ ...child, parent_id: null }], {
      path: [root, child],
      levels: [
        { parent_id: null, categories: [root, categories[3]!] },
        { parent_id: root.id, categories: [child, sibling] },
      ],
    });
    expect(projected.find((category) => category.id === root.id)?.has_children).toBe(true);
    expect(
      categoryRows(projected, new Set()).find((row) => row.category.id === child.id)?.depth,
    ).toBe(0);
  });
});
