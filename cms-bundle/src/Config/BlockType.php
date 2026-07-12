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
     * Complete `data` field schema for this block type: field name → kind.
     * Kinds: 'string' (plain text), 'url' (relative path, "#anchor" or absolute
     * http(s)), 'filename' (uploaded-image filename, no slashes), 'bool',
     * 'enum:a,b' (one of the listed values), 'delta' (Quill delta object),
     * 'plans' (pricing-table plan list). Keys not in the schema are rejected.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        return match($this) {
            self::RICH_TEXT => [
                'delta' => 'delta',
            ],
            self::IMAGE => [
                'alt' => 'string',
                'caption' => 'string',
                'size' => 'enum:small,medium,large,full',
                'link_full' => 'bool',
                'filename' => 'filename',
            ],
            self::HERO => [
                'heading' => 'string',
                'subtext' => 'string',
                'cta_text' => 'string',
                'cta_url' => 'url',
                'image_alt' => 'string',
                'filename' => 'filename',
            ],
            self::TEXT_IMAGE => [
                'delta' => 'delta',
                'image_side' => 'enum:left,right',
                'image_alt' => 'string',
                'size' => 'enum:small,medium,large,full',
                'link_full' => 'bool',
                'filename' => 'filename',
            ],
            self::CTA => [
                'heading' => 'string',
                'button_text' => 'string',
                'button_url' => 'url',
            ],
            self::PRICING_TABLE => [
                'plans' => 'plans',
            ],
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
