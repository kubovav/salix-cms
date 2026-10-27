<?php

declare(strict_types=1);

namespace Salix\Cms\Service\Quill;

use nadar\quill\InlineListener;
use nadar\quill\Line;

/**
 * Renders the editor's custom `badge` inline format as a Bootstrap badge.
 *
 * Registered after the built-in inline listeners so it wraps their output — a bolded badge comes
 * out as `<span class="badge …"><strong>…</strong></span>`, matching the nesting the editor
 * produces. The variant list is the allowlist for the emitted class and mirrors
 * `@core/quill-badge-variants` in the admin app; an unknown variant renders as plain text.
 */
final class BadgeListener extends InlineListener
{
    /**
     * @var list<string>
     */
    private const array VARIANTS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];

    #[\Override]
    public function process(Line $line): void
    {
        $attributes = $line->getAttributes();
        if (!\array_key_exists('badge', $attributes)) {
            return;
        }

        $variant = $attributes['badge'];
        if (!\is_string($variant) || !\in_array($variant, self::VARIANTS, true)) {
            // An attribute no listener claims makes the Text listener skip the line entirely, so
            // an unusable variant has to be claimed anyway — it just loses the badge styling
            // rather than deleting the content.
            $this->updateInput($line, $line->getInput());

            return;
        }

        $this->updateInput($line, '<span class="badge text-bg-'.$variant.'">'.$line->getInput().'</span>');
    }
}
