/**
 * Bootstrap badge variants offered by the editor's badge format.
 *
 * Kept apart from `quill-badge.ts` so the toolbar config can name them without pulling Quill
 * into the initial bundle. Mirrored by Salix\Cms\Service\Quill\BadgeListener::VARIANTS.
 */
export const BADGE_VARIANTS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'] as const;

export type BadgeVariant = (typeof BADGE_VARIANTS)[number];
