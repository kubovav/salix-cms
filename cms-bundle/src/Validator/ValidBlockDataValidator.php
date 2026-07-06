<?php

declare(strict_types=1);

namespace Salix\Cms\Validator;

use Salix\Cms\Config\BlockType;
use Salix\Cms\Entity\ContentBlock;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidBlockDataValidator extends ConstraintValidator
{
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

        foreach ($type->requiredFields() as $field) {
            $fieldValue = $data[$field] ?? null;
            if (!\is_string($fieldValue) || '' === trim($fieldValue)) {
                $this->context->buildViolation('This field is required.')
                    ->atPath('data.'.$field)
                    ->addViolation();
            }
        }

        if (\in_array($type, [BlockType::RICH_TEXT, BlockType::TEXT_IMAGE], true)
            && isset($data['delta'])
            && (!\is_array($data['delta']) || !\is_array($data['delta']['ops'] ?? null))
        ) {
            $this->context->buildViolation('Invalid rich-text content.')
                ->atPath('data.delta')
                ->addViolation();
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

        if (BlockType::PRICING_TABLE === $type) {
            $this->validatePricingTable($data);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validatePricingTable(array $data): void
    {
        $plans = $data['plans'] ?? null;

        if (!\is_array($plans) || [] === $plans) {
            $this->context->buildViolation('Add at least one plan.')
                ->atPath('data.plans')
                ->addViolation();

            return;
        }

        foreach ($plans as $index => $plan) {
            if (!\is_array($plan)) {
                $this->context->buildViolation('Invalid plan.')
                    ->atPath('data.plans['.$index.']')
                    ->addViolation();

                continue;
            }

            $name = $plan['name'] ?? null;
            if (!\is_string($name) || '' === trim($name)) {
                $this->context->buildViolation('This field is required.')
                    ->atPath('data.plans['.$index.'].name')
                    ->addViolation();
            }

            if (isset($plan['features'])
                && (!\is_array($plan['features'])
                    || [] !== \array_filter($plan['features'], static fn ($feature): bool => !\is_string($feature)))
            ) {
                $this->context->buildViolation('Features must be a list of text values.')
                    ->atPath('data.plans['.$index.'].features')
                    ->addViolation();
            }
        }
    }
}
