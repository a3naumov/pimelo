import { describe, expect, it } from 'vitest';
import { productInputSchema, productsResponseSchema } from './schemas';

describe('Product input validation', () => {
  it.each([{}, { sku: null }, { sku: 1 }, { sku: '' }, { sku: ' \t\r\n\0\v' }])(
    'rejects missing, non-string, or empty SKUs: %j',
    (input) => expect(productInputSchema.safeParse(input).success).toBe(false),
  );

  it('counts Unicode code points instead of UTF-16 units', () => {
    expect(productInputSchema.safeParse({ sku: '😀'.repeat(255) }).success).toBe(true);
    expect(productInputSchema.safeParse({ sku: '😀'.repeat(256) }).success).toBe(false);
    expect(productInputSchema.safeParse({ sku: 'a'.repeat(256) }).success).toBe(false);
  });

  it('trims the same edge characters as the backend before checking and saving a SKU', () => {
    expect(productInputSchema.parse({ sku: ' \t\0SKU\v\r\n' })).toEqual({ sku: 'SKU' });
    expect(productInputSchema.safeParse({ sku: ` ${'a'.repeat(255)} ` }).success).toBe(true);
  });
});

describe('Product response validation', () => {
  it.each([
    [],
    { products: null },
    { products: [{ id: 'invalid', sku: 'SKU', deleted_at: null }] },
    { products: [{ id: '0195f582-9762-7c2a-9228-4060489e06d8', sku: null }] },
  ])('rejects malformed responses: %j', (response) => {
    expect(productsResponseSchema.safeParse(response).success).toBe(false);
  });
});
