import { describe, expect, it } from 'vitest';
import { attributeInputSchema, attributesResponseSchema } from './schemas';

describe('Attribute input validation', () => {
  it.each([{}, { name: null }, { name: 1 }, { name: '' }, { name: ' \t\r\n\0\v' }])(
    'rejects missing, non-string, or empty names: %j',
    (input) => expect(attributeInputSchema.safeParse(input).success).toBe(false),
  );

  it('counts Unicode code points instead of UTF-16 units', () => {
    expect(attributeInputSchema.safeParse({ name: '😀'.repeat(255) }).success).toBe(true);
    expect(attributeInputSchema.safeParse({ name: '😀'.repeat(256) }).success).toBe(false);
    expect(attributeInputSchema.safeParse({ name: 'a'.repeat(256) }).success).toBe(false);
  });

  it('trims the same edge characters as the backend before checking and saving a name', () => {
    expect(attributeInputSchema.parse({ name: ' \t\0Name\v\r\n' })).toEqual({ name: 'Name' });
    expect(attributeInputSchema.parse({ name: '\fColor\f' })).toEqual({ name: '\fColor\f' });
    expect(attributeInputSchema.safeParse({ name: ` ${'a'.repeat(255)} ` }).success).toBe(true);
  });
});

describe('Attribute response validation', () => {
  const attribute = {
    id: '0195f582-9762-7c2a-9228-4060489e06d8',
    name: 'Color',
    created_at: '2026-09-01T10:00:00+04:00',
    updated_at: '2026-09-30T10:00:00+04:00',
    deleted_at: null,
  };

  it('requires valid creation and update timestamps and preserves their offsets', () => {
    expect(attributesResponseSchema.parse({ attributes: [attribute] })).toEqual({
      attributes: [attribute],
    });

    for (const field of ['created_at', 'updated_at']) {
      for (const value of [undefined, null, 'invalid']) {
        expect(
          attributesResponseSchema.safeParse({ attributes: [{ ...attribute, [field]: value }] })
            .success,
        ).toBe(false);
      }
    }
  });

  it.each([
    [],
    { attributes: null },
    { attributes: [{ id: 'invalid', name: 'Name', deleted_at: null }] },
    { attributes: [{ id: '0195f582-9762-7c2a-9228-4060489e06d8', name: null }] },
  ])('rejects malformed responses: %j', (response) => {
    expect(attributesResponseSchema.safeParse(response).success).toBe(false);
  });
});
