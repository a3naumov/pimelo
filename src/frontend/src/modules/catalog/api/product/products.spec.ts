import { afterEach, describe, expect, it, vi } from 'vitest';
import { ZodError } from 'zod';
import { apiClient } from '@/shared/api/client';
import {
  createProduct,
  deleteProduct,
  deleteProductPermanently,
  restoreProduct,
  getProduct,
  getProducts,
  updateProduct,
} from './products';

const product = { id: '0195f582-9762-7c2a-9228-4060489e06d8', sku: 'SKU-01', deleted_at: null };
afterEach(() => vi.restoreAllMocks());

describe('Product read API', () => {
  it('requests only deleted products and validates deletion timestamps', async () => {
    const archived = { ...product, deleted_at: '2026-09-25T10:00:00+00:00' };
    const request = vi
      .spyOn(apiClient, 'get')
      .mockResolvedValue({ data: { products: [archived] } });
    expect(await getProducts('deleted')).toEqual({ products: [archived] });
    expect(request).toHaveBeenCalledWith('/products/', {
      signal: undefined,
      params: { status: 'deleted' },
    });
    request.mockResolvedValue({ data: { products: [{ ...archived, deleted_at: 'invalid' }] } });
    await expect(getProducts('deleted')).rejects.toBeInstanceOf(ZodError);
  });
  it('preserves the list envelope and forwards cancellation', async () => {
    const request = vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { products: [product] } });
    const signal = new AbortController().signal;
    expect(await getProducts('active', signal)).toEqual({ products: [product] });
    expect(request).toHaveBeenCalledWith('/products/', { signal, params: undefined });
  });

  it('unwraps the detail response and forwards cancellation', async () => {
    const request = vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { product } });
    const signal = new AbortController().signal;
    expect(await getProduct(product.id, signal)).toEqual(product);
    expect(request).toHaveBeenCalledWith(`/products/${product.id}`, {
      signal,
      params: { include_deleted: 1 },
    });
  });

  it('rejects a successful response that breaks the contract', async () => {
    vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { products: [{ sku: 'missing-id' }] } });
    await expect(getProducts()).rejects.toBeInstanceOf(ZodError);
  });
});

describe('Product write API', () => {
  it('restores and permanently deletes through explicit endpoints', async () => {
    const post = vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { product } });
    const remove = vi.spyOn(apiClient, 'delete').mockResolvedValue({ status: 204, data: '' });
    expect(await restoreProduct(product.id)).toEqual(product);
    await expect(deleteProductPermanently(product.id)).resolves.toBeUndefined();
    expect(post).toHaveBeenCalledWith(`/products/${product.id}/restore`);
    expect(remove).toHaveBeenCalledWith(`/products/${product.id}/permanent`);
  });
  it('creates and updates through their respective endpoints', async () => {
    const post = vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { product } });
    const patch = vi.spyOn(apiClient, 'patch').mockResolvedValue({ data: { product } });
    expect(await createProduct({ sku: product.sku })).toEqual(product);
    expect(await updateProduct(product.id, { sku: product.sku })).toEqual(product);
    expect(post).toHaveBeenCalledWith('/products/', { sku: product.sku });
    expect(patch).toHaveBeenCalledWith(`/products/${product.id}`, { sku: product.sku });
  });

  it('accepts the empty 204 deletion response', async () => {
    const request = vi.spyOn(apiClient, 'delete').mockResolvedValue({ status: 204, data: '' });
    await expect(deleteProduct(product.id)).resolves.toBeUndefined();
    expect(request).toHaveBeenCalledWith(`/products/${product.id}`);
  });
});
