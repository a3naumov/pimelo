import { productsResponseSchema, type ProductsResponse } from '../../model/product/schemas';
import type { AxiosResponse } from 'axios';
import { apiClient } from '@/shared/api/client';
import {
  categoriesResponseSchema,
  categoryBranchResponseSchema,
  type CategoryBranchResponse,
  categoryResponseSchema,
  type CategoriesResponse,
  type CategoryResponse,
  type Category,
  type CategoryInput,
  type CategoryUpdateInput,
  categorySlugPreviewSchema,
  type CategorySlugPreview,
  type CategorySlugPreviewInput,
} from '../../model/category/schemas';
import { categoryApiRoutes as routes } from './routes';

export async function previewCategorySlug(
  input: CategorySlugPreviewInput,
  signal?: AbortSignal,
): Promise<CategorySlugPreview> {
  const { data } = await apiClient.get<CategorySlugPreview>(routes.slugPreview, {
    params: input,
    signal,
  });

  return categorySlugPreviewSchema.parse(data);
}

export async function getCategories(
  parentId: string | null,
  signal?: AbortSignal,
  includeDeleted = false,
): Promise<CategoriesResponse> {
  const { data } = await apiClient.get<CategoriesResponse>(routes.collection, {
    signal,
    ...(parentId !== null || includeDeleted
      ? {
          params: {
            ...(parentId !== null ? { parent_id: parentId } : {}),
            ...(includeDeleted ? { include_deleted: 1 } : {}),
          },
        }
      : {}),
  });

  return categoriesResponseSchema.parse(data);
}

export async function getCategory(
  id: string,
  signal?: AbortSignal,
  includeDeleted = false,
): Promise<Category> {
  const { data } = await apiClient.get<CategoryResponse>(routes.item(id), {
    signal,
    ...(includeDeleted ? { params: { include_deleted: 1 } } : {}),
  });

  return categoryResponseSchema.parse(data).category;
}

export async function createCategory(input: CategoryInput): Promise<Category> {
  const { data } = await apiClient.post<
    CategoryResponse,
    AxiosResponse<CategoryResponse, CategoryInput>,
    CategoryInput
  >(routes.collection, input);

  return categoryResponseSchema.parse(data).category;
}

export async function updateCategory(id: string, input: CategoryUpdateInput): Promise<Category> {
  const { data } = await apiClient.patch<
    CategoryResponse,
    AxiosResponse<CategoryResponse, CategoryUpdateInput>,
    CategoryUpdateInput
  >(routes.item(id), input);

  return categoryResponseSchema.parse(data).category;
}

export async function deleteCategory(id: string): Promise<void> {
  await apiClient.delete<void>(routes.item(id));
}

export function getCategoryChildren(
  id: string,
  signal?: AbortSignal,
  includeDeleted = false,
): Promise<CategoriesResponse> {
  return getCategories(id, signal, includeDeleted);
}

export async function getCategoryProducts(
  id: string,
  signal?: AbortSignal,
  includeDeleted = false,
): Promise<ProductsResponse> {
  const { data } = await apiClient.get<ProductsResponse>(routes.products(id), {
    signal,
    ...(includeDeleted ? { params: { include_deleted: 1 } } : {}),
  });

  return productsResponseSchema.parse(data);
}

export interface ProductCategoryInput {
  productId: string;
  categoryId: string;
}

export async function attachProduct({
  productId,
  categoryId,
}: ProductCategoryInput): Promise<void> {
  await apiClient.put<void>(routes.product(categoryId, productId));
}

export async function detachProduct({
  productId,
  categoryId,
}: ProductCategoryInput): Promise<void> {
  await apiClient.delete<void>(routes.product(categoryId, productId));
}

export async function getCategoryBranch(
  id: string,
  signal?: AbortSignal,
  includeDeleted = false,
): Promise<CategoryBranchResponse> {
  const { data } = await apiClient.get<CategoryBranchResponse>(routes.branch(id), {
    signal,
    ...(includeDeleted ? { params: { include_deleted: 1 } } : {}),
  });

  return categoryBranchResponseSchema
    .refine((branch) => branch.path[branch.path.length - 1]?.id === id.toLowerCase(), {
      message: 'The branch does not match the selected category.',
    })
    .parse(data);
}

export async function restoreCategory(id: string): Promise<Category> {
  const { data } = await apiClient.post<CategoryResponse>(routes.restore(id));

  return categoryResponseSchema.parse(data).category;
}

export async function deleteCategoryPermanently(id: string): Promise<void> {
  await apiClient.delete<void>(routes.permanent(id));
}
