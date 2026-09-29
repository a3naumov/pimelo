import { z } from 'zod';

export const categorySchema = z.object({
  id: z.uuid(),
  parent_id: z.uuid().nullable(),
  has_children: z.boolean(),
  deleted_at: z.iso.datetime({ offset: true }).nullable(),
});
export const categoryInputSchema = categorySchema.pick({ parent_id: true });
export const categoryResponseSchema = z.object({ category: categorySchema });
export const categoriesResponseSchema = z.object({ categories: z.array(categorySchema) });
export type Category = z.infer<typeof categorySchema>;
export type CategoryInput = z.infer<typeof categoryInputSchema>;
export type CategoryResponse = z.infer<typeof categoryResponseSchema>;
export type CategoriesResponse = z.infer<typeof categoriesResponseSchema>;

export const categoryBranchResponseSchema = z
  .object({
    path: z.array(categorySchema).min(1),
    levels: z
      .array(
        z.object({
          parent_id: z.uuid().nullable(),
          categories: z.array(categorySchema),
        }),
      )
      .min(1),
  })
  .superRefine((branch, context) => {
    const seen = new Set<string>();
    let parent: string | null = null;

    if (branch.levels.length !== branch.path.length) {
      context.addIssue({ code: 'custom', message: 'Incomplete category branch.' });
    }

    for (const [index, category] of branch.path.entries()) {
      const level = branch.levels[index];

      if (
        seen.has(category.id) ||
        category.parent_id !== parent ||
        level?.parent_id !== parent ||
        !level.categories.some((item) => item.id === category.id) ||
        level.categories.some((item) => item.parent_id !== parent)
      ) {
        context.addIssue({ code: 'custom', message: 'Invalid category branch.' });
      }

      seen.add(category.id);
      parent = category.id;
    }
  });
export type CategoryBranchResponse = z.infer<typeof categoryBranchResponseSchema>;
