import { afterEach, describe, expect, it, vi } from 'vitest';
import { ZodError } from 'zod';
import { apiClient } from '@/shared/api/client';
import {
  createAttribute,
  deleteAttribute,
  deleteAttributePermanently,
  restoreAttribute,
  getAttribute,
  getAttributes,
  updateAttribute,
} from './attributes';

const attribute = {
  id: '0195f582-9762-7c2a-9228-4060489e06d8',
  name: 'Color',
  created_at: '2026-09-30T10:00:00+00:00',
  updated_at: '2026-09-30T10:00:00+00:00',
  deleted_at: null,
};
afterEach(() => vi.restoreAllMocks());

describe('Attribute read API', () => {
  it('requests only deleted attributes and validates deletion timestamps', async () => {
    const archived = { ...attribute, deleted_at: '2026-09-25T10:00:00+00:00' };
    const request = vi
      .spyOn(apiClient, 'get')
      .mockResolvedValue({ data: { attributes: [archived] } });
    expect(await getAttributes('deleted')).toEqual({ attributes: [archived] });
    expect(request).toHaveBeenCalledWith('/attributes/', {
      signal: undefined,
      params: { status: 'deleted' },
    });
    request.mockResolvedValue({ data: { attributes: [{ ...archived, deleted_at: 'invalid' }] } });
    await expect(getAttributes('deleted')).rejects.toBeInstanceOf(ZodError);
  });
  it('preserves the list envelope and forwards cancellation', async () => {
    const request = vi
      .spyOn(apiClient, 'get')
      .mockResolvedValue({ data: { attributes: [attribute] } });
    const signal = new AbortController().signal;
    expect(await getAttributes('active', signal)).toEqual({ attributes: [attribute] });
    expect(request).toHaveBeenCalledWith('/attributes/', { signal, params: undefined });
  });

  it('unwraps the detail response and forwards cancellation', async () => {
    const request = vi.spyOn(apiClient, 'get').mockResolvedValue({ data: { attribute } });
    const signal = new AbortController().signal;
    expect(await getAttribute(attribute.id, signal)).toEqual(attribute);
    expect(request).toHaveBeenCalledWith(`/attributes/${attribute.id}`, {
      signal,
      params: { include_deleted: 1 },
    });
  });

  it('rejects a successful response that breaks the contract', async () => {
    vi.spyOn(apiClient, 'get').mockResolvedValue({
      data: { attributes: [{ name: 'missing-id' }] },
    });
    await expect(getAttributes()).rejects.toBeInstanceOf(ZodError);
  });
});

describe('Attribute write API', () => {
  it('restores and permanently deletes through explicit endpoints', async () => {
    const post = vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { attribute } });
    const remove = vi.spyOn(apiClient, 'delete').mockResolvedValue({ status: 204, data: '' });
    expect(await restoreAttribute(attribute.id)).toEqual(attribute);
    await expect(deleteAttributePermanently(attribute.id)).resolves.toBeUndefined();
    expect(post).toHaveBeenCalledWith(`/attributes/${attribute.id}/restore`);
    expect(remove).toHaveBeenCalledWith(`/attributes/${attribute.id}/permanent`);
  });
  it('creates and updates through their respective endpoints', async () => {
    const post = vi.spyOn(apiClient, 'post').mockResolvedValue({ data: { attribute } });
    const patch = vi.spyOn(apiClient, 'patch').mockResolvedValue({ data: { attribute } });
    expect(await createAttribute({ name: attribute.name })).toEqual(attribute);
    expect(await updateAttribute(attribute.id, { name: attribute.name })).toEqual(attribute);
    expect(post).toHaveBeenCalledWith('/attributes/', { name: attribute.name });
    expect(patch).toHaveBeenCalledWith(`/attributes/${attribute.id}`, { name: attribute.name });
  });

  it('accepts the empty 204 deletion response', async () => {
    const request = vi.spyOn(apiClient, 'delete').mockResolvedValue({ status: 204, data: '' });
    await expect(deleteAttribute(attribute.id)).resolves.toBeUndefined();
    expect(request).toHaveBeenCalledWith(`/attributes/${attribute.id}`);
  });
});
