<?php

declare(strict_types=1);

namespace Salix\Cms\Service\Quill;

use nadar\quill\Lexer;
use nadar\quill\listener\Heading;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Like the built-in {@see Heading} listener, but renders heading alignment as a `ql-align-*`
 * class instead of an inline `style="text-align:…"`, matching {@see AlignClassListener}.
 */
final class HeadingClassListener extends Heading
{
    #[\Override]
    public function render(Lexer $lexer): void
    {
        foreach ($this->picks() as $pick) {
            if (!\in_array($pick->optionValue('heading'), $this->levels, true)) {
                throw new UnexpectedValueException($pick->optionValue('heading'), 'A known heading level');
            }

            $alignment = $pick->optionValue('alignment');
            if ($alignment && !\in_array($alignment, $this->alignments, true)) {
                throw new UnexpectedValueException($alignment, 'A known alignment');
            }
        }

        // @phpstan-ignore argument.type
        $this->wrapElement('<h{heading}{class}>{__buffer__}</h{heading}>', [
            'heading',
            'class' => static function ($value, $pick): string {
                $alignment = $pick->optionValue('alignment');

                return $alignment ? ' class="ql-align-'.$alignment.'"' : '';
            },
        ]);
    }
}
