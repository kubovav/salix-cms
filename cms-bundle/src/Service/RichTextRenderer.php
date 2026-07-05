<?php

declare(strict_types=1);

namespace Salix\Cms\Service;

use Salix\Cms\Service\Quill\AlignClassListener;
use Salix\Cms\Service\Quill\HeadingClassListener;
use nadar\quill\Lexer;
use nadar\quill\listener\Align;
use nadar\quill\listener\Heading;

/**
 * Renders a Quill Delta document to HTML.
 *
 * The Delta is the canonical, editable representation stored on the block; this produces the
 * derived HTML that is sanitized and cached in ContentBlock::$renderedHtml for the frontend.
 */
final class RichTextRenderer
{
    /**
     * @param array<mixed> $delta the Quill Delta (a `{ops: [...]}` structure)
     */
    public function render(array $delta): string
    {
        if ([] === $delta) {
            return '';
        }

        $lexer = new Lexer($delta);
        $lexer->overwriteListener(new Align(), new AlignClassListener());
        $lexer->overwriteListener(new Heading(), new HeadingClassListener());

        return trim($lexer->render());
    }
}
