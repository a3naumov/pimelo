import type { AxiosResponse } from 'axios';
import { apiClient } from '@/shared/api/client';
import { attributeApiRoutes } from './routes';
import {
  attributeResponseSchema,
  attributesResponseSchema,
  type Attribute,
  type AttributeInput,
  type AttributeResponse,
  type AttributesResponse,
  type AttributeStatus,
} from '../../model/attribute/schemas';

export async function getAttributes(
  status: AttributeStatus = 'active',
  signal?: AbortSignal,
): Promise<AttributesResponse> {
  const { data } = await apiClient.get<AttributesResponse>(attributeApiRoutes.collection, {
    signal,
    params: status === 'deleted' ? { status } : undefined,
  });

  return attributesResponseSchema.parse(data);
}

export async function getAttribute(id: string, signal?: AbortSignal): Promise<Attribute> {
  const { data } = await apiClient.get<AttributeResponse>(attributeApiRoutes.item(id), {
    signal,
    params: { include_deleted: 1 },
  });

  return attributeResponseSchema.parse(data).attribute;
}

export async function createAttribute(input: AttributeInput): Promise<Attribute> {
  const { data } = await apiClient.post<
    AttributeResponse,
    AxiosResponse<AttributeResponse, AttributeInput>,
    AttributeInput
  >(attributeApiRoutes.collection, input);

  return attributeResponseSchema.parse(data).attribute;
}

export async function updateAttribute(id: string, input: AttributeInput): Promise<Attribute> {
  const { data } = await apiClient.patch<
    AttributeResponse,
    AxiosResponse<AttributeResponse, AttributeInput>,
    AttributeInput
  >(attributeApiRoutes.item(id), input);

  return attributeResponseSchema.parse(data).attribute;
}

export async function deleteAttribute(id: string): Promise<void> {
  await apiClient.delete<void>(attributeApiRoutes.item(id));
}

export async function restoreAttribute(id: string): Promise<Attribute> {
  const { data } = await apiClient.post<AttributeResponse>(attributeApiRoutes.restore(id));

  return attributeResponseSchema.parse(data).attribute;
}

export async function deleteAttributePermanently(id: string): Promise<void> {
  await apiClient.delete<void>(attributeApiRoutes.permanentDelete(id));
}
