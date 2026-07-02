<?php

declare(strict_types=1);

namespace App\Service\Quill;

use nadar\quill\Lexer;
use nadar\quill\listener\Align;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Like the built-in {@see Align} listener, but emits a `ql-align-*` class instead of an inline
 * `style="text-align:…"` so the rendered HTML can be sanitized with a `class`-only allowlist.
 */
final class AlignClassListener extends Align
{
    #[\Override]
    public function render(Lexer $lexer): void
    {
        foreach ($this->picks() as $pick) {
            if (!\in_array($pick->optionValue('alignment'), $this->alignments, true)) {
                throw new UnexpectedValueException($pick->optionValue('alignment'), 'A known alignment ('.implode(', ', $this->alignments).')');
            }
        }

        $this->wrapElement('<p class="ql-align-{alignment}">{__buffer__}</p>', ['alignment']);
    }
}
