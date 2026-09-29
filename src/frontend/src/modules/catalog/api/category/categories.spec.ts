import { afterEach, describe, expect, it, vi } from 'vitest';
import { ZodError } from 'zod';
import { apiClient } from '@/shared/api/client';
import * as api from './categories';
const category = {
  id: '0195f582-9762-7c2a-9228-4060489e06d8',
  parent_id: null,
  has_children: false,
  deleted_at: null,
};
afterEach(() => vi.restoreAllMocks());
describe('Category API contract', () => {
  it('reads validated envelopes and propagates cancellation', async () => {
    const signal = new AbortController().signal;
    const get = vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { categories: [category] } });
    expect(await api.getCategories(null, signal)).toEqual({ categories: [category] });
    expect(get).toHaveBeenLastCalledWith('/categories/', { signal });
    expect(await api.getCategories('parent', signal)).toEqual({ categories: [category] });
    expect(get).toHaveBeenLastCalledWith('/categories/', {
      signal,
      params: { parent_id: 'parent' },
    });
    get.mockResolvedValue({ data: { products: [] } });
    expect(await api.getCategoryProducts('parent', signal)).toEqual({ products: [] });
    expect(get).toHaveBeenLastCalledWith('/categories/parent/products/', { signal });
    const branch = { path: [category], levels: [{ parent_id: null, categories: [category] }] };
    get.mockResolvedValue({ data: branch });
    expect(await api.getCategoryBranch(category.id, signal)).toEqual(branch);
    expect(get).toHaveBeenLastCalledWith(`/categories/${category.id}/branch`, { signal });
    get.mockResolvedValue({ data: { ...branch, levels: [] } });
    await expect(api.getCategoryBranch(category.id)).rejects.toBeInstanceOf(ZodError);
    get.mockResolvedValue({ data: { category } });
    expect(await api.getCategory(category.id, signal)).toEqual(category);
    expect(get).toHaveBeenLastCalledWith(`/categories/${category.id}`, { signal });
    get.mockResolvedValue({ data: { categories: [{ id: category.id }] } });
    await expect(api.getCategories(null)).rejects.toBeInstanceOf(ZodError);
  });
  it.each([null, '0195f582-9762-7c2a-9228-4060489e06d9'])(
    'creates a category with parent %s',
    async (parent_id) => {
      const created = { ...category, parent_id };
      const post = vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { category: created } });
      expect(await api.createCategory({ parent_id })).toEqual(created);
      expect(post).toHaveBeenCalledExactlyOnceWith('/categories/', { parent_id });
    },
  );
  it('patches nullable parent and accepts 204', async () => {
    const patch = vi.spyOn(apiClient, 'patch').mockResolvedValue({ data: { category } });
    const remove = vi.spyOn(apiClient, 'delete').mockResolvedValue({ status: 204 });
    await api.updateCategory(category.id, { parent_id: null });
    expect(patch).toHaveBeenCalledWith(`/categories/${category.id}`, {
      parent_id: null,
    });
    await api.deleteCategory(category.id);
    expect(remove).toHaveBeenCalledWith(`/categories/${category.id}`);
  });
  it('changes only the requested relationship and encodes route parameters', async () => {
    const put = vi.spyOn(apiClient, 'put').mockResolvedValue({ status: 204 });
    const remove = vi.spyOn(apiClient, 'delete').mockResolvedValue({ status: 204 });
    await api.attachProduct({ productId: 'a/b', categoryId: 'c/d' });
    await api.detachProduct({ productId: 'a/b', categoryId: 'c/d' });
    expect(put).toHaveBeenCalledExactlyOnceWith('/categories/c%2Fd/products/a%2Fb');
    expect(remove).toHaveBeenCalledExactlyOnceWith('/categories/c%2Fd/products/a%2Fb');
  });
});

describe('Deleted category API', () => {
  it('passes visibility explicitly and validates deletion timestamps', async () => {
    const signal = new AbortController().signal;
    const deleted = { ...category, deleted_at: '2026-09-28T12:00:00+00:00' };
    const get = vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { categories: [deleted] } });
    expect(await api.getCategories(null, signal, true)).toEqual({ categories: [deleted] });
    expect(get).toHaveBeenLastCalledWith('/categories/', {
      signal,
      params: { include_deleted: 1 },
    });
    expect(await api.getCategoryChildren(category.id, signal, true)).toEqual({
      categories: [deleted],
    });
    expect(get).toHaveBeenLastCalledWith('/categories/', {
      signal,
      params: { parent_id: category.id, include_deleted: 1 },
    });
    get.mockResolvedValue({ data: { categories: [{ ...deleted, deleted_at: 'invalid' }] } });
    await expect(api.getCategories(null, signal, true)).rejects.toBeInstanceOf(ZodError);
  });

  it('uses distinct endpoints for restoration and permanent deletion', async () => {
    const post = vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { category } });
    const remove = vi.spyOn(apiClient, 'delete').mockResolvedValue({ status: 204 });
    expect(await api.restoreCategory(category.id)).toEqual(category);
    await api.deleteCategoryPermanently(category.id);
    expect(post).toHaveBeenCalledExactlyOnceWith(`/categories/${category.id}/restore`);
    expect(remove).toHaveBeenCalledExactlyOnceWith(`/categories/${category.id}/permanent`);
  });
});
