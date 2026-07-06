<?php

declare(strict_types=1);

namespace Salix\Cms\Config;

enum BlockType: string
{
    case RICH_TEXT = 'rich_text';
    case IMAGE = 'image';
    case HERO = 'hero';
    case TEXT_IMAGE = 'text_image';
    case CTA = 'cta';
    case PRICING_TABLE = 'pricing_table';

    public function label(): string
    {
        return match($this) {
            self::RICH_TEXT => 'Rich Text',
            self::IMAGE => 'Image',
            self::HERO => 'Hero',
            self::TEXT_IMAGE => 'Text + Image',
            self::CTA => 'Call to Action',
            self::PRICING_TABLE => 'Pricing Table',
        };
    }

    /**
     * Required (non-empty) `data` keys for this block type.
     *
     * @return list<string>
     */
    public function requiredFields(): array
    {
        return match($this) {
            self::IMAGE => ['alt', 'filename'],
            self::HERO => ['heading'],
            self::TEXT_IMAGE => ['image_side', 'image_alt', 'filename'],
            self::CTA => ['heading', 'button_text', 'button_url'],
            self::RICH_TEXT, self::PRICING_TABLE => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
