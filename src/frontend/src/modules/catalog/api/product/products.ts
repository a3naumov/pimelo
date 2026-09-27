import type { AxiosResponse } from 'axios';
import { apiClient } from '@/shared/api/client';
import { productApiRoutes } from './routes';
import {
  productResponseSchema,
  productsResponseSchema,
  type Product,
  type ProductInput,
  type ProductResponse,
  type ProductsResponse,
  type ProductStatus,
} from '../../model/product/schemas';

export async function getProducts(
  status: ProductStatus = 'active',
  signal?: AbortSignal,
): Promise<ProductsResponse> {
  const { data } = await apiClient.get<ProductsResponse>(productApiRoutes.collection, {
    signal,
    params: status === 'deleted' ? { status } : undefined,
  });

  return productsResponseSchema.parse(data);
}

export async function getProduct(id: string, signal?: AbortSignal): Promise<Product> {
  const { data } = await apiClient.get<ProductResponse>(productApiRoutes.item(id), {
    signal,
    params: { include_deleted: 1 },
  });

  return productResponseSchema.parse(data).product;
}

export async function createProduct(input: ProductInput): Promise<Product> {
  const { data } = await apiClient.post<
    ProductResponse,
    AxiosResponse<ProductResponse, ProductInput>,
    ProductInput
  >(productApiRoutes.collection, input);

  return productResponseSchema.parse(data).product;
}

export async function updateProduct(id: string, input: ProductInput): Promise<Product> {
  const { data } = await apiClient.patch<
    ProductResponse,
    AxiosResponse<ProductResponse, ProductInput>,
    ProductInput
  >(productApiRoutes.item(id), input);

  return productResponseSchema.parse(data).product;
}

export async function deleteProduct(id: string): Promise<void> {
  await apiClient.delete<void>(productApiRoutes.item(id));
}

export async function restoreProduct(id: string): Promise<Product> {
  const { data } = await apiClient.post<ProductResponse>(productApiRoutes.restore(id));

  return productResponseSchema.parse(data).product;
}

export async function deleteProductPermanently(id: string): Promise<void> {
  await apiClient.delete<void>(productApiRoutes.permanentDelete(id));
}
