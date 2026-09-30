import { computed, toValue, type MaybeRefOrGetter } from 'vue';
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import {
  createAttribute,
  deleteAttribute,
  deleteAttributePermanently,
  restoreAttribute,
  getAttribute,
  getAttributes,
  updateAttribute,
} from '../../api/attribute/attributes';
import type { Attribute, AttributeInput, AttributeStatus } from './schemas';

export const attributeKeys = {
  lists: () => ['attributes', 'list'] as const,
  list: (status: AttributeStatus) => [...attributeKeys.lists(), { status }] as const,
  detail: (id: string) => ['attributes', 'detail', id] as const,
};

export function useAttributes(
  status: MaybeRefOrGetter<AttributeStatus> = 'active',
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  return useQuery({
    queryKey: computed(() => attributeKeys.list(toValue(status))),
    enabled,
    queryFn: ({ signal }) => getAttributes(toValue(status), signal),
  });
}

export function useAttribute(id: MaybeRefOrGetter<string>) {
  return useQuery({
    queryKey: computed(() => attributeKeys.detail(toValue(id))),
    queryFn: ({ signal }) => getAttribute(toValue(id), signal),
  });
}

export function useAttributeMutations() {
  const client = useQueryClient();

  async function saved(attribute: Attribute) {
    await client.cancelQueries({ queryKey: attributeKeys.detail(attribute.id) });
    client.setQueryData(attributeKeys.detail(attribute.id), attribute);
    await client.invalidateQueries({ queryKey: attributeKeys.lists() });
  }

  const create = useMutation({ mutationFn: createAttribute, onSuccess: saved });

  const restore = useMutation({ mutationFn: restoreAttribute, onSuccess: saved });

  const update = useMutation({
    mutationFn: ({ id, input }: { id: string; input: AttributeInput }) =>
      updateAttribute(id, input),
    onSuccess: saved,
  });

  async function removed(id: string) {
    await client.cancelQueries({ queryKey: attributeKeys.detail(id) });
    client.removeQueries({ queryKey: attributeKeys.detail(id) });
    await client.invalidateQueries({ queryKey: attributeKeys.lists() });
  }

  const remove = useMutation({
    mutationFn: deleteAttribute,
    onSuccess: (_, id) => removed(id),
  });

  const purge = useMutation({
    mutationFn: deleteAttributePermanently,
    onSuccess: (_, id) => removed(id),
  });

  return { create, update, remove, restore, purge };
}
