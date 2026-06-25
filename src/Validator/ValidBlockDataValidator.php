<?php

declare(strict_types=1);

namespace App\Validator;

use App\Config\BlockType;
use App\Entity\ContentBlock;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidBlockDataValidator extends ConstraintValidator
{
    /**
     * Required (non-empty) `data` keys per block type.
     *
     * @var array<string, list<string>>
     */
    private const array REQUIRED_FIELDS = [
        BlockType::IMAGE->value => ['alt', 'filename'],
        BlockType::HERO->value => ['heading'],
        BlockType::TEXT_IMAGE->value => ['image_side', 'image_alt', 'filename'],
        BlockType::CTA->value => ['heading', 'button_text', 'button_url'],
        BlockType::RICH_TEXT->value => [],
    ];

    /**
     * Allowed (optional) `size` values for image-bearing blocks.
     *
     * @var list<string>
     */
    private const array ALLOWED_SIZES = ['small', 'medium', 'large', 'full'];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidBlockData) {
            throw new UnexpectedValueException($constraint, ValidBlockData::class);
        }

        if (!$value instanceof ContentBlock) {
            return;
        }

        $type = BlockType::tryFrom($value->getType());
        if (null === $type) {
            // The Choice constraint on `type` already reports this.
            return;
        }

        $data = $value->getData();

        foreach (self::REQUIRED_FIELDS[$type->value] as $field) {
            $fieldValue = $data[$field] ?? null;
            if (!\is_string($fieldValue) || '' === trim($fieldValue)) {
                $this->context->buildViolation('This field is required.')
                    ->atPath('data.'.$field)
                    ->addViolation();
            }
        }

        if (BlockType::TEXT_IMAGE === $type
            && isset($data['image_side'])
            && !\in_array($data['image_side'], ['left', 'right'], true)
        ) {
            $this->context->buildViolation('Image position must be "left" or "right".')
                ->atPath('data.image_side')
                ->addViolation();
        }

        if (\in_array($type, [BlockType::IMAGE, BlockType::TEXT_IMAGE], true)
            && isset($data['size'])
            && !\in_array($data['size'], self::ALLOWED_SIZES, true)
        ) {
            $this->context->buildViolation('Invalid image size.')
                ->atPath('data.size')
                ->addViolation();
        }

        if (\in_array($type, [BlockType::IMAGE, BlockType::TEXT_IMAGE], true)
            && isset($data['link_full'])
            && !\is_bool($data['link_full'])
        ) {
            $this->context->buildViolation('Link to full-size image must be true or false.')
                ->atPath('data.link_full')
                ->addViolation();
        }
    }
}
