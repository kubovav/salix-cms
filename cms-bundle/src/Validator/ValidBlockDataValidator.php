<?php

declare(strict_types=1);

namespace Salix\Cms\Validator;

use Salix\Cms\Config\BlockType;
use Salix\Cms\Entity\ContentBlock;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates a block's `data` against the field schema of its type (see
 * BlockType::fields()): unknown keys are rejected, every present value must
 * match its kind, URLs must be safe to render into href/src attributes and
 * filenames must be plain names (no slashes), since the frontend templates
 * interpolate them into URL and CSS contexts.
 */
final class ValidBlockDataValidator extends ConstraintValidator
{
    private const int MAX_STRING_LENGTH = 1000;

    private const int MAX_URL_LENGTH = 500;

    private const int MAX_PLANS = 25;

    private const int MAX_FEATURES = 50;

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
        $fields = $type->fields();

        foreach (array_keys($data) as $field) {
            if (!isset($fields[$field])) {
                $this->context->buildViolation('Unknown field.')
                    ->atPath('data.'.$field)
                    ->addViolation();
            }
        }

        $reported = [];
        foreach ($type->requiredFields() as $field) {
            $fieldValue = $data[$field] ?? null;
            if (!\is_string($fieldValue) || '' === trim($fieldValue)) {
                $this->context->buildViolation('This field is required.')
                    ->atPath('data.'.$field)
                    ->addViolation();
                $reported[$field] = true;
            }
        }

        foreach ($fields as $field => $kind) {
            if (isset($reported[$field])) {
                continue;
            }

            if (!\array_key_exists($field, $data)) {
                continue;
            }

            if (null === $data[$field]) {
                continue;
            }

            $this->validateKind('data.'.$field, $data[$field], $kind);
        }
    }

    private function validateKind(string $path, mixed $value, string $kind): void
    {
        match (true) {
            'string' === $kind => $this->validateString($path, $value),
            'url' === $kind => $this->validateUrl($path, $value),
            'filename' === $kind => $this->validateFilename($path, $value),
            'bool' === $kind => $this->validateBool($path, $value),
            'delta' === $kind => $this->validateDelta($path, $value),
            'plans' === $kind => $this->validatePlans($value),
            str_starts_with($kind, 'enum:') => $this->validateEnum($path, $value, explode(',', substr($kind, 5))),
            default => throw new \LogicException(sprintf('Unknown block field kind "%s".', $kind)),
        };
    }

    private function validateString(string $path, mixed $value): void
    {
        if (!\is_string($value)) {
            $this->addViolation($path, 'Must be text.');

            return;
        }

        if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
            $this->addViolation($path, sprintf('Must be %d characters or fewer.', self::MAX_STRING_LENGTH));
        }
    }

    /**
     * Values land in href/src attributes: allow a relative path, an in-page
     * anchor or an absolute http(s) URL — never other schemes (javascript: …).
     * Mirrors MenuItem::validateUrl().
     */
    private function validateUrl(string $path, mixed $value): void
    {
        if (!\is_string($value)) {
            $this->addViolation($path, 'Must be text.');

            return;
        }

        if ('' === $value) {
            return;
        }

        if (mb_strlen($value) > self::MAX_URL_LENGTH) {
            $this->addViolation($path, sprintf('Must be %d characters or fewer.', self::MAX_URL_LENGTH));

            return;
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '#')) {
            return;
        }

        if (!preg_match('#^https?://#i', $value) || false === filter_var($value, \FILTER_VALIDATE_URL)) {
            $this->addViolation($path, 'Enter a relative path ("/about"), an anchor ("#section") or a full http(s) URL.');
        }
    }

    /**
     * Uploaded-image filenames are interpolated into URL and CSS url() contexts
     * by the frontend templates, so only plain names are accepted (no slashes,
     * no quotes, no leading dot).
     */
    private function validateFilename(string $path, mixed $value): void
    {
        if (!\is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,100}$/', $value)) {
            $this->addViolation($path, 'Invalid filename.');
        }
    }

    private function validateBool(string $path, mixed $value): void
    {
        if (!\is_bool($value)) {
            $this->addViolation($path, 'Must be true or false.');
        }
    }

    /**
     * @param list<string> $choices
     */
    private function validateEnum(string $path, mixed $value, array $choices): void
    {
        if (!\in_array($value, $choices, true)) {
            $this->addViolation($path, sprintf('Must be one of: %s.', implode(', ', $choices)));
        }
    }

    private function validateDelta(string $path, mixed $value): void
    {
        if (!\is_array($value) || !\is_array($value['ops'] ?? null)) {
            $this->addViolation($path, 'Invalid rich-text content.');
        }
    }

    private function validatePlans(mixed $plans): void
    {
        if (!\is_array($plans) || [] === $plans) {
            $this->addViolation('data.plans', 'Add at least one plan.');

            return;
        }

        if (\count($plans) > self::MAX_PLANS) {
            $this->addViolation('data.plans', sprintf('At most %d plans are allowed.', self::MAX_PLANS));

            return;
        }

        foreach ($plans as $index => $plan) {
            if (!\is_array($plan)) {
                $this->addViolation('data.plans['.$index.']', 'Invalid plan.');

                continue;
            }

            $this->validatePlan('data.plans['.$index.']', $plan);
        }
    }

    /**
     * @param array<array-key, mixed> $plan
     */
    private function validatePlan(string $path, array $plan): void
    {
        foreach (array_keys($plan) as $field) {
            if (!\in_array($field, ['name', 'price', 'features', 'button_text', 'button_url'], true)) {
                $this->addViolation($path.'.'.$field, 'Unknown field.');
            }
        }

        $name = $plan['name'] ?? null;
        if (!\is_string($name) || '' === trim($name)) {
            $this->addViolation($path.'.name', 'This field is required.');
        } else {
            $this->validateString($path.'.name', $name);
        }

        foreach (['price', 'button_text'] as $field) {
            if (isset($plan[$field])) {
                $this->validateString($path.'.'.$field, $plan[$field]);
            }
        }

        if (isset($plan['button_url'])) {
            $this->validateUrl($path.'.button_url', $plan['button_url']);
        }

        if (isset($plan['features'])) {
            $this->validateFeatures($path.'.features', $plan['features']);
        }
    }

    private function validateFeatures(string $path, mixed $features): void
    {
        if (!\is_array($features) || [] !== array_filter($features, static fn ($feature): bool => !\is_string($feature))) {
            $this->addViolation($path, 'Features must be a list of text values.');

            return;
        }

        if (\count($features) > self::MAX_FEATURES) {
            $this->addViolation($path, sprintf('At most %d features are allowed.', self::MAX_FEATURES));

            return;
        }

        foreach ($features as $index => $feature) {
            if (mb_strlen((string) $feature) > self::MAX_STRING_LENGTH) {
                $this->addViolation($path.'['.$index.']', sprintf('Must be %d characters or fewer.', self::MAX_STRING_LENGTH));
            }
        }
    }

    private function addViolation(string $path, string $message): void
    {
        $this->context->buildViolation($message)
            ->atPath($path)
            ->addViolation();
    }
}
