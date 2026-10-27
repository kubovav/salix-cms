import Quill from 'quill';
import Inline from 'quill/blots/inline';
import { BADGE_VARIANTS, type BadgeVariant } from '@core/quill-badge-variants';

const DEFAULT_VARIANT: BadgeVariant = 'primary';

function isBadgeVariant(value: unknown): value is BadgeVariant {
  return BADGE_VARIANTS.includes(value as BadgeVariant);
}

/**
 * Inline format wrapping the selection in a Bootstrap badge.
 *
 * The Delta is the stored representation, so this only governs the editor; the frontend HTML is
 * produced from the same `badge` attribute by Salix\Cms\Service\Quill\BadgeListener. Keep the
 * variant list in sync with that listener — a variant it does not know is dropped when rendering.
 */
class BadgeBlot extends Inline {
  static override blotName = 'badge';
  static override tagName = 'SPAN';
  static override className = 'badge';

  static override create(value?: unknown): HTMLElement {
    const node = super.create();
    node.classList.add(`text-bg-${isBadgeVariant(value) ? value : DEFAULT_VARIANT}`);

    return node;
  }

  static override formats(node: HTMLElement): BadgeVariant | undefined {
    return BADGE_VARIANTS.find((variant) => node.classList.contains(`text-bg-${variant}`));
  }
}

let registered = false;

export function registerBadgeFormat(): void {
  if (registered) {
    return;
  }
  registered = true;

  // Inline.order runs from innermost (low index) to outermost; sitting above the character
  // formats keeps a badge applied over mixed bold/italic text as one pill instead of several,
  // and matches the nesting the server-side renderer emits.
  if (!Inline.order.includes(BadgeBlot.blotName)) {
    Inline.order.splice(Inline.order.indexOf('code'), 0, BadgeBlot.blotName);
  }

  Quill.register(BadgeBlot);
}
