import type { MessageKey } from '@/shared/i18n';
import { z } from 'zod';

function trimSku(value: string): string {
  let start = 0;
  let end = value.length;
  const isTrimCharacter = (code: number) => code === 0 || code === 32 || (code >= 9 && code <= 13);

  while (start < end && isTrimCharacter(value.charCodeAt(start))) {
    start++;
  }

  while (end > start && isTrimCharacter(value.charCodeAt(end - 1))) {
    end--;
  }

  return value.slice(start, end);
}

export const skuSchema = z
  .string()
  .transform(trimSku)
  .refine((value) => value.length > 0, 'catalog.product.validation.required' satisfies MessageKey)
  .refine(
    (value) => Array.from(value).length <= 255,
    'catalog.product.validation.tooLong' satisfies MessageKey,
  );

export const productInputSchema = z.object({ sku: skuSchema });
export const productStatusSchema = z.enum(['active', 'deleted']);
export type ProductStatus = z.infer<typeof productStatusSchema>;
export const productSchema = z.object({
  id: z.uuid(),
  sku: z.string().min(1),
  deleted_at: z.iso.datetime({ offset: true }).nullable(),
});
export const productResponseSchema = z.object({ product: productSchema });
export const productsResponseSchema = z.object({ products: z.array(productSchema) });

export type Product = z.infer<typeof productSchema>;
export type ProductInput = z.infer<typeof productInputSchema>;
export type ProductResponse = z.infer<typeof productResponseSchema>;
export type ProductsResponse = z.infer<typeof productsResponseSchema>;
