import type { MessageKey } from '@/shared/i18n';
import { z } from 'zod';

function trimName(value: string): string {
  let start = 0;
  let end = value.length;
  const isTrimCharacter = (code: number) =>
    code === 0 || code === 32 || code === 9 || code === 10 || code === 11 || code === 13;

  while (start < end && isTrimCharacter(value.charCodeAt(start))) {
    start++;
  }

  while (end > start && isTrimCharacter(value.charCodeAt(end - 1))) {
    end--;
  }

  return value.slice(start, end);
}

export const nameSchema = z
  .string()
  .transform(trimName)
  .refine(
    (value) => value.length > 0,
    'attributes.attribute.validation.required' satisfies MessageKey,
  )
  .refine(
    (value) => Array.from(value).length <= 255,
    'attributes.attribute.validation.tooLong' satisfies MessageKey,
  );

export const attributeInputSchema = z.object({ name: nameSchema });
export const attributeStatusSchema = z.enum(['active', 'deleted']);
export type AttributeStatus = z.infer<typeof attributeStatusSchema>;
export const attributeSchema = z.object({
  id: z.uuid(),
  name: z.string().min(1),
  created_at: z.iso.datetime({ offset: true }),
  updated_at: z.iso.datetime({ offset: true }),
  deleted_at: z.iso.datetime({ offset: true }).nullable(),
});
export const attributeResponseSchema = z.object({ attribute: attributeSchema });
export const attributesResponseSchema = z.object({ attributes: z.array(attributeSchema) });

export type Attribute = z.infer<typeof attributeSchema>;
export type AttributeInput = z.infer<typeof attributeInputSchema>;
export type AttributeResponse = z.infer<typeof attributeResponseSchema>;
export type AttributesResponse = z.infer<typeof attributesResponseSchema>;
