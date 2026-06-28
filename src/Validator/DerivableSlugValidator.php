<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\ContentPage;
use App\Service\SlugGenerator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class DerivableSlugValidator extends ConstraintValidator
{
    public function __construct(
        private readonly SlugGenerator $slugGenerator,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DerivableSlug) {
            throw new UnexpectedValueException($constraint, DerivableSlug::class);
        }

        if (!$value instanceof ContentPage) {
            return;
        }

        // Only relevant when the slug will be auto-generated; an explicit slug is
        // validated by the Regex/Length/UniqueEntity constraints instead.
        $slug = $value->getSlug();
        if (null !== $slug && '' !== trim($slug)) {
            return;
        }

        // A blank title is reported by the NotBlank constraint on `title`.
        $title = $value->getTitle();
        if (null === $title || '' === trim($title)) {
            return;
        }

        if ('' === $this->slugGenerator->slugify($title)) {
            $this->context->buildViolation($constraint->message)
                ->atPath('slug')
                ->addViolation();
        }
    }
}
