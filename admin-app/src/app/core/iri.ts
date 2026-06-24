/** A reference to an API resource: an IRI string, an object carrying '@id'/'id', or empty. */
export type IriRef = string | { '@id'?: string; id?: number } | null | undefined;

/**
 * Extract the numeric id (as a string) from an entity reference.
 *
 * Relations come back from the plain-JSON API (`formats: json`, no JSON-LD) either as an
 * IRI string (e.g. `/api/articles/5`) or as a nested object carrying `id`. This normalizes
 * both to the bare id used as a raw form value; returns '' when there is nothing to reference.
 *
 * Intended for **service-internal** use (mapping stored entities to form values), not components.
 */
export function idFromRef(ref: IriRef): string {
  if (!ref) {
    return '';
  }
  if (typeof ref === 'string') {
    return ref.split('/').pop() ?? '';
  }
  return ref.id != null ? String(ref.id) : '';
}
